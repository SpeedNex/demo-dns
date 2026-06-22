<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Member\ProfilePublishApplicationService;
use App\Domain\Profile\ProfileConfigBuilder;
use App\Domain\Publish\PublishService;
use App\Models\Profile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * quota:check — 检测所有活跃套餐的查询用量是否超过月度限额。
 *
 * 执行逻辑：
 * 1. 查询所有 status=active 的 subscription，按 user_id 分组
 * 2. 对每个用户，聚合当前 billing period 的 usage_records 总查询量
 * 3. 与 plan.monthly_query_limit 比较
 * 4. 超额 → subscription.quota_status = 'exceeded' → 重新发布该用户所有 Profile
 * 5. 恢复（新周期用量回落）→ quota_status = 'normal' → 重新发布
 *
 * 建议通过 cron 每5分钟执行一次：
 *   *\/5 * * * * cd /path && php artisan quota:check >> storage/logs/quota-check.log 2>&1
 */
class QuotaCheckCommand extends Command
{
    protected $signature = 'quota:check';
    protected $description = 'Check usage quotas and update quota_status';

    public function handle(): int
    {
        $this->info('Quota check started...');
        $checked = 0;
        $republished = 0;

        // 获取所有 status=active 且有 monthly_query_limit 的订阅
        // 左联 plans 表获取 monthly_query_limit
        $subscriptions = DB::table('subscriptions')
            ->join('plans', 'subscriptions.plan_id', '=', 'plans.id')
            ->where('subscriptions.status', 'active')
            ->whereNotNull('plans.monthly_query_limit')
            ->where('plans.monthly_query_limit', '>', 0)
            ->select([
                'subscriptions.id',
                'subscriptions.user_id',
                'subscriptions.quota_status',
                'plans.monthly_query_limit',
            ])
            ->get();

        if ($subscriptions->isEmpty()) {
            $this->info('No active subscriptions with query limits found.');
            return 0;
        }

        foreach ($subscriptions as $sub) {
            $checked++;
            $this->line("  user={$sub->user_id} plan_limit={$sub->monthly_query_limit} current_status={$sub->quota_status}");

            // 获取当前 billing period 的累计用量
            $used = $this->getCurrentPeriodUsage((int) $sub->user_id);

            $exceeded = $used >= (int) $sub->monthly_query_limit;

            // 计算目标状态
            $targetStatus = $exceeded ? 'exceeded' : 'normal';

            // 如果状态无变化，跳过
            if ($sub->quota_status === $targetStatus) {
                $this->line("    status unchanged ({$targetStatus}), skip.");
                continue;
            }

            // 更新 subscription.quota_status
            DB::table('subscriptions')
                ->where('id', $sub->id)
                ->update([
                    'quota_status' => $targetStatus,
                    'updated_at' => now(),
                ]);

            $this->info("    status changed: {$sub->quota_status} → {$targetStatus}");

            // 触发该用户所有 Profile 重新发布
            $profiles = Profile::where('user_id', $sub->user_id)->get(['profile_uid']);
            if ($profiles->isNotEmpty()) {
                $service = app(ProfilePublishApplicationService::class);
                foreach ($profiles as $profile) {
                    try {
                        $service->publishForUser((string) $sub->user_id, $profile->profile_uid);
                        $republished++;
                        $this->line("    republished profile={$profile->profile_uid}");
                    } catch (\Throwable $e) {
                        $this->error("    republish failed for profile={$profile->profile_uid}: {$e->getMessage()}");
                        logger()->error("quota:check republish failed", [
                            'user_id' => $sub->user_id,
                            'profile_uid' => $profile->profile_uid,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }
        }

        $this->info("Quota check completed: {$checked} subscriptions checked, {$republished} profiles republished.");
        return 0;
    }

    /**
     * 获取用户在当前 billing period 的累计查询量。
     * 取最近一个 billing_periods.status=open 或 closed 但未 billed 的记录。
     */
    private function getCurrentPeriodUsage(int $userId): int
    {
        // 查找当前 open 的 billing period
        $period = DB::table('billing_periods')
            ->where('user_id', $userId)
            ->where('status', 'open')
            ->orderByDesc('period_start')
            ->first();

        if ($period === null) {
            // 无开放周期，尝试最近 closed 的
            $period = DB::table('billing_periods')
                ->where('user_id', $userId)
                ->where('status', 'closed')
                ->orderByDesc('period_end')
                ->first();
        }

        if ($period === null) {
            return 0;
        }

        // 聚合 usage_records 中该周期的查询量
        $result = DB::table('usage_records')
            ->where('user_id', $userId)
            ->where('billing_period_id', $period->id)
            ->selectRaw('COALESCE(SUM(query_count), 0) as total')
            ->first();

        return (int) ($result->total ?? 0);
    }
}