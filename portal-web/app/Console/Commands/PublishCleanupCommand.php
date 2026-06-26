<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AdminAuditLog;
use App\Models\PublishTask;
use Illuminate\Console\Command;

/**
 * UI.md 发布中心 — 清理由 Resolver 未及时回执而堆积的 queued 发布任务。
 *
 * Resolver 节点离线或未正确配置时，publish_tasks 将永久停留在 queued 状态。
 * 本命令将超时任务标记为 failed（或 --delete 彻底删除），确保后台页面不堆积。
 *
 * 用法:
 *   php artisan publish:cleanup                  # 标记 30 分钟前的 queued 为 failed
 *   php artisan publish:cleanup --minutes=60     # 自定义超时窗口
 *   php artisan publish:cleanup --delete         # 直接删除（非标记）
 *   php artisan publish:cleanup --keep=10        # 保留最近 N 条不处理
 */
final class PublishCleanupCommand extends Command
{
    protected $signature = 'publish:cleanup
        {--minutes=30 : 超时分钟数，超过此时间未完成的任务将被处理}
        {--delete : 直接删除而非标记为 failed}
        {--keep=10 : 保留最近 N 条任务不处理}';

    protected $description = '清理超时未完成的发布任务（queued）';

    public function handle(): int
    {
        $minutes = (int) $this->option('minutes');
        $keep = (int) $this->option('keep');
        $delete = (bool) $this->option('delete');
        $cutoff = now()->subMinutes($minutes);
        $action = $delete ? 'delete' : 'mark-failed';

        $this->info("Publish cleanup [{$action}] starting...");
        $this->line("  Cutoff: {$cutoff}");
        $this->line("  Keep latest: {$keep}");

        // 跳过最近 N 条的 ID 范围
        $latestIds = PublishTask::query()
            ->where('status', 'queued')
            ->orderByDesc('id')
            ->take($keep)
            ->pluck('id')
            ->all();

        $query = PublishTask::query()
            ->where('status', 'queued')
            ->where('created_at', '<', $cutoff);

        if ($latestIds !== []) {
            $query->whereNotIn('id', $latestIds);
        }

        $count = $query->count();
        if ($count === 0) {
            $this->info('No queued tasks to clean up.');
            return self::SUCCESS;
        }

        $this->warn("Found {$count} queued tasks exceeding {$minutes} minutes.");

        if (! $this->confirm("Proceed to {$action} {$count} tasks?", true)) {
            $this->info('Cancelled.');
            return self::SUCCESS;
        }

        if ($delete) {
            $query->delete();
            AdminAuditLog::record(
                'publish.cleanup',
                'publish_task',
                null,
                ['deleted' => $count, 'cutoff' => (string) $cutoff, 'action' => 'delete'],
                'system',
            );
            $this->info("Deleted {$count} stale queued tasks.");
        } else {
            $updated = $query->update([
                'status' => 'failed',
                'completed_at' => now(),
                'latest_error' => "Auto-failed: timeout (>{$minutes} min) waiting for resolver ack",
            ]);
            AdminAuditLog::record(
                'publish.cleanup',
                'publish_task',
                null,
                ['marked_failed' => $updated, 'cutoff' => (string) $cutoff, 'action' => 'mark-failed'],
                'system',
            );
            $this->info("Marked {$updated} queued tasks as failed.");
        }

        return self::SUCCESS;
    }
}
