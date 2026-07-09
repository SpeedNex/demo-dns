<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Infrastructure\ClickHouse\ClickHouseClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ClickHouseRetryCommand extends Command
{
    protected $signature = 'clickhouse:retry-failed-batches
                            {--limit=50 : 每次最多重试多少条}
                            {--max-retry=5 : 最大重试次数，超过后跳过}';

    protected $description = 'Retry failed ClickHouse batches from failed_ch_batches table';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $maxRetry = (int) $this->option('max-retry');

        $batches = DB::table('failed_ch_batches')
            ->whereNull('resolved_at')
            ->where('retry_count', '<', $maxRetry)
            ->orderBy('created_at')
            ->limit($limit)
            ->get();

        if ($batches->isEmpty()) {
            $this->info('No failed batches to retry.');
            return 0;
        }

        $clickhouse = app(ClickHouseClient::class);
        $success = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($batches as $batch) {
            try {
                $dnsLogs = json_decode($batch->dns_logs, true) ?? [];
                $usageEvents = json_decode($batch->usage_events, true) ?? [];

                if ($dnsLogs !== []) {
                    $clickhouse->insertJsonEachRow('dns_logs', $dnsLogs);
                }
                if ($usageEvents !== []) {
                    $clickhouse->insertJsonEachRow('usage_events', $usageEvents);
                }

                DB::table('failed_ch_batches')
                    ->where('id', $batch->id)
                    ->update([
                        'resolved_at' => now(),
                        'retry_count' => $batch->retry_count + 1,
                        'last_retried_at' => now(),
                    ]);

                $success++;
            } catch (\Throwable $e) {
                DB::table('failed_ch_batches')
                    ->where('id', $batch->id)
                    ->update([
                        'retry_count' => $batch->retry_count + 1,
                        'last_retried_at' => now(),
                        'error_message' => substr($e->getMessage(), 0, 512),
                    ]);

                Log::warning('ClickHouse retry failed', [
                    'batch_id' => $batch->batch_id,
                    'retry_count' => $batch->retry_count + 1,
                    'error' => $e->getMessage(),
                ]);

                $failed++;
            }
        }

        // Count skipped (exceeded max retry)
        $skipped = DB::table('failed_ch_batches')
            ->whereNull('resolved_at')
            ->where('retry_count', '>=', $maxRetry)
            ->count();

        $this->info("Retry complete: {$success} succeeded, {$failed} failed, {$skipped} skipped (exceeded max retry).");
        return 0;
    }
}
