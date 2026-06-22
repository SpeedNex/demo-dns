<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Infrastructure\ClickHouse\ClickHouseClient;
use App\Models\QueryLogIngestBatch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * 2026-06-22 — ClickHouse 补传任务。
 *
 * 扫描 dns_query_log_ingest_batches 中 status='partial' 且 forwarded_to_clickhouse=0
 * 的批次，重新从 dns_query_log_entries 读取原始 items 并批量 insert 到 ClickHouse dns_logs。
 *
 * 设计要点（最小修改原则）：
 *   1. 不新增 retry_count 字段 — 复用 status 与 error_message 计数
 *   2. 批次从 raw_payload 读 fallback，dns_query_log_entries 才是主源
 *   3. 每批失败追加 [retry N] 前缀到 error_message，>5 次标 status='failed' 永不再试
 *   4. 默认每次处理 50 批（limit 1000 行），可用 --batch=N 调整
 *
 * 用法：
 *   php artisan clickhouse:retry-failed-batches
 *   php artisan clickhouse:retry-failed-batches --batch=200 --limit=2000
 */
final class ClickHouseRetryFailedBatchesCommand extends Command
{
    protected $signature = 'clickhouse:retry-failed-batches
        {--batch=50 : 每批处理的最大 batch 数量}
        {--limit=1000 : 单个 batch 最多回填的行数}';

    protected $description = '补传 forwarded_to_clickhouse=0 的 partial 批次到 ClickHouse';

    public function handle(): int
    {
        $batchLimit = (int) $this->option('batch');
        $rowLimit = (int) $this->option('limit');
        $client = new ClickHouseClient();

        // 2026-06-22: 单条 ping 失败直接退出，避免无意义循环
        if (! $client->ping()) {
            $this->error('ClickHouse ping failed — aborting');

            return self::FAILURE;
        }

        $batches = QueryLogIngestBatch::query()
            ->where('status', 'partial')
            ->where('forwarded_to_clickhouse', false)
            ->orderBy('id')
            ->limit($batchLimit)
            ->get();

        if ($batches->isEmpty()) {
            $this->info('No partial batches to retry');

            return self::SUCCESS;
        }

        $succeeded = 0;
        $failed = 0;
        $gaveUp = 0;

        foreach ($batches as $batch) {
            $retryCount = $this->extractRetryCount($batch->error_message);

            // 2026-06-22: dns_query_log_entries 不再写入。CH 重试数据源改为
            // dns_query_log_ingest_batches.raw_payload（上报时存的原始 items JSON）。
            $rawItems = $batch->raw_payload;
            if (! is_array($rawItems) || $rawItems === []) {
                // 历史批次（迁移前）没有 raw_payload → 无法重试
                $batch->update([
                    'status' => 'failed',
                    'error_message' => 'no raw_payload to retry (original: ' . substr((string) $batch->error_message, 0, 200) . ')',
                    'forwarded_to_clickhouse' => false,
                    'updated_at' => now(),
                ]);
                $gaveUp++;
                continue;
            }

            $items = array_slice($rawItems, 0, $rowLimit);

            $dnsLogs = [];
            foreach ($items as $it) {
                $queriedAt = isset($it['queried_at'])
                    ? now()->setTimestamp((int) $it['queried_at'])->format('Y-m-d H:i:s')
                    : (isset($it['ts']) ? (string) $it['ts'] : now()->format('Y-m-d H:i:s'));
                $queryName = strtolower((string) ($it['query_name'] ?? $it['domain'] ?? ''));
                $domain = strtolower((string) ($it['domain'] ?? $it['query_name'] ?? ''));
                $dnsLogs[] = [
                    'event_time' => $queriedAt,
                    'timestamp' => $queriedAt,
                    'node_id' => (string) ($batch->node_id ?? ''),
                    'user_id' => (string) ($it['user_id'] ?? ''),
                    'profile_id' => (string) ($it['profile_id'] ?? ''),
                    'device_id' => (string) ($it['device_id'] ?? ''),
                    'query_name' => $queryName,
                    'domain' => $domain,
                    'query_type' => strtoupper((string) ($it['query_type'] ?? 'A')),
                    'action' => strtoupper((string) ($it['action'] ?? 'ALLOW')),
                    'reason' => (string) ($it['reason'] ?? ''),
                    'category' => (string) ($it['category'] ?? ''),
                    'client_ip' => (string) ($it['client_ip'] ?? ''),
                    'rcode' => (int) ($it['rcode'] ?? 0),
                    'latency_ms' => (int) ($it['latency_ms'] ?? 0),
                    // raw_payload 通常含 protocol；缺失时置空
                    'protocol' => strtolower((string) ($it['protocol'] ?? '')),
                ];
            }

            if ($dnsLogs === []) {
                $batch->update([
                    'status' => 'failed',
                    'error_message' => 'raw_payload has no convertible items (original: ' . substr((string) $batch->error_message, 0, 200) . ')',
                    'forwarded_to_clickhouse' => false,
                    'updated_at' => now(),
                ]);
                $gaveUp++;
                continue;
            }

            try {
                $client->insertJsonEachRow('dns_logs', $dnsLogs);
                $batch->update([
                    'status' => 'succeeded',
                    'forwarded_to_clickhouse' => true,
                    'error_message' => null,
                    'processed_at' => now(),
                    'updated_at' => now(),
                ]);
                $succeeded++;
            } catch (\Throwable $e) {
                $newCount = $retryCount + 1;
                $newMsg = sprintf('[retry %d] %s', $newCount, $e->getMessage());
                $giveUp = $newCount >= 5;
                $batch->update([
                    'status' => $giveUp ? 'failed' : 'partial',
                    'error_message' => substr($newMsg, 0, 500),
                    'forwarded_to_clickhouse' => false,
                    'updated_at' => now(),
                ]);
                if ($giveUp) {
                    $gaveUp++;
                } else {
                    $failed++;
                }
            }
        }

        $this->line(sprintf(
            'batches=%d succeeded=%d still_partial=%d gave_up=%d',
            $batches->count(),
            $succeeded,
            $failed,
            $gaveUp
        ));

        return self::SUCCESS;
    }

    /**
     * 从 error_message 中解析 [retry N] 计数，没有就返回 0。
     */
    private function extractRetryCount(?string $errorMessage): int
    {
        if ($errorMessage === null) {
            return 0;
        }
        if (preg_match('/\[retry (\d+)\]/', $errorMessage, $m) === 1) {
            return (int) $m[1];
        }

        return 0;
    }
}
