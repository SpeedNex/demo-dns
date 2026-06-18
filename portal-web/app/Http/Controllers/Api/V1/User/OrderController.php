<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\User;

use App\Domain\Billing\OrderService;
use App\Domain\Billing\PaymentService;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * UI.md #51 #53 — 用户订单中心。
 *
 * 幂等：接受 Idempotency-Key 头 + body.idempotency_key。
 * 客户端双击/重试产生同一键 → 同一订单。
 */
final class OrderController
{
    public function __construct(
        private readonly OrderService $orders = new OrderService(),
        private readonly PaymentService $payments = new PaymentService(),
    ) {
    }

    /** POST /api/v1/user/orders — 创建订单（套餐购买）*/
    public function create(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'plan_code' => 'required|string|max:30',
            'payable_amount_minor' => 'required|integer|min:1',
            'currency' => 'sometimes|string|size:3',
            'description' => 'nullable|string|max:255',
            'idempotency_key' => 'nullable|string|max:80',
            'meta' => 'sometimes|array',
        ]);

        $userId = (string) $request->user()->id;
        // header 优先，其次 body（兼容旧调用）
        $idempotencyKey = (string) $request->header('Idempotency-Key', '') !== ''
            ? (string) $request->header('Idempotency-Key')
            : (string) ($validated['idempotency_key'] ?? '');

        $order = $this->orders->create(
            userId: $userId,
            planCode: $validated['plan_code'],
            payableAmountMinor: (int) $validated['payable_amount_minor'],
            currency: $validated['currency'] ?? 'USD',
            description: $validated['description'] ?? null,
            meta: $validated['meta'] ?? [],
            idempotencyKey: $idempotencyKey !== '' ? $idempotencyKey : null,
        );

        return response()->json(['data' => $this->format($order)], 201);
    }

    /** GET /api/v1/user/orders — 当前用户订单列表 */
    public function index(Request $request): JsonResponse
    {
        $userId = (string) $request->user()->id;
        $rows = Order::where('user_id', $userId)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();
        return response()->json(['data' => $rows->map(fn ($o) => $this->format($o))->all()]);
    }

    /** GET /api/v1/user/orders/{id} — 订单详情 */
    public function show(Request $request, string $id): JsonResponse
    {
        $order = Order::where('id', $id)
            ->where('user_id', (string) $request->user()->id)
            ->firstOrFail();
        return response()->json(['data' => $this->format($order)]);
    }

    /** POST /api/v1/user/orders/{id}/checkout — 创建支付会话 */
    public function checkout(Request $request, string $id): JsonResponse
    {
        $order = Order::where('id', $id)
            ->where('user_id', (string) $request->user()->id)
            ->firstOrFail();
        if ($order->status !== Order::STATUS_PENDING) {
            return response()->json(['message' => 'Order is not payable.'], 422);
        }
        $tx = $this->payments->createCheckout($order);
        $meta = $tx->meta ?? [];
        return response()->json([
            'data' => [
                'order_id' => (string) $order->id,
                'payment_transaction_id' => (string) $tx->id,
                'redirect_url' => $meta['redirect_url'] ?? null,
                'provider_session_id' => (string) $tx->provider_session_id,
            ],
        ], 201);
    }

    private function format(Order $order): array
    {
        return [
            'id' => (string) $order->id,
            'order_no' => $order->order_no,
            'plan_code' => $order->plan_code,
            'status' => $order->status,
            'payable_amount_minor' => (int) $order->payable_amount_minor,
            'currency' => $order->currency,
            'description' => $order->description,
            'paid_at' => $order->paid_at?->toIso8601String(),
            'cancelled_at' => $order->cancelled_at?->toIso8601String(),
            'created_at' => $order->created_at?->toIso8601String(),
        ];
    }
}
