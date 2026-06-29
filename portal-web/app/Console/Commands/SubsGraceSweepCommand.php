<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Billing\SubscriptionService;
use App\Domain\Jobs\JobRunner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * UI.md #59 — 订阅到期/宽限期扫描。
 *
 *  - active/trialing 超过 current_period_end → 自动 markExpired 并降级 Free
 *  - past_due 超过 grace_until → 自动 markSuspended
 *  - 调度：建议每小时 1 次（app/Console/Kernel.php）
 *
 * php artisan subs:grace-sweep
 */
final class SubsGraceSweepCommand extends Command
{
    protected $signature = 'subs:grace-sweep {--limit=500}';

    protected $description = '扫描到期订阅和 grace 期过期订阅，自动降级或暂停';

    public function handle(SubscriptionService $subs): int
    {
        $result = JobRunner::run('subs_grace_sweep', function () use ($subs) {
            $now = now();
            $limit = (int) $this->option('limit');

            $expiredRows = DB::table('subscriptions')
                ->whereIn('status', [SubscriptionService::STATUS_ACTIVE, SubscriptionService::STATUS_TRIALING])
                ->where('plan_code', '<>', 'free')
                ->where(function ($query) use ($now): void {
                    $query->whereNull('current_period_end')
                        ->orWhere('current_period_end', '<=', $now);
                })
                ->limit($limit)
                ->get(['id', 'user_id']);

            $expired = 0;
            foreach ($expiredRows as $r) {
                $subs->markExpired((int) $r->user_id, (int) $r->id);
                $expired++;
            }

            $remaining = max(0, $limit - $expired);
            $rows = DB::table('subscriptions')
                ->where('status', SubscriptionService::STATUS_PAST_DUE)
                ->whereNotNull('grace_until')
                ->where('grace_until', '<', $now)
                ->limit($remaining)
                ->get(['id', 'user_id']);
            $suspended = 0;
            foreach ($rows as $r) {
                $subs->markSuspended($r->user_id);
                $suspended++;
            }
            return ['expired' => $expired, 'suspended' => $suspended];
        });
        if (! $result['ok']) {
            $this->error('subs:grace-sweep failed');
            return self::FAILURE;
        }
        $this->info(sprintf(
            'subs:grace-sweep ok — expired=%d suspended=%d',
            $result['expired'] ?? 0,
            $result['suspended'] ?? 0,
        ));
        return self::SUCCESS;
    }
}
