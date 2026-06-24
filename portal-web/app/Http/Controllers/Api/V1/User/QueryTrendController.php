<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Domain\ClickHouse\ClickHouseStatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * UI.md #14 — 会员首页最近7天查询趋势数据。
 */
final class QueryTrendController extends Controller
{
    public function __construct(
        private readonly ClickHouseStatsService $stats,
    ) {
    }

    /**
     * GET /api/v1/user/query-trend
     *
     * 返回最近 N 天的每日查询量趋势。
     * Query params:
     *   - profile_id: string (optional, defaults to current user's default profile)
     *   - days: int (optional, default 7, max 30)
     */
    public function index(Request $request): JsonResponse
    {
        $userId = (string) $request->user()->uid;
        $profileId = $request->query('profile_id');
        $days = (int) $request->query('days', 7);
        $days = min(max($days, 1), 30);

        try {
            $trend = $this->stats->dailyTrend($userId, $profileId, $days);

            return response()->json([
                'data' => $trend,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'data' => [],
                'message' => 'Failed to fetch query trend.',
            ], 200);
        }
    }
}
