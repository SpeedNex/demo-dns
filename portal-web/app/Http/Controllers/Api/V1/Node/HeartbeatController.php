<?php

namespace App\Http\Controllers\Api\V1\Node;

use App\Domain\Heartbeat\HeartbeatService;
use App\Models\Alert;
use App\Models\Node;
use App\Models\NodeHeartbeat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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
        $heartbeat['node_id'] = $node->id;

        NodeHeartbeat::create([
            'node_id' => $node->id,
            'status' => $heartbeat['status'],
            'uptime_seconds' => (int) ($heartbeat['uptime_seconds'] ?? 0),
            'version' => $heartbeat['version'] ?? $node->version,
            'current_config_version' => $heartbeat['current_config_version'],
            'profiles_loaded' => (int) ($heartbeat['profiles_loaded'] ?? 0),
            'last_config_pull_at' => $heartbeat['last_config_pull_at'] ?? null,
            'last_log_flush_at' => $heartbeat['last_log_flush_at'] ?? null,
            'reported_at' => now(),
            'created_at' => now(),
        ]);

        // 在 update node 之前记录"旧"心跳时间，用于超时告警判断
        $previousLastHeartbeatAt = $node->last_heartbeat_at;
        $previousNodeStatus = $node->status;

        $node->update([
            'status' => $service->computeStatus($heartbeat),
            'version' => $heartbeat['version'] ?? $node->version,
            'current_config_version' => $heartbeat['current_config_version'],
            'last_heartbeat_at' => now(),
        ]);

        $result = response()->json([
            'data' => $service->evaluate($heartbeat, [
                'desired_config_version' => $node->desired_config_version,
            ]),
        ]);

        // 告警场景 1: 节点上报 status=degraded 或 offline (自身报告异常)
        $reportedStatus = (string) ($heartbeat['status'] ?? HeartbeatService::STATUS_ONLINE);
        if (in_array($reportedStatus, ['degraded', 'offline'], true)) {
            Alert::create([
                'code' => 'node_reported_' . $reportedStatus,
                'level' => $reportedStatus === 'offline' ? 'error' : 'warning',
                'source' => 'node',
                'subject_type' => 'node',
                'subject_id' => $node->id,
                'title' => '节点上报异常',
                'message' => "节点 {$node->name} (id={$node->id}) 上报 status={$reportedStatus}",
                'status' => 'open',
            ]);
        }

        // 告警场景 2: 节点超时（之前 last_heartbeat_at 超过 5 分钟未更新，但刚收到心跳）
        // 即: 这是节点超时后第一次恢复心跳，记录超时事件
        $threshold = now()->subSeconds(300);
        if (
            $previousLastHeartbeatAt instanceof \Carbon\Carbon
            && $previousLastHeartbeatAt->lt($threshold)
            && $previousNodeStatus !== 'online'
        ) {
            Alert::create([
                'code' => 'node_heartbeat_timeout',
                'level' => 'warning',
                'source' => 'node',
                'subject_type' => 'node',
                'subject_id' => $node->id,
                'title' => '节点心跳超时',
                'message' => "节点 {$node->name} (id={$node->id}) 距离上次心跳已超 5 分钟",
                'status' => 'open',
            ]);
        }

        return $result;
    }
}
