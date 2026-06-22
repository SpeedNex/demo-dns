<?php

namespace App\Http\Controllers\Api\V1\Node;

use App\Domain\Heartbeat\HeartbeatService;
use App\Models\Alert;
use App\Models\Node;
use App\Models\NodeHeartbeat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class HeartbeatController
{
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
        $heartbeat['node_id'] = $node->node_id;

        // 历史心跳表保留 status（自报），便于审计 / 趋势分析。
        NodeHeartbeat::create([
            'node_id' => $node->node_id,
            'status' => $heartbeat['status'],
            'uptime_seconds' => (int) ($heartbeat['uptime_seconds'] ?? 0),
            'version' => $heartbeat['version'] ?? null,
            'current_config_version' => $heartbeat['current_config_version'],
            'profiles_loaded' => (int) ($heartbeat['profiles_loaded'] ?? 0),
            'last_config_pull_at' => $heartbeat['last_config_pull_at'] ?? null,
            'last_log_flush_at' => $heartbeat['last_log_flush_at'] ?? null,
            'reported_at' => now(),
            'created_at' => now(),
        ]);

        // 2026-06-22: 单一事实源 — nodes 表上不再写 status 列（已 drop）。
        // "是否在线" 任何读路径都用 $node->isOnline() 现算。
        // 这里只更新 last_heartbeat_at 与 config 版本，足够让 isOnline() 返回 true。
        $previousLastHeartbeatAt = $node->last_heartbeat_at;
        $node->update([
            'current_config_version' => $heartbeat['current_config_version'],
            'last_heartbeat_at' => now(),
        ]);
        // 关键：refresh 让后续的 isOnline() / runtimeStatus() 走的是更新后的 last_heartbeat_at。
        $node->refresh();

        $result = response()->json([
            'data' => $service->evaluate($heartbeat, [
                'desired_config_version' => $node->desired_config_version,
            ]),
        ]);

        // 告警场景 1: 节点自报 status=degraded / offline（节点自己看到异常了，通报一下）
        // 与「心跳超时」不同：这里是 agent 主动报告，不是控制平面超时检测。
        $reportedStatus = (string) ($heartbeat['status'] ?? HeartbeatService::STATUS_ONLINE);
        if (in_array($reportedStatus, ['degraded', 'offline'], true)) {
            Alert::create([
                'code' => 'node_reported_' . $reportedStatus,
                'level' => $reportedStatus === 'offline' ? 'error' : 'warning',
                'source' => 'node',
                'subject_type' => 'node',
                'subject_id' => $node->node_id,
                'title' => '节点上报异常',
                'message' => "节点 {$node->node_alias} (id={$node->node_id}) 上报 status={$reportedStatus}",
                'status' => 'open',
            ]);
        }

        // 告警场景 2: 节点超时后第一次恢复心跳 — 用「旧 last_heartbeat_at 距 now > 5 分钟」+「更新前 isOnline() 为 false」判断
        $threshold = now()->subSeconds(300);
        // isOnline() 用「更新前的 last_heartbeat_at」独立算一遍（不等同于 runtimeStatus()，因为后者还会查 install_status）
        $wasOnlineBefore = $previousLastHeartbeatAt instanceof \Carbon\Carbon
            && $previousLastHeartbeatAt->gt(now()->subSeconds($node->getHeartbeatStaleSeconds()));
        if (
            $previousLastHeartbeatAt instanceof \Carbon\Carbon
            && $previousLastHeartbeatAt->lt($threshold)
            && ! $wasOnlineBefore
        ) {
            Alert::create([
                'code' => 'node_heartbeat_timeout',
                'level' => 'warning',
                'source' => 'node',
                'subject_type' => 'node',
                'subject_id' => $node->node_id,
                'title' => '节点心跳超时',
                'message' => "节点 {$node->node_alias} (id={$node->node_id}) 距离上次心跳已超 5 分钟",
                'status' => 'open',
            ]);
        }

        return $result;
    }
}
