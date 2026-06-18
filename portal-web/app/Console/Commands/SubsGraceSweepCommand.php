<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Billing\SubscriptionService;
use App\Domain\Jobs\JobRunner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * UI.md #59 — 订阅宽限期扫描。
 *
 *  - past_due 超过 grace_until → 自动 markSuspended
 *  - 调度：建议每小时 1 次（app/Console/Kernel.php）
 *
 * php artisan subs:grace-sweep
 */
final class SubsGraceSweepCommand extends Command
{
    protected $signature = 'subs:grace-sweep {--limit=500}';

    protected $description = '扫描 grace 期过期的 past_due 订阅，强制进入 suspended';

    public function handle(SubscriptionService $subs): int
    {
        $result = JobRunner::run('subs_grace_sweep', function () use ($subs) {
            $now = now();
            $limit = (int) $this->option('limit');
            $rows = DB::table('subscriptions')
                ->where('status', SubscriptionService::STATUS_PAST_DUE)
                ->whereNotNull('grace_until')
                ->where('grace_until', '<', $now)
                ->limit($limit)
                ->get(['id', 'user_id']);
            $suspended = 0;
            foreach ($rows as $r) {
                $subs->markSuspended($r->user_id);
                $suspended++;
            }
            return ['scanned' => count($rows), 'suspended' => $suspended];
        });
        if (! $result['ok']) {
            $this->error('subs:grace-sweep failed');
            return self::FAILURE;
        }
        $this->info(sprintf('subs:grace-sweep ok — suspended=%d', $result['suspended'] ?? 0));
        return self::SUCCESS;
    }
}
