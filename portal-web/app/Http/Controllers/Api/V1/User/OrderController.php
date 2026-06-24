<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\User;

use App\Domain\Billing\OrderService;
use App\Domain\Billing\PaymentService;
use App\Domain\Billing\PlanCatalogService;
use App\Domain\Billing\WalletService;
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
        private readonly PlanCatalogService $plans = new PlanCatalogService(),
        private readonly WalletService $wallets = new WalletService(),
    ) {
    }

    /** GET /api/v1/user/plans — 当前可购买的套餐列表（用于购买入口）*/
    public function plans(): JsonResponse
    {
        return response()->json(['data' => $this->plans->memberList()]);
    }

    /** POST /api/v1/user/orders — 创建订单（套餐购买）*/
    public function create(Request $request): JsonResponse
    {
        // 安全约束：仅允许前端传 plan_code + billing_cycle；金额/币种由后端从 plan_prices 查表得到
        $validated = $request->validate([
            'plan_code' => 'required|string|max:30',
            'description' => 'nullable|string|max:255',
            'billing_cycle' => 'nullable|string|in:monthly,yearly',
            'idempotency_key' => 'nullable|string|max:80',
            'meta' => 'sometimes|array',
        ]);

        $userId = (string) $request->user()->uid;
        $idempotencyKey = (string) $request->header('Idempotency-Key', '') !== ''
            ? (string) $request->header('Idempotency-Key')
            : (string) ($validated['idempotency_key'] ?? '');

        $plan = \App\Models\Plan::where('code', $validated['plan_code'])->first();

        // billing_cycle 可选：如果前端没传，自动取第一个有金额的价格
        $billingCycle = $validated['billing_cycle'] ?? null;
        $price = null;

        if ($billingCycle && $plan) {
            $price = $plan
                ->prices()
                ->where('billing_cycle', $billingCycle)
                ->where('status', 'active')
                ->first();
        }

        // 如果没有找到价格，取第一个有金额的有效价格
        if ($price === null && $plan) {
            $price = $plan
                ->prices()
                ->where('status', 'active')
                ->where('amount_minor', '>', 0)
                ->first();

            // 如果还是没有，取第一个有效价格
            if ($price === null) {
                $price = $plan->prices()->where('status', 'active')->first();
            }

            $billingCycle = $price?->billing_cycle ?? 'monthly';
        }

        if ($price === null) {
            return response()->json([
                'message' => "No active price for plan [{$validated['plan_code']}].",
            ], 422);
        }

        $payableAmountMinor = (int) $price->amount_minor;
        $currency = $price->currency ?? 'USD';

        $order = $this->orders->create(
            userId: $userId,
            planCode: $validated['plan_code'],
            payableAmountMinor: $payableAmountMinor,
            currency: $currency,
            description: $validated['description'] ?? null,
            meta: array_merge($validated['meta'] ?? [], ['billing_cycle' => $billingCycle]),
            idempotencyKey: $idempotencyKey !== '' ? $idempotencyKey : null,
            planId: $plan?->id,
            planPriceId: $price->id,
        );

        return response()->json(['data' => $this->format($order)], 201);
    }

    /** GET /api/v1/user/orders — 当前用户订单列表 */
    public function index(Request $request): JsonResponse
    {
        $userId = (string) $request->user()->uid;
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
            ->where('user_id', (string) $request->user()->uid)
            ->firstOrFail();
        return response()->json(['data' => $this->format($order)]);
    }

    /** POST /api/v1/user/orders/{id}/checkout — 创建支付会话 */
    public function checkout(Request $request, string $id): JsonResponse
    {
        $order = Order::where('id', $id)
            ->where('user_id', (string) $request->user()->uid)
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

    /** POST /api/v1/user/orders/{id}/pay-with-wallet — 使用余额支付订单 */
    public function payWithWallet(Request $request, string $id): JsonResponse
    {
        $userId = (string) $request->user()->uid;

        $order = Order::where('id', $id)
            ->where('user_id', $userId)
            ->firstOrFail();
        if ($order->status !== Order::STATUS_PENDING) {
            return response()->json(['message' => 'Order is not payable.'], 422);
        }

        $wallet = $this->wallets->balance($userId);
        $orderAmount = (int) $order->payable_amount_minor;

        if ($wallet['balance_minor'] < $orderAmount) {
            return response()->json([
                'message' => 'Insufficient balance.',
                'errors' => [
                    'balance' => ['Insufficient balance. Please recharge your wallet.'],
                ],
            ], 422);
        }

        try {
            $idempotencyKey = 'wallet_pay:' . $order->id . ':' . now()->format('YmdHisv');
            $this->wallets->debit(
                userId: $userId,
                amountMinor: $orderAmount,
                source: 'order_payment',
                idempotencyKey: $idempotencyKey,
                description: 'Payment for order ' . $order->order_no,
            );

            $this->orders->markPaid((string) $order->id, 'wallet');

            $updatedOrder = $order->fresh();
            return response()->json(['data' => $this->format($updatedOrder)]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    private function format(Order $order): array
    {
        return [
            'id' => (string) $order->id,
            'order_no' => $order->order_no,
            'plan_code' => $order->plan_code_snapshot,
            'status' => $order->status,
            'payable_amount_minor' => (int) $order->payable_amount_minor,
            'currency' => $order->currency,
            'billing_cycle' => $order->billing_cycle,
            'description' => data_get($order->meta ?? [], 'description'),
            'paid_at' => $order->paid_at?->toIso8601String(),
            'cancelled_at' => $order->cancelled_at?->toIso8601String(),
            'created_at' => $order->created_at?->toIso8601String(),
        ];
    }
}
