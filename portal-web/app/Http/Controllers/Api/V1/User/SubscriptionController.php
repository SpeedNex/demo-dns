<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\User;

use App\Domain\Billing\PaymentService;
use App\Domain\Billing\SubscriptionService;
use App\Models\PaymentTransaction;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * 用户订阅控制器 — SaaS 订阅流程。
 *
 * 流程: plans → create subscription → checkout → mock payment success → active
 */
final class SubscriptionController
{
    /** GET /api/v1/user/plans — 套餐列表 */
    public function plans(): JsonResponse
    {
        $plans = DB::table('plans')
            ->orderBy('sort_order')
            ->get()
            ->keyBy('id');

        // 一次性加载所有 prices 和 features，按 plan_id 分组，消除 N+1
        $allPrices = DB::table('plan_prices')->get()->groupBy('plan_id');
        $allFeatures = DB::table('plan_features')->get()->groupBy('plan_id');

        $result = $plans->map(function ($plan) use ($allPrices, $allFeatures) {
            $prices = collect($allPrices->get($plan->id, []))
                ->map(fn ($p) => [
                    'id' => (int) $p->id,
                    'billing_cycle' => $p->billing_cycle,
                    'amount_minor' => (int) $p->amount_minor,
                    'currency' => $p->currency,
                ]);

            $features = collect($allFeatures->get($plan->id, []))
                ->pluck('feature_key')
                ->all();

            return [
                'id' => (int) $plan->id,
                'code' => $plan->code,
                'name' => $plan->name,
                'description' => $plan->description,
                'sort_order' => (int) ($plan->sort_order ?? 0),
                'prices' => $prices,
                'features' => $features,
            ];
        });

        return response()->json(['data' => $result]);
    }

    /** GET /api/v1/user/subscriptions — 用户订阅列表 */
    public function index(Request $request): JsonResponse
    {
        $userId = Auth::id();
        $subs = Subscription::where('user_id', $userId)
            ->orderByDesc('created_at')
            ->get()
            ->map(function (Subscription $sub): array {
                return [
                    'id' => $sub->id,
                    'subscription_no' => $sub->subscription_no,
                    'plan_code' => $sub->plan_code,
                    'billing_cycle' => $sub->billing_cycle,
                    'amount_minor' => $sub->amount_minor,
                    'currency' => $sub->currency,
                    'status' => $sub->status,
                    'quota_status' => $sub->quota_status,
                    'auto_renew' => $sub->auto_renew,
                    'cancel_at_period_end' => $sub->cancel_at_period_end,
                    'current_period_start' => $sub->current_period_start?->toIso8601String(),
                    'current_period_end' => $sub->current_period_end?->toIso8601String(),
                    'created_at' => $sub->created_at?->toIso8601String(),
                ];
            });

        return response()->json(['data' => $subs]);
    }

