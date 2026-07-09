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
 *
 * 注意：以下表结构对齐线上实际部署的 schema（见 ai-doc/specs/clickhouse/tables.md §实际部署版本）。
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

        // usage_events: MergeTree（原 SummingMergeTree 有数据不可见问题，已迁移）
        // 用途：记录用户 DNS 查询用量事件，由 usage:aggregate 汇总到 MySQL usage_records
        $client->sendRaw('', <<<SQL
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

        // dns_logs: 实际部署版本（简化版，Resolver 上报的 DNS 查询日志）
        $client->sendRaw('', <<<SQL
CREATE TABLE IF NOT EXISTS dns_logs (
    event_id String,
    event_time DateTime64(3),
    user_id String,
    profile_id String,
    device_id String,
    node_id String,
    domain String,
    query_type LowCardinality(String),
    action LowCardinality(String),
    reason LowCardinality(String),
    protocol LowCardinality(String),
    client_ip String,
    rcode UInt16,
    latency_ms Float32,
    INDEX idx_domain domain TYPE bloom_filter(0.01) GRANULARITY 2,
    INDEX idx_client_ip client_ip TYPE bloom_filter(0.01) GRANULARITY 2,
    INDEX idx_action action TYPE set(100) GRANULARITY 2,
    INDEX idx_profile profile_id TYPE bloom_filter(0.01) GRANULARITY 2
) ENGINE = MergeTree()
PARTITION BY toYYYYMM(event_time)
ORDER BY (event_time, profile_id)
TTL event_time + toIntervalDay(90)
SQL);

        $this->info('  ✓ dns_logs');

        $this->info('Done. ClickHouse tables ready.');

        return 0;
    }
}
