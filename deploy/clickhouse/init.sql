-- ClickHouse Initialization Script for OcerDNS
-- This script creates the necessary tables for DNS query logging and analytics

CREATE DATABASE IF NOT EXISTS ocer_dns;

-- DNS Query Logs Table (main query logging)
CREATE TABLE IF NOT EXISTS ocer_dns.dns_logs (
    event_id String,
    timestamp DateTime64(3),
    profile_id String,
    node_id String,
    protocol String,
    query_domain String,
    query_type UInt16,
    response_code UInt16,
    blocked Bool,
    block_reason Nullable(String),
    upstream_server String,
    response_time_ms Float32,
    client_ip String,
    device_id Nullable(String),
    country_code Nullable(String),
    region Nullable(String)
) ENGINE = MergeTree()
PARTITION BY toYYYYMM(timestamp)
ORDER BY (profile_id, timestamp)
TTL timestamp + INTERVAL 30 DAY
SETTINGS index_granularity = 8192;

-- DNS Query Log Batches Table (for batch ingestion tracking)
CREATE TABLE IF NOT EXISTS ocer_dns.dns_query_log_batches (
    batch_id String,
    profile_id String,
    node_id String,
    records_count UInt32,
    forwarded_to_clickhouse Bool,
    created_at DateTime,
    forwarded_at Nullable(DateTime),
    error_message Nullable(String)
) ENGINE = MergeTree()
ORDER BY (batch_id, created_at)
SETTINGS index_granularity = 8192;

-- Aggregated Stats Table (for daily/hourly rollups)
CREATE TABLE IF NOT EXISTS ocer_dns.aggregated_stats (
    period_start DateTime,
    period_type Enum8('hourly' = 1, 'daily' = 2),
    profile_id String,
    node_id String,
    query_count UInt64,
    blocked_count UInt64,
    allowed_count UInt64,
    avg_response_time_ms Float32,
    unique_domains UInt64,
    unique_ips UInt64
) ENGINE = SummingMergeTree()
ORDER BY (period_start, period_type, profile_id, node_id)
SETTINGS index_granularity = 8192;

-- Top Domains Analytics Table
CREATE TABLE IF NOT EXISTS ocer_dns.top_domains (
    date Date,
    profile_id String,
    domain String,
    query_count UInt64,
    blocked_count UInt64,
    unique_clients UInt64
) ENGINE = MergeTree()
PARTITION BY toYYYYMM(date)
ORDER BY (date, profile_id, query_count DESC)
SETTINGS index_granularity = 8192;

-- Create materialized view for real-time stats
CREATE MATERIALIZED VIEW IF NOT EXISTS ocer_dns.dns_stats_mv
ENGINE = SummingMergeTree()
ORDER BY (timestamp, profile_id, node_id)
AS SELECT
    toStartOfHour(timestamp) as timestamp,
    profile_id,
    node_id,
    count() as query_count,
    sumIf(1, blocked) as blocked_count,
    avg(response_time_ms) as avg_response_time_ms
FROM ocer_dns.dns_logs
GROUP BY timestamp, profile_id, node_id;

-- Create index for faster domain lookups
ALTER TABLE ocer_dns.dns_logs ADD INDEX idx_domain query_type TYPE bloom_filter GRANULARITY 1;

-- Create index for client IP lookups
ALTER TABLE ocer_dns.dns_logs ADD INDEX idx_client_ip client_ip TYPE set(1000) GRANULARITY 1;