    /** POST /api/v1/user/subscriptions — 创建订阅 */
    public function create(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'plan_code' => 'required|string|max:50',
            'billing_cycle' => 'nullable|string|in:monthly,yearly',
        ]);

        $userId = Auth::id();
        $service = new SubscriptionService();
        $sub = $service->create(
            $userId,
            $validated['plan_code'],
            $validated['billing_cycle'] ?? 'monthly',
        );

        return response()->json([
            'data' => [
                'id' => $sub->id,
                'subscription_no' => $sub->subscription_no,
                'plan_code' => $sub->plan_code,
                'billing_cycle' => $sub->billing_cycle,
                'amount_minor' => $sub->amount_minor,
                'currency' => $sub->currency,
                'status' => $sub->status,
            ],
        ], 201);
    }

    /** GET /api/v1/user/subscriptions/{id} — 订阅详情 */
    public function show(string $id): JsonResponse
    {
        $sub = Subscription::findOrFail($id);
        $this->authorizeSubscription($sub);

        return response()->json([
            'data' => [
                'id' => $sub->id,
                'subscription_no' => $sub->subscription_no,
                'plan_code' => $sub->plan_code,
                'billing_cycle' => $sub->billing_cycle,
                'amount_minor' => $sub->amount_minor,
                'currency' => $sub->currency,
                'provider' => $sub->provider,
                'status' => $sub->status,
                'quota_status' => $sub->quota_status,
                'auto_renew' => $sub->auto_renew,
                'cancel_at_period_end' => $sub->cancel_at_period_end,
                'started_at' => $sub->started_at?->toIso8601String(),
                'current_period_start' => $sub->current_period_start?->toIso8601String(),
                'current_period_end' => $sub->current_period_end?->toIso8601String(),
                'cancelled_at' => $sub->cancelled_at?->toIso8601String(),
                'expired_at' => $sub->expired_at?->toIso8601String(),
                'meta' => $sub->meta,
                'created_at' => $sub->created_at?->toIso8601String(),
            ],
        ]);
    }

    /** POST /api/v1/user/subscriptions/{id}/checkout — 创建支付会话 */
    public function checkout(Request $request, string $id): JsonResponse
    {
        $sub = Subscription::findOrFail($id);
        $this->authorizeSubscription($sub);

        $paymentMethod = $request->input('payment_method');
        $paymentService = new PaymentService();

        $tx = $paymentService->createCheckout($sub, $paymentMethod);

        return response()->json([
            'data' => [
                'payment_transaction_id' => $tx->id,
                'provider_session_id' => $tx->provider_session_id,
                'redirect_url' => $tx->raw_payload['redirect_url'] ?? null,
                'status' => $tx->status,
            ],
        ]);
    }

    /** POST /api/v1/user/subscriptions/{id}/cancel — 取消订阅 */
    public function cancel(string $id): JsonResponse
    {
        $sub = Subscription::findOrFail($id);
        $this->authorizeSubscription($sub);

        $service = new SubscriptionService();
        $service->cancel((string) $sub->id);

        return response()->json([
            'data' => [
                'id' => $sub->id,
                'cancel_at_period_end' => true,
                'message' => '订阅将在当前周期结束后取消',
            ],
        ]);
    }

    /** POST /api/v1/user/subscriptions/{id}/resume — 恢复续费 */
    public function resume(string $id): JsonResponse
    {
        $sub = Subscription::findOrFail($id);
        $this->authorizeSubscription($sub);

        $service = new SubscriptionService();
        $service->resume((string) $sub->id);

        return response()->json([
            'data' => [
                'id' => $sub->id,
                'cancel_at_period_end' => false,
                'message' => '已恢复自动续费，订阅将在当前周期结束后自动续约',
            ],
        ]);
    }

    /** GET /api/v1/user/subscriptions/current — 当前订阅 */
    public function current(): JsonResponse
    {
        $userId = Auth::id();
        $service = new SubscriptionService();
        $sub = $service->getActive($userId);

        return response()->json(['data' => $sub]);
    }

    /** GET /api/v1/user/payment-transactions/{id}/status */
    public function paymentTransactionStatus(string $id): JsonResponse
    {
        $tx = PaymentTransaction::findOrFail($id);
        if ((string) $tx->user_id !== (string) Auth::id()) {
            abort(403);
        }

        return response()->json([
            'data' => [
                'id' => $tx->id,
                'status' => $tx->status,
                'provider_session_id' => $tx->provider_session_id,
                'amount_minor' => $tx->amount_minor,
                'currency' => $tx->currency,
                'failure_message' => $tx->failure_message,
                'created_at' => $tx->created_at?->toIso8601String(),
            ],
        ]);
    }

    /** POST /api/v1/user/payment-transactions/{id}/mock-success — 模拟支付成功 */
    public function mockPaymentSuccess(string $id): JsonResponse
    {
        $tx = PaymentTransaction::findOrFail($id);
        if ((string) $tx->user_id !== (string) Auth::id()) {
            abort(403);
        }

        $paymentService = new PaymentService();
        $tx = $paymentService->mockPaymentSuccess($id);

        return response()->json([
            'data' => [
                'id' => $tx->id,
                'status' => $tx->status,
                'message' => '支付成功（模拟）',
            ],
        ]);
    }

    /** GET /api/v1/user/stripe-config */
    public function stripeConfig(): JsonResponse
    {
        $paymentService = new PaymentService();

        return response()->json([
            'data' => [
                'publishable_key' => $paymentService->stripePublishableKey(),
                'is_fake' => $paymentService->isFakeMode(),
                'payment_methods' => $paymentService->configuredPaymentMethods(),
            ],
        ]);
    }

    private function authorizeSubscription(Subscription $sub): void
    {
        if ((string) $sub->user_id !== (string) Auth::id()) {
            abort(403);
        }
    }
}