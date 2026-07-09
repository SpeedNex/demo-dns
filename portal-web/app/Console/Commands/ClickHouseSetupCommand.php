<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Infrastructure\ClickHouse\ClickHouseClient;
use Illuminate\Console\Command;

/**
 * Create ClickHouse tables if they do not exist.
 *
 * Usage:
 *   php artisan clickhouse:setup
 *
 * Idempotent: uses CREATE TABLE IF NOT EXISTS, safe to re-run.
 * Works for both local and remote ClickHouse (config via .env CLICKHOUSE_HOST).
 */
class ClickHouseSetupCommand extends Command
{
    protected $signature = 'clickhouse:setup';
    protected $description = 'Create ClickHouse tables (usage_events, dns_logs) if not exist';

    public function handle(ClickHouseClient $client): int
    {
        if (! $client->ping()) {
            $this->error('ClickHouse is not reachable. Check CLICKHOUSE_HOST in .env.');
            return 1;
        }

        $this->info('Creating ClickHouse tables...');

        // 2026-07-09: usage_events 使用 MergeTree 引擎（替代 SummingMergeTree）。
        // SummingMergeTree 后台合并可能永不触发，导致 INSERT 成功但数据不可见。
        // MergeTree 写入后立即可见，配额统计由 PHP 侧 usage:aggregate 汇总。
        $client->sendRaw(<<<SQL
CREATE TABLE IF NOT EXISTS usage_events (
    timestamp DateTime64(3),
    user_id String,
    profile_id UInt32,
    device_id UInt64,
    billing_category LowCardinality(String)
) ENGINE = MergeTree()
ORDER BY (user_id, profile_id, toDate(timestamp))
SQL);

        $this->info('  ✓ usage_events');

        $client->sendRaw(<<<SQL
CREATE TABLE IF NOT EXISTS dns_logs (
    event_time DateTime64(3),
    profile_id String,
    user_id String,
    domain String,
    action LowCardinality(String),
    client_ip_hash String,
    device_id String,
    protocol LowCardinality(String),
    dnssec LowCardinality(String),
    reason String,
    country LowCardinality(String),
    city String,
    inserted_at DateTime64(3) DEFAULT now64(3)
) ENGINE = MergeTree()
PARTITION BY toYYYYMM(event_time)
ORDER BY (event_time, profile_id)
SQL);

        $this->info('  ✓ dns_logs');

        $this->info('Done. ClickHouse tables ready.');

        return 0;
    }
}
