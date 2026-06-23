<?php

declare(strict_types=1);

namespace App\Domain\Publish;

use App\Models\ConfigVersion;
use App\Models\Node;
use App\Models\PublishTask;
use App\Models\TaskExecution;

/**
 * In-process equivalent of the former dns-console-web internal
 * `POST /api/v1/internal/profile-publishes` endpoint.
 *
 * Writes a (config_version, publish_task, task_executions) tuple to the
 * shared portal-web database. Member-side flows (the publish button on
 * a profile) call this directly. There is no HTTP layer and no fallback
 * path: if any write fails, the caller's transaction is rolled back and
 * a 5xx propagates to the user.
 */
final class PublishService
{
    /**
     * @param array<string, mixed> $configJson
     * @return array{publish_id: string, status: string, config_version: int, checksum: string}
     */
    public function recordPublish(
        string $profileId,
        int $profileVersion,
        string $checksum,
        array $configJson,
    ): array {
        $globalVersion = (int) (ConfigVersion::max('version') ?? 0) + 1;
        $encoded = json_encode($configJson, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            throw new \RuntimeException(
                'Failed to encode config JSON for publish: ' . json_last_error_msg(),
            );
        }

        $configVersion = ConfigVersion::create([
            'version' => $globalVersion,
            'target_scope' => 'profile',
            'target_profile_id' => $this->resolveProfilePk((string) ($configJson['profile_id'] ?? $profileId)),
            'config_json' => $configJson,
            'checksum' => $checksum,
            'published_at' => now(),
            'created_at' => now(),
        ]);

        // 2026-06-22: 单一事实源 — 用 Node::online() scope 取真正在岗的节点（last_heartbeat_at 距 now 不超过阈值）。
        // 之前的 $activeStatuses = ['pending','online','degraded','maintenance'] 是基于已 drop 的 status 列。
        $targetNodes = Node::online()->get(['node_id']);

        $publishTask = PublishTask::create([
            'config_version_id' => $configVersion->id,
            'profile_id' => $profileId,
            'status' => 'queued',
            'target_scope' => 'all_nodes',
            'target_filter' => [],
            'target_node_count' => $targetNodes->count(),
            'applied_node_count' => 0,
            'failed_node_count' => 0,
            'retry_count' => 0,
            'message' => 'Queued for resolver pull',
            'queued_at' => now(),
        ]);

        if ($targetNodes->isNotEmpty()) {
            $now = now();
            $rows = $targetNodes->map(fn (Node $node): array => [
                'id' => 'texec_' . bin2hex(random_bytes(8)),
                'publish_task_id' => $publishTask->id,
                'node_id' => $node->id,
                'config_version' => $globalVersion,
                'status' => 'pending',
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();
            TaskExecution::insert($rows);
        }

        // 2026-06-22: desired_config_version 只下发给真正能拉到配置的节点。
        Node::online()->update([
            'desired_config_version' => $globalVersion,
        ]);

        return [
            'publish_id' => $publishTask->id,
            'status' => 'queued',
            'config_version' => (int) $configVersion->version,
            'checksum' => (string) $configVersion->checksum,
        ];
    }

    private function resolveProfilePk(string $profileRef): ?int
    {
        if ($profileRef === '') {
            return null;
        }
        if (ctype_digit($profileRef)) {
            return (int) $profileRef;
        }
        $row = \App\Models\Profile::where('profile_uid', $profileRef)->first(['id']);
        return $row?->id !== null ? (int) $row->id : null;
    }
}
