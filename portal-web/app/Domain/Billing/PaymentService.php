<?php

declare(strict_types=1);

namespace App\Domain\Billing;

use App\Models\Order;
use App\Models\PaymentTransaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

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
     * 失败 / 无 SDK / 无 secret 时降级为占位 session（本地/测试场景）。
     */
    public function createCheckout(Order $order): PaymentTransaction
    {
        $secret = (string) config('services.stripe.secret', '');
        $successUrl = (string) config('app.url') . '/user/orders/' . $order->id . '?status=success&session_id={CHECKOUT_SESSION_ID}';
        $cancelUrl  = (string) config('app.url') . '/user/orders/' . $order->id . '?status=cancel';

        // 默认：占位 session（无 Stripe 凭证时降级）
        $sessionId = 'cs_test_' . Str::random(24);
        $redirectUrl = "https://checkout.stripe.com/c/pay/{$sessionId}";

        if ($secret !== '' && class_exists(\Stripe\StripeClient::class)) {
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
                $redirectUrl = $cancelUrl . '&stripe_error=1';
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
            'meta' => [
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
            'completed_at' => Carbon::now(),
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
            'completed_at' => Carbon::now(),
            'meta' => array_merge($tx->meta ?? [], ['failure_reason' => $reason]),
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
