-- ClickHouse Initialization Script for OcerDNS
-- 与当前 PHP 代码 (QueryLogController.php) 写入列名保持一致。
-- 第一次部署:clickhouse-client < init.sql
-- 已运行过旧版 init.sql:clickhouse-client < migrate.sql  (idempotent ALTER)

CREATE DATABASE IF NOT EXISTS ocer_dns;

-- DNS Query Logs Table (主查询日志)
-- 字段命名与 QueryLogController.php 写入列保持一致:
--   event_id (UUID,行级删除)
--   event_time / timestamp (DateTime)
--   user_id / profile_id / device_id (查询主体)
--   query_name (被查询的域名)
--   query_type (A/AAAA 等)
--   action (ALLOW / BLOCK)
--   reason (block / default / allowlist)
--   client_ip / rcode / latency_ms / protocol / node_id
CREATE TABLE IF NOT EXISTS ocer_dns.dns_logs (
    event_id   String,
    event_time DateTime64(3),
    timestamp  DateTime64(3),
    user_id    String,
    profile_id String,
    device_id  String,
    node_id    String,
    query_name String,
    query_type LowCardinality(String),
    action     LowCardinality(String),
    reason     LowCardinality(String),
    protocol   LowCardinality(String),
    client_ip  String,
    rcode      UInt16,
    latency_ms Float32
) ENGINE = MergeTree()
PARTITION BY toYYYYMM(event_time)
ORDER BY (profile_id, event_time)
TTL (event_time + INTERVAL 90 DAY)
SETTINGS index_granularity = 8192;

-- Usage Events (计费用,供 UsageBillingService 聚合)
CREATE TABLE IF NOT EXISTS ocer_dns.usage_events (
    timestamp        DateTime64(3),
    user_id          String,
    profile_id       UInt32,
    device_id        UInt64,
    billing_category LowCardinality(String)
) ENGINE = SummingMergeTree()
ORDER BY (user_id, profile_id, toDate(timestamp))
TTL (timestamp + INTERVAL 365 DAY)
SETTINGS index_granularity = 8192;

-- Indexes (用于按域名/IP 查找)
ALTER TABLE ocer_dns.dns_logs ADD INDEX IF NOT EXISTS idx_query_name query_name TYPE bloom_filter(0.01) GRANULARITY 2;
ALTER TABLE ocer_dns.dns_logs ADD INDEX IF NOT EXISTS idx_client_ip  client_ip TYPE bloom_filter(0.01) GRANULARITY 2;
