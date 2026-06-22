<?php

declare(strict_types=1);

namespace App\Domain\Billing;

use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * UI.md #51 — 订单中心。
 *
 * 状态机：pending → paid → cancelled → refunded
 * 业务层禁止直接修改 status，必须走 OrderService。
 *
 * 幂等性：
 *  - markPaid() 重复调用不会产生副作用。
 *  - 已 paid 订单再调 markPaid() 直接返回原订单（不重复开订阅）。
 *  - cancelled / refunded 订单禁止回到 paid（抛 DomainException）。
 */
final class OrderService
{
    public function create(
        string $userId,
        string $planCode,
        int $payableAmountMinor,
        string $currency = 'USD',
        ?string $description = null,
        array $meta = [],
        ?string $idempotencyKey = null,
        ?int $planId = null,
        ?int $planPriceId = null
    ): Order {
        // 幂等键：未传入时按 user+plan+amount+ts 自动生成
        $idempotencyKey = $idempotencyKey ?: sprintf(
            '%s:%s:%d:%s',
            $userId,
            $planCode,
            $payableAmountMinor,
            now()->format('YmdHis')
        );

        // 幂等键：先查再写，避免双击产生双订单
        $existing = Order::where('user_id', $userId)
            ->where('idempotency_key', $idempotencyKey)
            ->first();
        if ($existing !== null) {
            return $existing;
        }
        try {
            $order = Order::create([
                'user_id' => $userId,
                'order_no' => $this->generateOrderNo(),
                'idempotency_key' => $idempotencyKey,
                'plan_id' => $planId,
                'plan_price_id' => $planPriceId,
                'plan_code_snapshot' => $planCode,
                'billing_cycle' => $meta['billing_cycle'] ?? 'monthly',
                'status' => Order::STATUS_PENDING,
                'original_amount_minor' => $payableAmountMinor,
                'payable_amount_minor' => $payableAmountMinor,
                'currency' => $currency,
                'meta' => array_merge($meta, ['description' => $description]),
            ]);

            // 同步生成账单（pending 状态）— 用户在账单页支付
            $this->ensureBillForOrder($order);

            return $order;
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            // 唯一约束兜底：并发双击场景
            $order = Order::where('user_id', $userId)
                ->where('idempotency_key', $idempotencyKey)
                ->first();
            if ($order !== null) {
                return $order;
            }
            throw $e;
        }
    }

    public function markPaid(string $orderId, ?string $paymentRef = null): Order
    {
        $order = null;
        DB::transaction(function () use ($orderId, $paymentRef, &$order) {
            $order = Order::query()->whereKey($orderId)->lockForUpdate()->firstOrFail();
            if ($order->status === Order::STATUS_PAID) {
                return;
            }
            if (in_array($order->status, [Order::STATUS_CANCELLED, Order::STATUS_REFUNDED], true)) {
                throw new \DomainException(sprintf(
                    'Order %s cannot transition to paid from status=%s',
                    $orderId,
                    $order->status
                ));
            }
            if ($order->status !== Order::STATUS_PENDING) {
                throw new \DomainException(sprintf(
                    'Order %s expected status=pending, got %s',
                    $orderId,
                    $order->status
                ));
            }

            $order->update([
                'status' => Order::STATUS_PAID,
                'paid_at' => now(),
                'meta' => array_merge($order->meta ?? [], ['payment_ref' => $paymentRef]),
            ]);
            $planCode = (string) $order->plan_code_snapshot;
            $description = (string) data_get($order->meta ?? [], 'description', '');
            if ($planCode === 'wallet_topup') {
                (new BillingService())->charge(
                    userId: $order->user_id,
                    amountMinor: (int) $order->payable_amount_minor,
                    description: $description !== '' ? $description : 'Wallet recharge',
                );
            } else {
                // 联动订阅：plan_code 立即生效，order_id 用于幂等回查
                (new SubscriptionService())->setPlan(
                    userId: $order->user_id,
                    planCode: $planCode,
                    monthlyLimit: null,
                    orderId: (string) $order->id,
                );
            }
        });
        return $order->fresh();
    }

    public function cancel(string $orderId, ?string $reason = null): Order
    {
        $order = Order::findOrFail($orderId);
        if ($order->status !== Order::STATUS_PENDING) {
            return $order;
        }
        $order->update([
            'status' => Order::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'meta' => array_merge($order->meta ?? [], ['cancel_reason' => $reason]),
        ]);
        return $order;
    }

    public function markRefunded(string $orderId): Order
    {
        $order = Order::findOrFail($orderId);
        if ($order->status !== Order::STATUS_PAID) {
            return $order;
        }
        DB::transaction(function () use ($order) {
            $order->update(['status' => Order::STATUS_REFUNDED]);
            // 退款：进入 past_due 等待用户续费（保留 grace 期）
            (new SubscriptionService())->markPastDue($order->user_id, 7);
        });
        return $order;
    }

    private function generateOrderNo(): string
    {
        return 'OD' . now()->format('YmdHis') . strtoupper(Str::random(6));
    }

    /**
     * 订单创建后同步生成账单（status=pending）。
     * 幂等：同一 order_id 已有账单时不再创建。
     */
    private function ensureBillForOrder(Order $order): void
    {
        $existing = DB::table('billings')
            ->where('user_id', (string) $order->user_id)
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(meta, '$.order_id')) = ?", [(string) $order->id])
            ->first();
        if ($existing !== null) {
            return;
        }

        $billingNo = 'BIL-' . now()->format('YmdHis') . '-' . str_pad((string) $order->id, 6, '0', STR_PAD_LEFT);
        $now = now();
        DB::table('billings')->insert([
            'billing_no' => $billingNo,
            'user_id' => (string) $order->user_id,
            'currency' => $order->currency,
            'subtotal_minor' => (int) $order->payable_amount_minor,
            'discount_minor' => 0,
            'tax_minor' => 0,
            'total_minor' => (int) $order->payable_amount_minor,
            'status' => 'pending',
            'issued_at' => $now,
            'due_at' => $now->copy()->addDays(7),
            'meta' => json_encode([
                'kind' => 'order',
                'order_id' => (string) $order->id,
                'order_no' => $order->order_no,
                'plan_code' => $order->plan_code_snapshot,
                'billing_cycle' => $order->billing_cycle,
                'description' => data_get($order->meta ?? [], 'description'),
            ], JSON_UNESCAPED_UNICODE),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
