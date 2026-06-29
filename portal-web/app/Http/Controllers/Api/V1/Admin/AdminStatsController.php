<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\System\HealthCheckService;
use App\Domain\User\UserService;
use App\Infrastructure\ClickHouse\ClickHouseClient;
use App\Models\ProfileVersion;
use App\Models\Node;
use App\Models\PublishTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Redis;

/**
 * 2026-06-22: query_log_ingest_batches 表已删除，查询统计改为从 ClickHouse dns_logs 取。
 */
final class AdminStatsController
{
    public function overview(): JsonResponse
    {
        $totalNodes = Node::count();
        $notInstalledNodes = (int) Node::where('install_status', '!=', 'installed')->count();

        // 节点在线计数使用 Redis（实时真相源，TTL 90s），
        // 避免 Redis 正常时 MySQL 每 5 分钟才更新导致 stat 显示滞后。
        // Redis 不可用时 fallback 到 MySQL scopeOffline()/scopeOnline()。
        try {
            $redisOnlineCount = (int) Redis::scard('nodes:online');
        } catch (\Throwable) {
            $redisOnlineCount = -1; // Redis 不可用，标记为 fallback
        }

        if ($redisOnlineCount >= 0) {
            // Redis 正常：直接用 Redis SET 的大小作为在线节点数
            $onlineNodes = $redisOnlineCount;
        } else {
            // Redis 不可用：fallback 到 MySQL（可能滞后 5 分钟，仅作保底）
            $onlineNodes = (int) Node::online()->count();
        }

        $offlineNodes = max($totalNodes - $onlineNodes - $notInstalledNodes, 0);

        $publishes = PublishTask::count();
        $completedPublishes = PublishTask::where('status', 'succeeded')->count();
        $health = (new HealthCheckService())->probe();

        // 从 ClickHouse dns_logs 获取查询量和拦截量
        $last24h = 0;
        $blocked24h = 0;
        try {
            $client = new ClickHouseClient();
            $row = $client->jsonSelect(
                "SELECT count() AS c FROM dns_logs WHERE event_time >= now() - INTERVAL 24 HOUR"
            );
            $last24h = (int) ($row[0]['c'] ?? 0);

            $blockedRow = $client->jsonSelect(
                "SELECT count() AS c FROM dns_logs WHERE event_time >= now() - INTERVAL 24 HOUR AND lower(action) IN ('block', 'blocked')"
            );
            $blocked24h = (int) ($blockedRow[0]['c'] ?? 0);
        } catch (\Throwable) {
            // ClickHouse 不可用时返回 0
        }

        // 用户统计
        $totalUsers = \App\Models\User::count();
        $activeUsers = (int) \App\Models\User::where('last_login_at', '>=', now()->subDays(7))->count();

        return response()->json([
            'data' => [
                'nodes' => [
                    'total' => $totalNodes,
                    'online' => $onlineNodes,
                    'offline' => $offlineNodes,
                    'not_installed' => $notInstalledNodes,
                ],
                'publishes' => [
                    'total' => $publishes,
                    'success_rate' => $publishes > 0 ? round(($completedPublishes / $publishes) * 100, 2) : 0.0,
                    'last_24h' => PublishTask::where('queued_at', '>=', now()->subDay())->count(),
                ],
                'configs' => [
                    'total_versions' => ProfileVersion::count(),
                    'active_nodes' => $onlineNodes,
                    'latest_version' => (int) (ProfileVersion::max('version') ?? 0),
                ],
                'queries' => [
                    'last_24h' => $last24h,
                    'blocked_24h' => $blocked24h,
                    'gafam' => $this->countByCategory('gafam'),
                    'root' => $this->countByCategory('root'),
                    'encrypted_dns' => $this->countByCategory('encrypted_dns'),
                    'dnssec_valid' => $this->countByCategory('dnssec_valid'),
                ],
                'users' => [
                    'total' => $totalUsers,
                    'active' => $activeUsers,
                ],
                'system' => array_merge([
                    'uptime_hours' => 0,
                    'php_version' => PHP_VERSION,
                ], $health),
            ],
        ]);
    }

    private function countByCategory(string $bucket): int
    {
        try {
            $client = new ClickHouseClient();
            $query = match ($bucket) {
                'gafam' => "SELECT count() AS c FROM dns_logs WHERE event_time >= now() - INTERVAL 24 HOUR AND domain IN ('google.com','www.google.com','youtube.com','www.youtube.com','facebook.com','www.facebook.com','instagram.com','www.instagram.com','whatsapp.com','www.whatsapp.com','x.com','twitter.com','www.x.com','www.twitter.com','apple.com','www.apple.com','amazon.com','www.amazon.com','microsoft.com','www.microsoft.com')",
                'root' => "SELECT count() AS c FROM dns_logs WHERE event_time >= now() - INTERVAL 24 HOUR AND position(domain, '.') = 0",
                'encrypted_dns' => "SELECT count() AS c FROM dns_logs WHERE event_time >= now() - INTERVAL 24 HOUR AND lower(protocol) IN ('doh','dot','doq')",
                'dnssec_valid' => "SELECT 0 AS c",
                default => null,
            };
            if ($query === null) {
                return 0;
            }
            $row = $client->jsonSelect($query);
            return (int) ($row[0]['c'] ?? 0);
        } catch (\Throwable) {
            return 0;
        }
    }
}
