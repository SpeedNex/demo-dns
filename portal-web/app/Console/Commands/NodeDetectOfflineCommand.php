<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Alert\AlertService;
use App\Models\Node;
use Illuminate\Console\Command;

/**
 * node:detect-offline — 主动扫描心跳超时/离线的 resolver 节点并生成告警。
 *
 * 用法:
 *   php artisan node:detect-offline                  # 扫描全部节点
 *   php artisan node:detect-offline --dry-run        # 只统计，不写告警
 *   php artisan node:detect-offline --quiet          # 静默模式
 *
 * 阈值（与 Node::isOnline() / scopeOffline() 完全一致，避免漂移）:
 *   - online   : Redis key 存在 或 last_heartbeat_at > now - 90s
 *   - degraded : last_heartbeat_at ∈ (now-180s, now-90s]
 *   - offline  : last_heartbeat_at <= now-180s 或从未上报
 *
 * 告警去重：同 (code, subject_type=node, subject_id, status=open) 已存在则不重复创建。
 * 告警恢复：当节点恢复心跳后，自动将同类 open 告警标记为 resolved。
 */
final class NodeDetectOfflineCommand extends Command
{
    protected $signature = 'node:detect-offline
        {--dry-run : 仅输出统计，不写入告警}
        {--silent : 静默模式，仅输出汇总}';

    protected $description = 'Detect resolver nodes whose heartbeat is stale and emit alerts';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $silent = (bool) $this->option('silent');

        // 与 Node::isOnline() 的阈值完全一致（90s）
        $threshold = (int) env('NODE_HEARTBEAT_STALE_SECONDS', 90);
        $offlineThreshold = $threshold * 2;  // 180s 离线阈值

        $online = Node::query()->online()->count();
        $degraded = Node::query()->degraded()->count();
        $offline = Node::query()->offline()->count();
        $total = Node::query()->count();

        if (! $silent) {
            $this->info("Scanning resolver nodes (online<{$threshold}s, degraded∈({$offlineThreshold}s,{$threshold}s], offline≥{$offlineThreshold}s)...");
            $this->table(
                ['Status', 'Count'],
                [
                    ['online', $online],
                    ['degraded', $degraded],
                    ['offline', $offline],
                    ['total', $total],
                ]
            );
        }

        if ($dryRun) {
            $this->warn('--dry-run set, no alerts will be created.');
            return 0;
        }

        $created = 0;
        $skipped = 0;
        $recovered = 0;

        // 处理 degraded 节点（90-180s 心跳延迟）
        $degradedNodes = Node::query()->degraded()->get();
        foreach ($degradedNodes as $node) {
            $result = $this->ensureAlert(
                node: $node,
                code: 'node_heartbeat_degraded',
                level: 'warning',
                title: '节点心跳降级',
                message: "节点 {$node->node_alias} (id={$node->id}) 心跳延迟超过 {$threshold}s 但未超过 {$offlineThreshold}s",
                silent: $silent
            );
            $result ? $created++ : $skipped++;
        }

        // 处理 offline 节点（≥180s 无心跳）
        $offlineNodes = Node::query()->offline()->get();
        foreach ($offlineNodes as $node) {
            $lastSeen = $node->last_heartbeat_at
                ? $node->last_heartbeat_at->diffForHumans(now(), ['short' => true])
                : '从未上报';

            $result = $this->ensureAlert(
                node: $node,
                code: 'node_heartbeat_offline',
                level: 'critical',
                title: '节点心跳离线',
                message: "节点 {$node->node_alias} (id={$node->id}) 离线（最后心跳: {$lastSeen}）",
                silent: $silent
            );
            $result ? $created++ : $skipped++;
        }

        // 恢复检测：已安装节点中，在线且有 open 离线/降级告警的 → 自动 resolved
        $onlineNodes = Node::query()->online()->get();
        foreach ($onlineNodes as $node) {
            $resolved = $this->resolveAlertsForNode($node, $silent);
            if ($resolved > 0) {
                $recovered += $resolved;
            }
        }

        $this->info("Done. created={$created}, skipped(dedup)={$skipped}, recovered={$recovered}");

        return 0;
    }

    /**
     * 去重告警：相同 (code, subject_type=node, subject_id, status=open) 已存在则跳过。
     *
     * @return bool true=新建告警, false=跳过（已存在）
     */
    private function ensureAlert(
        Node $node,
        string $code,
        string $level,
        string $title,
        string $message,
        bool $silent,
    ): bool {
        $exists = \App\Models\Alert::query()
            ->where('code', $code)
            ->where('subject_type', 'node')
            ->where('subject_id', $node->id)
            ->where('status', 'open')
            ->exists();

        if ($exists) {
            if (! $silent) {
                $this->line("  · skip (open alert exists): node={$node->id} code={$code}");
            }
            return false;
        }

        AlertService::create(
            level: $level,
            title: $title,
            message: $message,
            code: $code,
            source: 'system',
            subjectType: 'node',
            subjectId: (int) $node->id,
            payload: [
                'node_id' => $node->id,
                'node_code' => $node->node_code,
                'node_alias' => $node->node_alias,
                'last_heartbeat_at' => $node->last_heartbeat_at?->toIso8601String(),
                'detected_by' => 'node:detect-offline',
            ],
        );

        if (! $silent) {
            $this->info("  ✓ alert created: node={$node->id} code={$code}");
        }
        return true;
    }

    /**
     * 节点恢复后，自动将相关的 open 离线/降级告警标记为 resolved。
     *
     * @return int 关闭的告警数量
     */
    private function resolveAlertsForNode(Node $node, bool $silent): int
    {
        $updated = \App\Models\Alert::query()
            ->where('subject_type', 'node')
            ->where('subject_id', $node->id)
            ->whereIn('code', ['node_heartbeat_offline', 'node_heartbeat_degraded'])
            ->where('status', 'open')
            ->update([
                'status' => 'resolved',
                'resolved_at' => now(),
            ]);

        if ($updated > 0 && ! $silent) {
            $this->line("  · recovered: node={$node->id} resolved {$updated} alert(s)");
        }

        return $updated;
    }
}
