<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Infrastructure\ClickHouse\ClickHouseClient;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * 2026-06-22: query_log_ingest_batches 表已删除，统计改为从 ClickHouse dns_logs 取。
 */
final class AdminBillingStatsController
{
    public function overview(): JsonResponse
    {
        $totalUsers = User::count();
        $clickhouse = new ClickHouseClient();

        // 从 ClickHouse dns_logs 获取统计
        $todayQueries = 0;
        $totalQueries = 0;
        $todayBatches = 0;
        $todayBlocked = 0;
        $totalBlocked = 0;
        try {
            $todayRow = $clickhouse->jsonSelect(
                "SELECT count() AS c FROM dns_logs WHERE event_time >= toDate(now())"
            );
            $todayQueries = (int) ($todayRow[0]['c'] ?? 0);

            $allRow = $clickhouse->jsonSelect("SELECT count() AS c FROM dns_logs");
            $totalQueries = (int) ($allRow[0]['c'] ?? 0);

            $todayBatchesRow = $clickhouse->jsonSelect(
                "SELECT uniqExact(node_id) AS n FROM dns_logs WHERE event_time >= toDate(now())"
            );
            $todayBatches = (int) ($todayBatchesRow[0]['n'] ?? 0);

            $todayBlockedRow = $clickhouse->jsonSelect(
                "SELECT count() AS c FROM dns_logs WHERE event_time >= toDate(now()) AND action = 'BLOCK'"
            );
            $todayBlocked = (int) ($todayBlockedRow[0]['c'] ?? 0);

            $totalBlockedRow = $clickhouse->jsonSelect(
                "SELECT count() AS c FROM dns_logs WHERE action = 'BLOCK'"
            );
            $totalBlocked = (int) ($totalBlockedRow[0]['c'] ?? 0);
        } catch (\Throwable) {
            // ClickHouse 不可用时静默返回 0
        }

        // 套餐分布（从用户 plan_code 统计）
        $planDistribution = User::selectRaw("COALESCE(plan_code, 'free') as plan, COUNT(*) as count")
            ->groupBy('plan')
            ->pluck('count', 'plan')
            ->toArray();

        $freeUsers = $planDistribution['free'] ?? 0;
        $proUsers = ($planDistribution['pro'] ?? 0) + ($planDistribution['pro_monthly'] ?? 0) + ($planDistribution['pro_yearly'] ?? 0);
        $businessUsers = $planDistribution['business'] ?? 0;

        return response()->json([
            'data' => [
                'intercepts' => [
                    'today_queries' => $todayQueries,
                    'today_blocked' => $todayBlocked,
                    'total_queries' => $totalQueries,
                    'total_blocked' => $totalBlocked,
                    'today_batches' => $todayBatches,
                ],
                'usage' => [
                    'total_users' => $totalUsers,
                    'free_users' => $freeUsers,
                    'pro_users' => $proUsers,
                    'business_users' => $businessUsers,
                ],
                'billing' => [
                    'total_revenue_minor' => 0,
                    'monthly_new_users' => User::where('created_at', '>=', now()->startOfMonth())->count(),
                ],
                'plans' => [
                    [
                        'code' => 'free',
                        'name' => 'Free',
                        'users' => $freeUsers,
                        'monthly_limit' => 300000,
                    ],
                    [
                        'code' => 'pro',
                        'name' => 'Pro',
                        'users' => $proUsers,
                        'monthly_limit' => null,
                    ],
                    [
                        'code' => 'business',
                        'name' => 'Business',
                        'users' => $businessUsers,
                        'monthly_limit' => null,
                    ],
                ],
            ],
        ]);
    }
}
