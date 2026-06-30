<?php

namespace App\Http\Controllers\Api\V1\Node;

use App\Domain\Heartbeat\HeartbeatService;
use App\Models\Alert;
use App\Models\Node;
use App\Models\NodeHeartbeat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

final class HeartbeatController
{
    /** 心跳写入 MySQL 的最小间隔（秒）：约 5 分钟 */
    private const MYSQL_FLUSH_INTERVAL = 300;

    public function store(Request $request): JsonResponse
    {
        $service = new HeartbeatService();
        /** @var Node $node */
        $node = $request->attributes->get('node');

        // 心跳只携带"是否在岗 + 持有配置版本"，不再带 qps/cpu/mem/disk/error
        $heartbeat = $request->validate([
            'status' => 'nullable|string|max:30',
            'uptime_seconds' => 'nullable|integer|min:0',
            'version' => 'nullable|string|max:50',
            'current_config_version' => 'nullable|integer|min:0',
            'profiles_loaded' => 'nullable|integer|min:0',
            'last_config_pull_at' => 'nullable|date',
            'last_log_flush_at' => 'nullable|date',
        ]);
        $heartbeat['status'] = $heartbeat['status'] ?? HeartbeatService::STATUS_ONLINE;
        $heartbeat['current_config_version'] = (int) ($heartbeat['current_config_version'] ?? $node->current_config_version);
        $heartbeat['node_id'] = $node->id;

        $now = now();
        $nodeId = $node->id;

        // ================================================================
        // 1. Redis 最新心跳（每次写入，TTL 90 秒）
        //    兜底：Redis 宕机时仍可正常心跳，降级为每次写 MySQL
        // ================================================================
        $redisAvailable = false;
        try {
            $redisKey = "node:{$nodeId}:heartbeat";
            Redis::hMSet($redisKey, [
                'status' => $heartbeat['status'],
                'uptime_seconds' => (int) ($heartbeat['uptime_seconds'] ?? 0),
                'version' => $heartbeat['version'] ?? '',
                'current_config_version' => $heartbeat['current_config_version'],
                'profiles_loaded' => (int) ($heartbeat['profiles_loaded'] ?? 0),
                'last_config_pull_at' => $heartbeat['last_config_pull_at'] ?? '',
                'last_log_flush_at' => $heartbeat['last_log_flush_at'] ?? '',
                'reported_at' => $now->toIso8601String(),
            ]);
            Redis::expire($redisKey, 90);

            // 更新在线节点集合
            Redis::sAdd('nodes:online', (string) $nodeId);
            Redis::expire('nodes:online', 90);

            $redisAvailable = true;
        } catch (\Throwable $e) {
            Log::warning('Redis unavailable for heartbeat, falling back to MySQL-only', [
                'node_id' => $nodeId,
                'error' => $e->getMessage(),
            ]);
        }

        // ================================================================
        // 2. MySQL 写入
        //    策略：
        //    - Redis 正常时：每 5 分钟写一次（由 Redis key 控制间隔）
        //    - Redis 宕机时：每次心跳都写（保证节点在线状态不丢失）
        // ================================================================
        $previousLastHeartbeatAt = $node->last_heartbeat_at;

        if ($redisAvailable) {
            // Redis 正常 → 低频写入
            $mysqlKey = "node:{$nodeId}:mysql_hb_at";
            try {
                $lastMysqlTs = Redis::get($mysqlKey);
                $shouldWriteMysql = $lastMysqlTs === null || ($now->timestamp - (int) $lastMysqlTs) >= self::MYSQL_FLUSH_INTERVAL;
            } catch (\Throwable) {
                // Redis 在刚才还正常，现在异常了 → 直接写 MySQL
                $shouldWriteMysql = true;
            }

            if ($shouldWriteMysql) {
                try {
                    Redis::setEx($mysqlKey, 600, (string) $now->timestamp);
                } catch (\Throwable) {
                    // Redis 写入失败不阻塞 MySQL 写入
                }
            }
        } else {
            // Redis 不可用 → 每次心跳都写 MySQL，确保在线状态不被丢失
            $shouldWriteMysql = true;
        }

        if ($shouldWriteMysql) {
            // 历史心跳表（审计 / 趋势分析）
            NodeHeartbeat::create([
                'node_id' => $nodeId,
                'status' => $heartbeat['status'],
                'uptime_seconds' => (int) ($heartbeat['uptime_seconds'] ?? 0),
                'version' => $heartbeat['version'] ?? null,
                'current_config_version' => $heartbeat['current_config_version'],
                'profiles_loaded' => (int) ($heartbeat['profiles_loaded'] ?? 0),
                'last_config_pull_at' => $heartbeat['last_config_pull_at'] ?? null,
                'last_log_flush_at' => $heartbeat['last_log_flush_at'] ?? null,
                'reported_at' => $now,
                'created_at' => $now,
            ]);

            // 更新 MySQL last_heartbeat_at，使 scopeOnline 等 SQL 查询仍可工作
            $updateData = [
                'current_config_version' => $heartbeat['current_config_version'],
                'last_heartbeat_at' => $now,
            ];

            // 2026-06-30: 有些节点手动部署时绕过 register 接口，导致 install_status 永远停在 pending
            // 心跳本身已通过 AuthenticateNodeApiKey 中间件鉴权（api_key 合法 = 节点物理可达），
            // 如果当前仍是 pending，翻转为 installed，让 Admin UI 状态展示与运行时一致。
            // failed 状态需人工介入，不在此自动翻转。
            if ($node->install_status === 'pending') {
                $updateData['install_status'] = 'installed';
                $updateData['last_installed_at'] = $now;
            }

            $node->update($updateData);
            $node->refresh();
        }

        // ================================================================
        // 3. 构造响应 & 告警检测
        // ================================================================
        // 2026-06-26: heartbeat 响应携带 latest_config_version，触发 Resolver 快速拉取
        $result = response()->json([
            'data' => $service->evaluate($heartbeat, [
                'desired_config_version' => $node->desired_config_version,
                'latest_config_version' => (int) ($node->desired_config_version ?? $node->current_config_version ?? 0),
            ]),
        ]);

        // 告警场景 1: 节点自报 status=degraded / offline（与 NodeDetectOfflineCommand 的阈值对齐）
        $reportedStatus = (string) ($heartbeat['status'] ?? HeartbeatService::STATUS_ONLINE);
        if (in_array($reportedStatus, ['degraded', 'offline'], true)) {
            // 去重：同类 open 告警已存在则跳过
            $exists = Alert::query()
                ->where('code', 'node_reported_' . $reportedStatus)
                ->where('subject_type', 'node')
                ->where('subject_id', $nodeId)
                ->where('status', 'open')
                ->exists();
            if (! $exists) {
                Alert::create([
                    'code' => 'node_reported_' . $reportedStatus,
                    'level' => $reportedStatus === 'offline' ? 'critical' : 'warning',
                    'source' => 'node',
                    'subject_type' => 'node',
                    'subject_id' => $nodeId,
                    'title' => $reportedStatus === 'offline' ? '节点上报离线' : '节点上报降级',
                    'message' => "节点 {$node->node_alias} (id={$nodeId}) 上报 status={$reportedStatus}",
                    'status' => 'open',
                ]);
            }
        }

        // 告警场景 2: 节点超时后第一次恢复心跳（阈值与离线检测一致 = 180s）
        $offlineThreshold = $node->getHeartbeatStaleSeconds() * 2;
        $wasOfflineBefore = $previousLastHeartbeatAt instanceof \Carbon\Carbon
            && $previousLastHeartbeatAt->lt($now->copy()->subSeconds($offlineThreshold));
        if ($wasOfflineBefore) {
            // 去重：同类 open 告警已存在则跳过
            $exists = Alert::query()
                ->where('code', 'node_heartbeat_offline')
                ->where('subject_type', 'node')
                ->where('subject_id', $nodeId)
                ->where('status', 'open')
                ->exists();
            if (! $exists) {
                Alert::create([
                    'code' => 'node_heartbeat_offline',
                    'level' => 'critical',
                    'source' => 'node',
                    'subject_type' => 'node',
                    'subject_id' => $nodeId,
                    'title' => '节点心跳超时离线',
                    'message' => "节点 {$node->node_alias} (id={$nodeId}) 距离上次心跳已超过 {$offlineThreshold} 秒",
                    'status' => 'open',
                ]);
            }
        }

        // 恢复检测：节点在线且有 open 离线/降级告警 → 自动 resolved
        if ($node->isOnline()) {
            Alert::query()
                ->where('subject_type', 'node')
                ->where('subject_id', $nodeId)
                ->whereIn('code', ['node_heartbeat_offline', 'node_heartbeat_degraded', 'node_reported_degraded', 'node_reported_offline'])
                ->where('status', 'open')
                ->update([
                    'status' => 'resolved',
                    'resolved_at' => $now,
                ]);
        }

        return $result;
    }
}
