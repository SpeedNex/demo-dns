<?php

declare(strict_types=1);

namespace App\Application\Node;

use App\Domain\ConfigVersion\ConfigAckService;
use App\Models\Node;
use App\Models\PublishTask;
use App\Models\TaskExecution;

final class ConfigAcknowledgementService
{
    public function __construct(
        private readonly ConfigAckService $configAckService,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function acknowledge(Node $node, array $payload): array
    {
        $ack = $this->configAckService->acknowledge([
            'node_id' => $node->id,
            'config_version' => $payload['config_version'],
            'status' => $payload['status'],
        ]);

        $node->update([
            'current_config_version' => $payload['config_version'],
        ]);

        $execution = TaskExecution::where('node_id', $node->id)
            ->where('config_version', $payload['config_version'])
            ->latest('updated_at')
            ->first();

        if ($execution === null) {
            return $ack;
        }

        $execution->update([
            'status' => $payload['status'],
            'checksum' => $payload['checksum'] ?? $execution->checksum,
            'applied_at' => now(),
            'last_seen_at' => now(),
        ]);

        $task = PublishTask::withCount([
            'executions as applied_count' => fn ($query) => $query->where('status', 'applied'),
            'executions as failed_count' => fn ($query) => $query->where('status', 'failed'),
            'executions as pending_count' => fn ($query) => $query->whereIn('status', ['pending', 'sent']),
        ])->find($execution->publish_task_id);

        if ($task !== null) {
            $target = (int) $task->target_node_count;
            $applied = (int) $task->applied_count;
            $failed = (int) $task->failed_count;
            $pending = (int) $task->pending_count;

            // 修复 2026-07-06 #14：按 target/applied/failed/pending 决定状态。
            // 原逻辑：只要没有 failed_count，第一个节点 applied 后任务就可能被标记为 succeeded，
            // 即使还有大量 pending 节点。后台发布中心显示"成功"但实际很多节点未应用。
            $newStatus = $task->status;
            if ($pending > 0) {
                $newStatus = 'running';
            } elseif ($applied >= $target && $target > 0) {
                $newStatus = $failed > 0 ? 'partial' : 'succeeded';
            } elseif ($applied === 0 && $failed >= $target) {
                $newStatus = 'failed';
            } elseif ($failed > 0) {
                $newStatus = 'partial';
            } else {
                $newStatus = 'running';
            }

            // 仅当所有节点均已 ACK（applied + failed == target）才置 completed_at；否则说明还有 pending
            $allAcked = $target > 0 && ($applied + $failed) >= $target;

            $task->update([
                'status' => $newStatus,
                'applied_node_count' => $applied,
                'failed_node_count' => $failed,
                'completed_at' => $allAcked ? now() : null,
            ]);
        }

        return $ack;
    }
}
