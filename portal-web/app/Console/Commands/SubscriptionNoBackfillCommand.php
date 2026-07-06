<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * 一次性回填历史订阅的 subscription_no 字段。
 *
 * 背景：2026-06-26 17:57~18:00 期间，dns_subscriptions 表新增 subscription_no 列
 *      的 migration 刚跑完，但 SubscriptionService::create() 生成编号的代码
 *      当时还没上线，导致 sub_id=1~5、6、8、10 等 7 条 active 订阅的
 *      subscription_no 为 NULL。
 *
 * 用法：
 *   php artisan subscriptions:backfill-no              # 默认 dry-run
 *   php artisan subscriptions:backfill-no --apply      # 真正写入
 *   php artisan subscriptions:backfill-no --only=1,2,3 # 仅处理指定 id
 *
 * 编号格式（与 SubscriptionService::create() 一致）：
 *   SUB-{created_at YmdHis}-{6位大写字母数字}
 */
final class SubscriptionNoBackfillCommand extends Command
{
    protected $signature = 'subscriptions:backfill-no {--apply : 真正写入 DB} {--only= : 仅处理指定 id（逗号分隔）}';

    protected $description = '回填历史 active 订阅缺失的 subscription_no（默认 dry-run，需 --apply 生效）';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $onlyOpt = trim((string) $this->option('only'));
        $onlyIds = $onlyOpt === '' ? [] : array_filter(array_map('intval', explode(',', $onlyOpt)));

        $query = DB::table('subscriptions')
            ->where(function ($q): void {
                $q->whereNull('subscription_no')->orWhere('subscription_no', '');
            })
            ->orderBy('id')
            ->select(['id', 'user_id', 'plan_code', 'status', 'created_at', 'subscription_no']);

        if (! empty($onlyIds)) {
            $query->whereIn('id', $onlyIds);
        }

        $rows = $query->get();
        if ($rows->isEmpty()) {
            $this->info('没有需要回填 subscription_no 的订阅（全部已存在）。');
            return self::SUCCESS;
        }

        $this->line(sprintf('候选 %d 条订阅%s：', $rows->count(), $apply ? '（将执行 UPDATE）' : '（dry-run，不会写入）'));
        $this->table(
            ['id', 'user_id', 'plan_code', 'status', 'created_at', 'new_subscription_no'],
            $rows->map(function ($r): array {
                return [
                    $r->id,
                    $r->user_id,
                    $r->plan_code ?? '-',
                    $r->status,
                    (string) $r->created_at,
                    $this->generateNo($r->created_at),
                ];
            })->all(),
        );

        if (! $apply) {
            $this->warn('当前为 dry-run，未写入任何数据。加 --apply 真正执行。');
            return self::SUCCESS;
        }

        $updated = 0;
        $skipped = 0;
        $conflicts = [];

        DB::transaction(function () use ($rows, &$updated, &$skipped, &$conflicts): void {
            foreach ($rows as $r) {
                // 二次校验：避免唯一索引冲突
                $newNo = $this->generateNo($r->created_at);
                $exists = DB::table('subscriptions')
                    ->where('subscription_no', $newNo)
                    ->where('id', '<>', $r->id)
                    ->exists();
                if ($exists) {
                    $conflicts[] = sprintf('id=%d proposed=%s 已被占用', $r->id, $newNo);
                    $skipped++;
                    continue;
                }

                DB::table('subscriptions')
                    ->where('id', $r->id)
                    ->update([
                        'subscription_no' => $newNo,
                        'updated_at' => now(),
                    ]);
                $updated++;
            }
        });

        foreach ($conflicts as $c) {
            $this->error($c);
        }
        $this->info(sprintf('回填完成 updated=%d skipped=%d apply=%s', $updated, $skipped, $apply ? 'yes' : 'no'));

        return self::SUCCESS;
    }

    /**
     * 与 SubscriptionService::create() 保持一致的编号格式：
     *   SUB-{YmdHis}-{6位大写随机}
     */
    private function generateNo(string|\DateTimeInterface $createdAt): string
    {
        $ts = $createdAt instanceof \DateTimeInterface
            ? $createdAt->format('YmdHis')
            : date('YmdHis', strtotime((string) $createdAt));
        return 'SUB-' . $ts . '-' . strtoupper(Str::random(6));
    }
}
