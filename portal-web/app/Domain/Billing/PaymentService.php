<?php

declare(strict_types=1);

namespace App\Domain\Billing;

use App\Models\PaymentTransaction;
use App\Models\Subscription;
use App\Support\SystemConfigValue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * 支付中心 — SaaS 订阅模式。
 *
 * V2: 使用 Stripe PaymentIntent + Payment Element 嵌入式支付。
 * 流程: 创建 Subscription(pending) → createCheckout(PaymentIntent) →
 *       Stripe Payment Element 前端支付 → webhook payment_intent.succeeded →
 *       PaymentService.handleSuccessByPaymentIntent() → SubscriptionService.activate()。
 */
final class PaymentService
{
    private const PAYMENT_METHOD_LABELS = [
        'card' => '信用卡',
        'wechat_pay' => '微信支付',
        'alipay' => '支付宝',
    ];

    /**
     * 获取 Stripe 运行模式（test / live）。
     */
    public function getMode(): string
    {
        $configured = SystemConfigValue::field('payment', 'mode', 'test');
        return in_array($configured, ['test', 'live'], true) ? $configured : 'test';
    }

    /**
     * 创建一个支付会话（Stripe PaymentIntent）。
     * 返回 PaymentTransaction，raw_payload 中包含 client_secret 供前端 Stripe Elements 使用。
     */
    public function createCheckout(Subscription $subscription, ?string $paymentMethod = null): PaymentTransaction
    {
        $paymentMethodTypes = $this->paymentMethodsForCheckout($paymentMethod);
        $secret = $this->stripeSecret();
        $mode = $this->getMode();
        $useFake = $this->useFakeCheckout();

        if (app()->environment('production') && $useFake) {
            throw new RuntimeException('Fake Stripe checkout is forbidden in production.');
        }

        // 复用同一 subscription 下 pending 状态的交易，防重复创建
        $existing = PaymentTransaction::query()
            ->where('subscription_id', $subscription->id)
            ->where('provider', 'stripe')
            ->where('status', PaymentTransaction::STATUS_PENDING)
            ->latest('id')
            ->first();
        $existingMethod = is_array($existing?->raw_payload)
            ? ($existing->raw_payload['payment_method'] ?? null)
            : null;
        if ($existing instanceof PaymentTransaction && ($paymentMethod === null || $existingMethod === $paymentMethod)) {
            return $existing;
        }

        $hasStripe = $secret !== '' && class_exists(\Stripe\StripeClient::class) && ! $useFake;

        // Fake 模式：生成模拟数据，用于本地开发（无 Stripe key）
        if ($useFake) {
            $fakeIntentId = 'pi_fake_' . Str::random(24);
            $fakeClientSecret = $fakeIntentId . '_secret_' . Str::random(24);

            return PaymentTransaction::create([
                'user_id'              => $subscription->user_id,
                'subscription_id'      => $subscription->id,
                'provider'             => 'stripe',
                'provider_session_id'  => $fakeIntentId,
                'provider_payment_intent_id' => $fakeIntentId,
                'status'               => PaymentTransaction::STATUS_PENDING,
                'amount_minor'         => $subscription->amount_minor,
                'currency'             => $subscription->currency,
                'raw_payload'          => [
                    'client_secret'       => $fakeClientSecret,
                    'payment_method'      => $paymentMethodTypes[0] ?? 'card',
                    'payment_method_types' => $paymentMethodTypes,
                    'mode'                => 'fake',
                    'is_fake'             => true,
                ],
            ]);
        }

        if (! $hasStripe) {
            throw new RuntimeException('Stripe is not configured. Set STRIPE_SECRET or enable STRIPE_FAKE for local dev.');
        }

        // 真实 Stripe API：创建 PaymentIntent
        try {
            /** @var \Stripe\StripeClient $stripe */
            $stripe = new \Stripe\StripeClient($secret);

            $payload = [
                'amount'               => (int) $subscription->amount_minor,
                'currency'             => strtolower((string) $subscription->currency),
                'payment_method_types' => $paymentMethodTypes,
                'metadata'             => [
                    'subscription_id' => (string) $subscription->id,
                    'user_id'         => (string) $subscription->user_id,
                    'mode'            => $mode,
                ],
            ];

            // 微信支付需要特殊配置
            if (in_array('wechat_pay', $paymentMethodTypes, true)) {
                $payload['payment_method_options'] = [
                    'wechat_pay' => ['client' => 'web'],
                ];
            }

            $intent = $stripe->paymentIntents->create($payload);

            return PaymentTransaction::create([
                'user_id'                    => $subscription->user_id,
                'subscription_id'            => $subscription->id,
                'provider'                   => 'stripe',
                'provider_session_id'        => $intent->id,
                'provider_payment_intent_id' => $intent->id,
                'status'                     => PaymentTransaction::STATUS_PENDING,
                'amount_minor'               => $subscription->amount_minor,
                'currency'                   => $subscription->currency,
                'raw_payload'                => [
                    'client_secret'        => $intent->client_secret,
                    'payment_method'       => $paymentMethodTypes[0] ?? 'card',
                    'payment_method_types' => $paymentMethodTypes,
                    'mode'                 => $mode,
                ],
            ]);
        } catch (\Throwable $e) {
            throw new RuntimeException('Stripe PaymentIntent creation failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Stripe webhook → checkout.session.completed（兼容旧版 Checkout Session）。
     */
    public function handleSuccess(string $sessionId, ?string $paymentIntentId = null): PaymentTransaction
    {
        $tx = PaymentTransaction::where('provider_session_id', $sessionId)->firstOrFail();
        if ($tx->status === PaymentTransaction::STATUS_SUCCESS) {
            return $tx; // 幂等
        }
        $tx->update([
            'status'                     => PaymentTransaction::STATUS_SUCCESS,
            'provider_payment_intent_id' => $paymentIntentId,
            'updated_at'                 => Carbon::now(),
        ]);
        if ($tx->subscription_id) {
            (new SubscriptionService())->activate((string) $tx->subscription_id, $paymentIntentId ?? '');
        }
        return $tx;
    }

    /**
     * Stripe webhook → checkout.session.completed 失败（兼容旧版）。
     */
    public function handleFailure(string $sessionId, ?string $reason = null): PaymentTransaction
    {
        $tx = PaymentTransaction::where('provider_session_id', $sessionId)->firstOrFail();
        if ($tx->status === PaymentTransaction::STATUS_SUCCESS) {
            return $tx;
        }
        $tx->update([
            'status'          => PaymentTransaction::STATUS_FAILED,
            'failure_message' => $reason,
            'updated_at'      => Carbon::now(),
        ]);
        return $tx;
    }

    /**
     * Stripe webhook → payment_intent.succeeded
     * PaymentIntent 支付成功（信用卡/微信/支付宝 均走此事件）。
     */
    public function handleSuccessByPaymentIntent(string $paymentIntentId): PaymentTransaction
    {
        $tx = PaymentTransaction::where('provider_payment_intent_id', $paymentIntentId)->firstOrFail();
        if ($tx->status === PaymentTransaction::STATUS_SUCCESS) {
            return $tx; // 幂等
        }
        $tx->update([
            'status'     => PaymentTransaction::STATUS_SUCCESS,
            'updated_at' => Carbon::now(),
        ]);
        if ($tx->subscription_id) {
            (new SubscriptionService())->activate((string) $tx->subscription_id, $paymentIntentId);
        }
        return $tx;
    }

    /**
     * Stripe webhook → payment_intent.payment_failed
     */
    public function handleFailureByPaymentIntent(string $paymentIntentId, ?string $reason = null): PaymentTransaction
    {
        $tx = PaymentTransaction::where('provider_payment_intent_id', $paymentIntentId)->firstOrFail();
        if ($tx->status === PaymentTransaction::STATUS_SUCCESS) {
            return $tx;
        }
        $tx->update([
            'status'          => PaymentTransaction::STATUS_FAILED,
            'failure_message' => $reason,
            'updated_at'      => Carbon::now(),
        ]);
        return $tx;
    }

    /**
     * 模拟支付成功（开发环境用）。
     */
    public function mockPaymentSuccess(string $transactionId): PaymentTransaction
    {
        if (app()->environment('production')) {
            throw new RuntimeException('Mock payment is forbidden in production.');
        }

        $tx = PaymentTransaction::findOrFail($transactionId);
        if ($tx->status === PaymentTransaction::STATUS_SUCCESS) {
            return $tx;
        }
        $tx->update([
            'status'     => PaymentTransaction::STATUS_SUCCESS,
            'updated_at' => Carbon::now(),
        ]);
        if ($tx->subscription_id) {
            (new SubscriptionService())->activate((string) $tx->subscription_id, 'mock_' . $transactionId);
        }
        return $tx;
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function paymentMethodOptions(): array
    {
        return array_map(
            fn (string $method): array => [
                'value' => $method,
                'label' => self::PAYMENT_METHOD_LABELS[$method] ?? $method,
            ],
            $this->configuredPaymentMethods(),
        );
    }

    public function configuredPaymentMethods(): array
    {
        return $this->normalizePaymentMethods(
            SystemConfigValue::field('payment', 'payment_methods', ['card']),
        );
    }

    public function stripePublishableKey(): string
    {
        $configured = (string) SystemConfigValue::field('payment', 'publishable_key', '');
        if ($configured !== '' && $configured !== '********') {
            return $configured;
        }
        return (string) config('services.stripe.publishable', '');
    }

    public function isFakeMode(): bool
    {
        return $this->useFakeCheckout();
    }

    public function getTransactionStatus(string $transactionId): ?PaymentTransaction
    {
        return PaymentTransaction::find($transactionId);
    }

    /**
     * 获取默认结算货币。
     */
    public function getDefaultCurrency(): string
    {
        $configured = (string) SystemConfigValue::field('payment', 'default_currency', '');
        if ($configured !== '') {
            return strtoupper($configured);
        }
        return 'USD';
    }

    private function paymentMethodsForCheckout(?string $selected): array
    {
        $configured = $this->configuredPaymentMethods();
        if ($this->useFakeCheckout() && ! in_array('mock', $configured, true)) {
            $configured[] = 'mock';
        }
        $selected = is_string($selected) ? trim($selected) : '';
        if ($selected === '') {
            return $configured;
        }
        if (! in_array($selected, $configured, true)) {
            throw new RuntimeException('Selected payment method is not enabled.');
        }
        return [$selected];
    }

    private function normalizePaymentMethods(mixed $methods): array
    {
        if (is_string($methods)) {
            $methods = array_map('trim', explode(',', $methods));
        }
        if (! is_array($methods)) {
            return ['card'];
        }
        $allowed = array_keys(self::PAYMENT_METHOD_LABELS);
        $normalized = [];
        foreach ($methods as $method) {
            $method = trim((string) $method);
            if ($method !== '' && in_array($method, $allowed, true)) {
                $normalized[] = $method;
            }
        }
        return array_values(array_unique($normalized)) ?: ['card'];
    }

    private function stripeSecret(): string
    {
        $configured = (string) SystemConfigValue::field('payment', 'secret_key', '');
        if ($configured !== '' && $configured !== '********') {
            return $configured;
        }
        return (string) config('services.stripe.secret', '');
    }

    private function useFakeCheckout(): bool
    {
        $configured = SystemConfigValue::field('payment', 'fake', null);
        if ($configured !== null && $configured !== '') {
            return filter_var($configured, FILTER_VALIDATE_BOOLEAN);
        }
        return (bool) config('services.stripe.fake', false);
    }
}