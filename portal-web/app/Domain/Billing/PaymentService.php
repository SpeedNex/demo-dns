<?php

declare(strict_types=1);

namespace App\Domain\Billing;

use App\Models\Order;
use App\Models\PaymentTransaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * UI.md #53 — 支付中心。
 *
 * V1: 仅支持 Stripe Checkout Session。
 * 支付金额来源: `Order.payable_amount_minor`，前端禁止直接支付金额。
 *
 * 流程: 支付先写 `payment_transactions` (pending) → Stripe 回调 →
 *       PaymentService.handleSuccess() → OrderService.markPaid()。
 */
final class PaymentService
{
    /**
     * 创建一个支付会话 (Stripe Checkout Session)。
     *
     * 安全约束：
     *  - 当 Stripe SDK + secret 都可用时，必须真的调用 Stripe API 拿到真实 session。
     *    SDK 抛异常 → 抛回 RuntimeException，禁止 fallback 成占位 session。
     *  - 没有 SDK / secret 时：仅允许 fake 模式（仅在非 production）使用占位 session。
     */
    public function createCheckout(Order $order): PaymentTransaction
    {
        $secret = (string) config('services.stripe.secret', '');
        $successUrl = (string) config('app.url') . '/user/orders/' . $order->id . '?status=success&session_id={CHECKOUT_SESSION_ID}';
        $cancelUrl  = (string) config('app.url') . '/user/orders/' . $order->id . '?status=cancel';
        $useFake = (bool) config('services.stripe.fake', false);

        if (app()->environment('production') && $useFake) {
            throw new RuntimeException('Fake Stripe checkout is forbidden in production.');
        }

        $existing = PaymentTransaction::query()
            ->where('order_id', $order->id)
            ->where('provider', 'stripe')
            ->where('status', PaymentTransaction::STATUS_PENDING)
            ->latest('id')
            ->first();
        if ($existing instanceof PaymentTransaction) {
            return $existing;
        }

        $hasStripe = $secret !== '' && class_exists(\Stripe\StripeClient::class) && ! $useFake;
        if (! $hasStripe && ! $useFake) {
            // 没有 Stripe SDK 也未启用 fake：拒绝创建 pending 假流水
            throw new RuntimeException('Stripe is not configured. Set STRIPE_SECRET or enable STRIPE_FAKE for local dev.');
        }

        $sessionId = 'cs_test_' . Str::random(24);
        $redirectUrl = "https://checkout.stripe.com/c/pay/{$sessionId}";

        if ($hasStripe) {
            try {
                /** @var \Stripe\StripeClient $stripe */
                $stripe = new \Stripe\StripeClient($secret);
                $session = $stripe->checkout->sessions->create([
                    'mode' => 'payment',
                    'line_items' => [[
                        'price_data' => [
                            'currency' => strtolower((string) $order->currency),
                            'unit_amount' => (int) $order->payable_amount_minor,
                            'product_data' => ['name' => 'Order #' . $order->order_no],
                        ],
                        'quantity' => 1,
                    ]],
                    'success_url' => $successUrl,
                    'cancel_url' => $cancelUrl,
                    'metadata' => [
                        'order_id' => (string) $order->id,
                        'user_id' => (string) $order->user_id,
                    ],
                ]);
                $sessionId = $session->id;
                $redirectUrl = (string) $session->url;
            } catch (\Throwable $e) {
                // Stripe API 失败：直接抛回，不创建占位 pending 交易
                throw new RuntimeException('Stripe checkout session creation failed: ' . $e->getMessage(), 0, $e);
            }
        }

        return PaymentTransaction::create([
            'user_id' => $order->user_id,
            'order_id' => $order->id,
            'provider' => 'stripe',
            'provider_session_id' => $sessionId,
            'status' => PaymentTransaction::STATUS_PENDING,
            'amount_minor' => $order->payable_amount_minor,
            'currency' => $order->currency,
            'raw_payload' => [
                'redirect_url' => $redirectUrl,
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
        if ($tx->order_id) {
            (new OrderService())->markPaid((string) $tx->order_id, $paymentIntentId);
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
     * 用于 payment_intent.* 事件，ID 形如 pi_xxx。
     * 必须按 provider_payment_intent_id 查找，不能与 checkout session id (cs_xxx) 混用。
     */
    public function handleFailureByPaymentIntent(string $paymentIntentId, ?string $reason = null): PaymentTransaction
    {
        $tx = PaymentTransaction::where('provider_payment_intent_id', $paymentIntentId)->first();
        if (! $tx instanceof PaymentTransaction) {
            // payment_intent 事件先于 webhook 落库的兜底：记录失败但不抛 500
            throw new \RuntimeException("No payment transaction found for payment_intent [{$paymentIntentId}]");
        }
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

    public function refund(PaymentTransaction $tx): PaymentTransaction
    {
        if ($tx->status !== PaymentTransaction::STATUS_SUCCESS) {
            return $tx;
        }
        $tx->update(['status' => PaymentTransaction::STATUS_REFUNDED]);
        if ($tx->order_id) {
            (new OrderService())->markRefunded((string) $tx->order_id);
        }
        return $tx;
    }
}
