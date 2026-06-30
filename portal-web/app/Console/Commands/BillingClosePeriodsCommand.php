<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Billing\BillingPeriodCloseService;
use Illuminate\Console\Command;

/**
 * 2026-06-30: 每日 00:00 关闭已结束 billing_period。
 *
 * 链路：
 *   billing:close-periods (00:00)  → open → closed
 *   billing:generate      (00:30)  → closed → billed
 */
final class BillingClosePeriodsCommand extends Command
{
    protected $signature = 'billing:close-periods';

    protected $description = '关闭已过 period_end 的 open 账期，为账单生成做前置准备';

    public function handle(BillingPeriodCloseService $service): int
    {
        $result = $service->closeExpiredPeriods();
        $this->info(sprintf(
            'billing period close ok (scanned=%d, closed=%d, ids=%s)',
            $result['scanned'],
            $result['closed'],
            $result['closed'] > 0 ? implode(',', $result['closed_ids']) : '-'
        ));
        return self::SUCCESS;
    }
}
