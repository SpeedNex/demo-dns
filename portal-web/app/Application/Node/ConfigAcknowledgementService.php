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
            'node_id' => $node->node_id,
            'config_version' => $payload['config_version'],
            'status' => $payload['status'],
        ]);

        $node->update([
            'current_config_version' => $payload['config_version'],
        ]);

        $execution = TaskExecution::where('node_id', $node->node_id)
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
        ])->find($execution->publish_task_id);

        if ($task !== null) {
            $task->update([
                'status' => $task->failed_count > 0 ? 'partial' : 'succeeded',
                'applied_node_count' => $task->applied_count,
                'failed_node_count' => $task->failed_count,
                'completed_at' => now(),
            ]);
        }

        return $ack;
    }
}
