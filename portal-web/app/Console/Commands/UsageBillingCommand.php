<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Billing\UsageBillingService;
use Illuminate\Console\Command;

/**
 * UI.md #67 — usage 聚合调度。
 * php artisan usage:aggregate
 * php artisan billing:generate
 */
final class UsageBillingCommand extends Command
{
    protected $signature = 'usage:aggregate {--since=}';

    protected $description = '从 ClickHouse usage_events 聚合到 PostgreSQL usage_records';

    public function handle(UsageBillingService $service): int
    {
        $result = $service->aggregateOnce($this->option('since'));
        if (! $result['ok']) {
            $this->error('usage aggregation failed (consecutive_failures=' . $result['consecutive_failures'] . ')');
            return self::FAILURE;
        }
        $this->info('usage aggregation ok');
        return self::SUCCESS;
    }
}
