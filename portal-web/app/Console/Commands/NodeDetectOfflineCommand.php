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
 * 触发条件（与 Node::scopeOnline/Degraded/Offline 保持一致，避免漂移）:
 *   - online  : last_heartbeat_at >  now - 90s
 *   - degraded: last_heartbeat_at ∈ (now-180s, now-90s]
 *   - offline : last_heartbeat_at <= now-180s 或 NULL
 *
 * 告警去重：同 (code, subject_type, subject_id, status=open) 已存在则不重复创建。
 * 告警恢复：当节点恢复心跳后，调用 HeartbeatController 中已有的"超时后第一次恢复"逻辑闭环。
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

        $threshold = (int) env('NODE_HEARTBEAT_STALE_SECONDS', 90);

        $online = Node::query()->online()->count();
        $degraded = Node::query()->degraded()->count();
        $offline = Node::query()->offline()->count();
        $total = Node::query()->count();

        if (! $silent) {
            $this->info("Scanning resolver nodes (stale threshold={$threshold}s)...");
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

        // 处理 degraded 节点
        $degradedNodes = Node::query()->degraded()->get();
        foreach ($degradedNodes as $node) {
            $result = $this->ensureAlert(
                node: $node,
                code: 'node_heartbeat_degraded',
                level: 'warning',
                title: '节点心跳降级',
                message: "节点 {$node->node_alias} (id={$node->id}) 心跳已超过 {$threshold} 秒但未超过 " . ($threshold * 2) . " 秒",
                silent: $silent
            );
            $result ? $created++ : $skipped++;
        }

        // 处理 offline 节点
        $offlineNodes = Node::query()->offline()->get();
        foreach ($offlineNodes as $node) {
            $lastSeen = $node->last_heartbeat_at
                ? $node->last_heartbeat_at->diffForHumans(now(), ['short' => true])
                : '从未上报';

            $result = $this->ensureAlert(
                node: $node,
                code: 'node_heartbeat_offline',
                level: 'error',
                title: '节点心跳离线',
                message: "节点 {$node->node_alias} (id={$node->id}) 离线（最后心跳: {$lastSeen}）",
                silent: $silent
            );
            $result ? $created++ : $skipped++;
        }

        $this->info("Done. created={$created}, skipped(dedup)={$skipped}");

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
}
