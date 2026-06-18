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
        ?string $idempotencyKey = null
    ): Order {
        // 幂等键：先查再写，避免双击产生双订单
        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            $existing = Order::where('user_id', $userId)
                ->where('idempotency_key', $idempotencyKey)
                ->first();
            if ($existing !== null) {
                return $existing;
            }
        }
        try {
            return Order::create([
                'user_id' => $userId,
                'order_no' => $this->generateOrderNo(),
                'idempotency_key' => $idempotencyKey,
                'plan_code' => $planCode,
                'status' => Order::STATUS_PENDING,
                'payable_amount_minor' => $payableAmountMinor,
                'currency' => $currency,
                'description' => $description,
                'meta' => $meta,
            ]);
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
            // 联动订阅：plan_code 立即生效，order_id 用于幂等回查
            (new SubscriptionService())->setPlan(
                userId: $order->user_id,
                planCode: $order->plan_code,
                monthlyLimit: null,
                orderId: (string) $order->id,
            );
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
}
