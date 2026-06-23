<?php

declare(strict_types=1);

namespace App\Domain\Policy;

use App\Models\Node;
use App\Models\PolicyPublishLog;
use App\Models\PolicySnapshot;

/**
 * UI.md #62 — Policy 发布 → Node → ACK 全链路记录。
 *
 * 2026-06-23: 使用 Node 模型，通过 region 字段区分 resolver 节点。
 */
final class PolicyPublisher
{
    /**
     * 发布 snapshot 到全部在线 node，写入 publish_logs。
     * @return array<int,array{node_id:string,status:string}>
     */
    public function publishToAllOnlineNodes(int $snapshotId): array
    {
        $snap = PolicySnapshot::findOrFail($snapshotId);
        if ($snap->status !== PolicySnapshot::STATUS_PUBLISHED) {
            // 允许 draft 状态也预创建 log，发布时再统一更新 ack
        }
        $nodes = Node::online()->where('region', 'like', 'resolver-%')->get();
        $results = [];
        foreach ($nodes as $node) {
            $log = PolicyPublishLog::create([
                'snapshot_id' => $snap->id,
                'node_id' => $node->node_code,
                'status' => PolicyPublishLog::STATUS_PENDING,
            ]);
            $results[] = ['node_id' => $node->node_code, 'status' => $log->status, 'log_id' => $log->id];
        }
        return $results;
    }

    public function recordAck(int $logId, bool $ok, ?string $error = null): PolicyPublishLog
    {
        $log = PolicyPublishLog::findOrFail($logId);
        $log->update([
            'status' => $ok ? PolicyPublishLog::STATUS_ACKED : PolicyPublishLog::STATUS_FAILED,
            'ack_at' => now(),
            'error_message' => $error,
        ]);
        return $log;
    }
}
