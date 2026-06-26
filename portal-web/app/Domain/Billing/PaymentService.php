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
 * V1: 仅支持 Stripe Checkout Session。
 * 流程: 创建 Subscription(pending) → createCheckout → Stripe 回调 →
 *       PaymentService.handleSuccess() → SubscriptionService.activate()。
 */
final class PaymentService
{
    private const PAYMENT_METHOD_LABELS = [
        'card' => '信用卡',
        'wechat_pay' => '微信支付',
        'alipay' => '支付宝',
    ];

    /**
     * 创建一个支付会话 (Stripe Checkout Session)。
     */
    public function createCheckout(Subscription $subscription, ?string $paymentMethod = null): PaymentTransaction
    {
        $paymentMethodTypes = $this->paymentMethodsForCheckout($paymentMethod);
        $secret = $this->stripeSecret();
        $appUrl = rtrim((string) config('app.url'), '/');
        $successUrl = $appUrl . '/user/subscription?status=success&sub_id=' . $subscription->id . '&session_id={CHECKOUT_SESSION_ID}';
        $cancelUrl  = $appUrl . '/user/subscription?status=cancel&sub_id=' . $subscription->id;
        $useFake = $this->useFakeCheckout();

        if (app()->environment('production') && $useFake) {
            throw new RuntimeException('Fake Stripe checkout is forbidden in production.');
        }

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
        if (! $hasStripe && ! $useFake) {
            throw new RuntimeException('Stripe is not configured. Set STRIPE_SECRET or enable STRIPE_FAKE for local dev.');
        }

        $sessionId = 'cs_test_' . Str::random(24);
        $redirectUrl = "https://checkout.stripe.com/c/pay/{$sessionId}";

        if ($hasStripe) {
            try {
                /** @var \Stripe\StripeClient $stripe */
                $stripe = new \Stripe\StripeClient($secret);
                $payload = [
                    'mode' => 'subscription',
                    'payment_method_types' => $paymentMethodTypes,
                    'line_items' => [[
                        'price_data' => [
                            'currency' => strtolower((string) $subscription->currency),
                            'unit_amount' => (int) $subscription->amount_minor,
                            'product_data' => ['name' => 'Subscription #' . $subscription->subscription_no],
                            'recurring' => ['interval' => $subscription->billing_cycle === 'yearly' ? 'year' : 'month'],
                        ],
                        'quantity' => 1,
                    ]],
                    'success_url' => $successUrl,
                    'cancel_url' => $cancelUrl,
                    'metadata' => [
                        'subscription_id' => (string) $subscription->id,
                        'user_id' => (string) $subscription->user_id,
                    ],
                ];

                if (in_array('wechat_pay', $paymentMethodTypes, true)) {
                    $payload['payment_method_options'] = [
                        'wechat_pay' => ['client' => 'web'],
                    ];
                }

                $session = $stripe->checkout->sessions->create($payload);
                $sessionId = $session->id;
                $redirectUrl = (string) $session->url;
            } catch (\Throwable $e) {
                throw new RuntimeException('Stripe checkout session creation failed: ' . $e->getMessage(), 0, $e);
            }
        }

        return PaymentTransaction::create([
            'user_id' => $subscription->user_id,
            'subscription_id' => $subscription->id,
            'provider' => 'stripe',
            'provider_session_id' => $sessionId,
            'status' => PaymentTransaction::STATUS_PENDING,
            'amount_minor' => $subscription->amount_minor,
            'currency' => $subscription->currency,
            'raw_payload' => [
                'redirect_url' => $redirectUrl,
                'payment_method' => $paymentMethodTypes[0] ?? 'card',
                'payment_method_types' => $paymentMethodTypes,
            ],
        ]);
    }

    /**
     * Stripe webhook → success
     */
    public function handleSuccess(string $sessionId, ?string $paymentIntentId = null): PaymentTransaction
    {
        $tx = PaymentTransaction::where('provider_session_id', $sessionId)->firstOrFail();
        if ($tx->status === PaymentTransaction::STATUS_SUCCESS) {
            return $tx; // 幂等
        }
        $tx->update([
            'status' => PaymentTransaction::STATUS_SUCCESS,
            'provider_payment_intent_id' => $paymentIntentId,
            'updated_at' => Carbon::now(),
        ]);
        if ($tx->subscription_id) {
            (new SubscriptionService())->activate((string) $tx->subscription_id, $paymentIntentId ?? '');
        }
        return $tx;
    }

    public function handleFailure(string $sessionId, ?string $reason = null): PaymentTransaction
    {
        $tx = PaymentTransaction::where('provider_session_id', $sessionId)->firstOrFail();
        if ($tx->status === PaymentTransaction::STATUS_SUCCESS) {
            return $tx;
        }
        $tx->update([
            'status' => PaymentTransaction::STATUS_FAILED,
            'failure_message' => $reason,
            'updated_at' => Carbon::now(),
        ]);
        return $tx;
    }

    /**
     * 模拟支付成功（开发环境用）。
     */
    public function mockPaymentSuccess(string $transactionId): PaymentTransaction
    {
        $tx = PaymentTransaction::findOrFail($transactionId);
        if ($tx->status === PaymentTransaction::STATUS_SUCCESS) {
            return $tx;
        }
        $tx->update([
            'status' => PaymentTransaction::STATUS_SUCCESS,
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

    private function paymentMethodsForCheckout(?string $selected): array
    {
        $configured = $this->configuredPaymentMethods();
        // Allow "mock" payment method when fake checkout is enabled (dev/test only)
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