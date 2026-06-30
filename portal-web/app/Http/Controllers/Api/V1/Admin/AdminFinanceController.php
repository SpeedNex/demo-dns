<?php

namespace App\Http\Controllers\Api\V1\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Admin Finance controller — SaaS 订阅模式。
 * 仅保留: bills, subscriptions, payment-flows
 */
final class AdminFinanceController
{
    /** GET /admin/finance/bills */
    public function bills(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'nullable|string',
            'status' => 'nullable|string',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $service = new \App\Domain\Billing\BillingService();
        $result = $service->bills(
            $validated['user_id'] ?? '',
            (int) ($validated['page'] ?? 1),
            (int) ($validated['per_page'] ?? 20),
            $validated['status'] ?? '',
        );

        return response()->json($result);
    }

    /** GET /admin/finance/bills/export */
    public function billExport(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'nullable|string',
            'status' => 'nullable|string',
            'limit' => 'nullable|integer|min:1|max:1000',
        ]);

        $service = new \App\Domain\Billing\BillingService();
        $result = $service->bills($validated['user_id'] ?? '', 1, (int) ($validated['limit'] ?? 1000), $validated['status'] ?? '');

        return response()->json(['data' => $result['data'] ?? []]);
    }

    /** GET /admin/finance/subscriptions */
    public function subscriptions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'nullable|string',
            'plan_code' => 'nullable|string|max:50',
            'status' => 'nullable|string|max:30',
            'quota_status' => 'nullable|string|max:30',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 20);

        $query = DB::table('subscriptions as s')
            ->leftJoin('users as u', 'u.uid', '=', 's.user_id')
            ->orderByDesc('s.created_at')
            ->select([
                's.*',
                'u.username as user_name',
                'u.email as user_email',
            ]);

        if (! empty($validated['user_id'])) {
            $query->where('s.user_id', $validated['user_id']);
        }
        if (! empty($validated['plan_code'])) {
            $query->where('s.plan_code', $validated['plan_code']);
        }
        if (! empty($validated['status'])) {
            $query->where('s.status', $validated['status']);
        }
        if (! empty($validated['quota_status'])) {
            $query->where('s.quota_status', $validated['quota_status']);
        }

        $total = (clone $query)->count();
        $items = $query->forPage($page, $perPage)->get()->map(function ($row): array {
            return [
                'id' => (int) $row->id,
                'subscription_no' => $row->subscription_no,
                'user_id' => (int) $row->user_id,
                'user_name' => $row->user_name,
                'user_email' => $row->user_email,
                'plan_id' => $row->plan_id !== null ? (int) $row->plan_id : null,
                'plan_code' => $row->plan_code,
                'billing_cycle' => $row->billing_cycle,
                'amount_minor' => (int) ($row->amount_minor ?? 0),
                'currency' => $row->currency,
                'status' => $row->status,
                'quota_status' => $row->quota_status,
                'auto_renew' => (bool) $row->auto_renew,
                'cancel_at_period_end' => (bool) $row->cancel_at_period_end,
                'started_at' => $row->started_at,
                'current_period_start' => $row->current_period_start,
                'current_period_end' => $row->current_period_end,
                'cancelled_at' => $row->cancelled_at,
                'expired_at' => $row->expired_at,
                'created_at' => $row->created_at,
            ];
        })->all();

        return response()->json([
            'data' => $items,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
            ],
        ]);
    }

    /** GET /admin/finance/subscriptions/{id} */
    public function subscriptionDetail(string $id): JsonResponse
    {
        $row = DB::table('subscriptions as s')
            ->leftJoin('users as u', 'u.uid', '=', 's.user_id')
            ->where('s.id', $id)
            ->select(['s.*', 'u.username as user_name', 'u.email as user_email'])
            ->first();

        if ($row === null) {
            abort(404, 'Subscription not found.');
        }

        return response()->json([
            'data' => [
                'id' => (int) $row->id,
                'subscription_no' => $row->subscription_no,
                'user_id' => (int) $row->user_id,
                'user_name' => $row->user_name,
                'user_email' => $row->user_email,
                'plan_id' => $row->plan_id !== null ? (int) $row->plan_id : null,
                'plan_code' => $row->plan_code,
                'billing_cycle' => $row->billing_cycle,
                'amount_minor' => (int) ($row->amount_minor ?? 0),
                'currency' => $row->currency,
                'provider' => $row->provider,
                'status' => $row->status,
                'quota_status' => $row->quota_status,
                'auto_renew' => (bool) $row->auto_renew,
                'cancel_at_period_end' => (bool) $row->cancel_at_period_end,
                'started_at' => $row->started_at,
                'current_period_start' => $row->current_period_start,
                'current_period_end' => $row->current_period_end,
                'cancelled_at' => $row->cancelled_at,
                'expired_at' => $row->expired_at,
                'meta' => json_decode((string) ($row->meta ?? '{}'), true),
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ],
        ]);
    }

    /** GET /admin/finance/payment-flows */
    public function paymentFlows(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'nullable|string',
            'status' => 'nullable|string',
            'type' => 'nullable|string|in:payment,refund',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 20);

        $query = DB::table('payment_transactions as pt')
            ->leftJoin('users as u', 'u.uid', '=', 'pt.user_id')
            ->leftJoin('subscriptions as s', 's.id', '=', 'pt.subscription_id')
            ->orderByDesc('pt.created_at')
            ->select([
                'pt.*',
                'u.username as user_name',
                'u.email as user_email',
                's.subscription_no',
                's.plan_code',
            ]);

        if (! empty($validated['user_id'])) {
            $query->where('pt.user_id', $validated['user_id']);
        }
        if (! empty($validated['status'])) {
            $query->where('pt.status', $validated['status']);
        }

        $total = (clone $query)->count();
        $items = $query->forPage($page, $perPage)->get()->map(function ($row): array {
            $rawPayload = json_decode((string) ($row->raw_payload ?? '{}'), true);
            $paymentMethod = $rawPayload['payment_method'] ?? null;
            $type = in_array($row->status, ['refunded']) ? 'refund' : 'payment';

            return [
                'id' => (int) $row->id,
                'user_id' => (int) $row->user_id,
                'user_name' => $row->user_name,
                'user_email' => $row->user_email,
                'subscription_id' => (int) $row->subscription_id,
                'subscription_no' => $row->subscription_no,
                'plan_code' => $row->plan_code,
                'provider' => $row->provider,
                'provider_session_id' => $row->provider_session_id,
                'provider_payment_intent_id' => $row->provider_payment_intent_id,
                'amount_minor' => (int) $row->amount_minor,
                'currency' => $row->currency,
                'status' => $row->status,
                'type' => $type,
                'payment_method' => $paymentMethod,
                'failure_code' => $row->failure_code,
                'failure_message' => $row->failure_message,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ];
        })->all();

        return response()->json([
            'data' => $items,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
            ],
        ]);
    }

    /** GET /admin/finance/payment-flows/export */
    public function paymentFlowExport(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'nullable|string',
            'status' => 'nullable|string',
            'limit' => 'nullable|integer|min:1|max:1000',
        ]);

        $query = DB::table('payment_transactions as pt')
            ->leftJoin('users as u', 'u.uid', '=', 'pt.user_id')
            ->leftJoin('subscriptions as s', 's.id', '=', 'pt.subscription_id')
            ->orderByDesc('pt.created_at')
            ->select([
                'pt.*',
                'u.username as user_name',
                'u.email as user_email',
                's.subscription_no',
                's.plan_code',
            ]);

        if (! empty($validated['user_id'])) {
            $query->where('pt.user_id', $validated['user_id']);
        }
        if (! empty($validated['status'])) {
            $query->where('pt.status', $validated['status']);
        }

        $items = $query->limit((int) ($validated['limit'] ?? 1000))->get()->map(function ($row): array {
            $type = in_array($row->status, ['refunded']) ? 'refund' : 'payment';
            return [
                'id' => (int) $row->id,
                'user_id' => (int) $row->user_id,
                'user_name' => $row->user_name,
                'user_email' => $row->user_email,
                'subscription_no' => $row->subscription_no,
                'plan_code' => $row->plan_code,
                'provider' => $row->provider,
                'amount_minor' => (int) $row->amount_minor,
                'currency' => $row->currency,
                'status' => $row->status,
                'type' => $type,
                'created_at' => $row->created_at,
            ];
        })->all();

        return response()->json(['data' => $items]);
    }

    /** POST /admin/finance/subscriptions/{id}/cancel */
    public function subscriptionCancel(string $id): JsonResponse
    {
        $sub = \App\Models\Subscription::findOrFail($id);

        $sub->update([
            'cancel_at_period_end' => true,
            'updated_at' => now(),
        ]);

        return response()->json([
            'data' => [
                'id' => $sub->id,
                'cancel_at_period_end' => true,
                'message' => '订阅已取消，当前周期结束后自动降级',
            ],
        ]);
    }

    /** POST /admin/finance/subscriptions/{id}/resume */
    public function subscriptionResume(string $id): JsonResponse
    {
        $sub = \App\Models\Subscription::findOrFail($id);

        $sub->update([
            'cancel_at_period_end' => false,
            'updated_at' => now(),
        ]);

        return response()->json([
            'data' => [
                'id' => $sub->id,
                'cancel_at_period_end' => false,
                'message' => '订阅已恢复自动续费',
            ],
        ]);
    }

    /**
     * 2026-06-30: 支付流水汇总（变化列表数据源）
     * GET /admin/finance/payment-flows/summary
     * 字段：
     *   today / this_month: KPI（金额、笔数、成功率、退款率）
     *   trend_7d: 最近 7 天每日成功/失败/退款金额
     */
    public function paymentFlowsSummary(Request $request): JsonResponse
    {
        $today = now()->startOfDay();
        $monthStart = now()->startOfMonth();
        $currency = strtoupper((string) $request->input('currency', 'USD'));
        $range = (int) $request->input('range', 7);
        $range = max(1, min(90, $range));

        $base = function () use ($currency) {
            $q = DB::table('payment_transactions');
            if ($currency !== '') {
                $q->where('currency', $currency);
            }
            return $q;
        };

        // 今日 KPI
        $todayTotal = (clone $base())->where('created_at', '>=', $today)->count();
        $todaySucceeded = (clone $base())->where('created_at', '>=', $today)->where('status', 'succeeded')->count();
        $todaySucceededAmount = (int) (clone $base())->where('created_at', '>=', $today)->where('status', 'succeeded')->sum('amount_minor');
        $todayRefunded = (clone $base())->where('created_at', '>=', $today)->where('status', 'refunded')->count();

        // 本月 KPI
        $monthTotal = (clone $base())->where('created_at', '>=', $monthStart)->count();
        $monthSucceeded = (clone $base())->where('created_at', '>=', $monthStart)->where('status', 'succeeded')->count();
        $monthSucceededAmount = (int) (clone $base())->where('created_at', '>=', $monthStart)->where('status', 'succeeded')->sum('amount_minor');
        $monthRefunded = (clone $base())->where('created_at', '>=', $monthStart)->where('status', 'refunded')->count();

        // 最近 N 天趋势
        $trendStart = now()->startOfDay()->subDays($range - 1);
        $rows = (clone $base())
            ->where('created_at', '>=', $trendStart)
            ->select([
                DB::raw('DATE(created_at) as d'),
                'status',
                DB::raw('SUM(amount_minor) as amt'),
                DB::raw('COUNT(*) as cnt'),
            ])
            ->groupBy('d', 'status')
            ->get();

        $buckets = [];
        for ($i = 0; $i < $range; $i++) {
            $key = now()->startOfDay()->subDays($range - 1 - $i)->toDateString();
            $buckets[$key] = ['date' => $key, 'succeeded_amount' => 0, 'failed_amount' => 0, 'refunded_amount' => 0, 'succeeded_count' => 0, 'failed_count' => 0];
        }
        foreach ($rows as $r) {
            $d = (string) $r->d;
            if (! isset($buckets[$d])) {
                continue;
            }
            $amt = (int) $r->amt;
            $cnt = (int) $r->cnt;
            if ($r->status === 'succeeded') {
                $buckets[$d]['succeeded_amount'] += $amt;
                $buckets[$d]['succeeded_count'] += $cnt;
            } elseif ($r->status === 'refunded') {
                $buckets[$d]['refunded_amount'] += $amt;
            } elseif (in_array($r->status, ['failed'], true)) {
                $buckets[$d]['failed_amount'] += $amt;
                $buckets[$d]['failed_count'] += $cnt;
            }
        }

        return response()->json([
            'data' => [
                'currency' => $currency,
                'range' => $range,
                'today' => [
                    'amount_minor' => $todaySucceededAmount,
                    'total_count' => $todayTotal,
                    'succeeded_count' => $todaySucceeded,
                    'success_rate' => $todayTotal > 0 ? round($todaySucceeded / $todayTotal, 4) : 0.0,
                    'refund_count' => $todayRefunded,
                ],
                'this_month' => [
                    'amount_minor' => $monthSucceededAmount,
                    'total_count' => $monthTotal,
                    'succeeded_count' => $monthSucceeded,
                    'success_rate' => $monthTotal > 0 ? round($monthSucceeded / $monthTotal, 4) : 0.0,
                    'refund_count' => $monthRefunded,
                    'refund_rate' => $monthSucceeded > 0 ? round($monthRefunded / $monthSucceeded, 4) : 0.0,
                ],
                'trend' => array_values($buckets),
            ],
        ]);
    }
}