<?php

declare(strict_types=1);

namespace App\Domain\Publish;

use App\Models\ProfileVersion;
use App\Models\Node;
use App\Models\PublishTask;
use App\Models\TaskExecution;
use Illuminate\Support\Facades\DB;

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
        $encoded = json_encode($configJson, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            throw new \RuntimeException(
                'Failed to encode config JSON for publish: ' . json_last_error_msg(),
            );
        }

        // 修复 2026-07-06 #15 #16：用数据库事务 + 行锁保证 (version, profile_version) 的原子写入与版本号无冲突。
        // 事务包裹 ProfileVersion/PublishTask/TaskExecution/Node 更新，避免半成品发布记录。
        // 全局 version 等于新建的 ProfileVersion.id（数据库自增），保证 version 唯一、单调递增、无并发冲突。
        return DB::transaction(function () use ($profileId, $profileVersion, $checksum, $configJson): array {
            // 修复 #15：version = 新插入行的主键 id（自增），避免 max(id)+1 竞态
            // 先创建 ProfileVersion 占位，拿到 id 后把 version 同步为 id
            $configVersion = ProfileVersion::create([
                'version' => $profileVersion, // 临时值，下面更新
                'target_scope' => 'profile',
                'target_profile_id' => $this->resolveProfilePk((string) ($configJson['profile_id'] ?? $profileId)),
                'config_json' => $configJson,
                'checksum' => $checksum,
                'published_at' => now(),
                'created_at' => now(),
            ]);

            // 用自增主键 id 作为全局 version，确保并发安全
            $globalVersion = (int) $configVersion->id;
            if ($globalVersion !== $profileVersion) {
                $configVersion->update(['version' => $globalVersion]);
            }

            // 2026-06-22: 单一事实源 — 用 Node::online() scope 取真正在岗的节点（last_heartbeat_at 距 now 不超过阈值）。
            // 之前的 $activeStatuses = ['pending','online','degraded','maintenance'] 是基于已 drop 的 status 列。
            // 加行锁 SELECT ... FOR UPDATE，避免与 ack 流程并发修改
            $targetNodes = Node::online()->lockForUpdate()->get(['id']);

            $publishTask = PublishTask::create([
                'profile_version_id' => $configVersion->id,
                'profile_id' => $this->resolveProfilePk($profileId),
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

            // 2026-06-26: 更新所有已安装节点的目标配置版本，不限制在线状态。
            // 即使节点心跳过期（离线），也要更新 desired_config_version，
            // 这样节点下次心跳时 HeartbeatService 能返回正确的版本号，触发拉取。
            Node::where('install_status', 'installed')->update([
                'desired_config_version' => $globalVersion,
            ]);

            return [
                'publish_id' => $publishTask->id,
                'status' => 'queued',
                'config_version' => (int) $configVersion->version,
                'checksum' => (string) $configVersion->checksum,
            ];
        });
    }

    private function resolveProfilePk(string $profileRef): ?int
    {
        if ($profileRef === '') {
            return null;
        }
        $row = \App\Models\Profile::where('profile_id', $profileRef)->first(['id']);
        if ($row?->id !== null) {
            return (int) $row->id;
        }

        return ctype_digit($profileRef) ? (int) $profileRef : null;
    }
}
