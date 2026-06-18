<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Billing\UsageBillingService;
use Illuminate\Console\Command;

/**
 * UI.md #70 — usage 账单生成调度。
 * php artisan billing:generate
 */
final class BillingGenerateCommand extends Command
{
    protected $signature = 'billing:generate';

    protected $description = '为已关闭的 billing_period 生成 usage 类型账单';

    public function handle(UsageBillingService $service): int
    {
        $result = $service->generateBillingsForClosedPeriods();
        if (! $result['ok']) {
            $this->error('billing generation failed (consecutive_failures=' . $result['consecutive_failures'] . ')');
            return self::FAILURE;
        }
        $this->info('billing generation ok');
        return self::SUCCESS;
    }
}
