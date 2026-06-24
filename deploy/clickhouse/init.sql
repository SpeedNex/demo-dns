-- ClickHouse Initialization Script for OcerDNS

CREATE DATABASE IF NOT EXISTS ocer_dns;

-- ============================================================
-- DNS Query Logs
-- ============================================================

CREATE TABLE IF NOT EXISTS ocer_dns.dns_logs
(
    event_id   String,

    event_time DateTime64(3),

    user_id    String,
    profile_id String,
    device_id  String,
    node_id    String,

    domain     String,

    query_type LowCardinality(String),
    action     LowCardinality(String),
    reason     LowCardinality(String),
    protocol   LowCardinality(String),

    client_ip  String,

    rcode      UInt16,
    latency_ms Float32
)
ENGINE = MergeTree
PARTITION BY toYYYYMM(event_time)
ORDER BY (event_time, profile_id)
TTL event_time + INTERVAL 90 DAY
SETTINGS index_granularity = 8192;


-- ============================================================
-- Usage Events
-- ============================================================

CREATE TABLE IF NOT EXISTS ocer_dns.usage_events
(
    timestamp        DateTime64(3),

    user_id          String,
    profile_id       String,
    device_id        String,

    billing_category LowCardinality(String),

    requests_count   UInt64 DEFAULT 1
)
ENGINE = SummingMergeTree(requests_count)
PARTITION BY toYYYYMM(timestamp)
ORDER BY
(
    user_id,
    profile_id,
    toDate(timestamp),
    billing_category
)
TTL timestamp + INTERVAL 365 DAY
SETTINGS index_granularity = 8192;


-- ============================================================
-- Secondary Indexes
-- ============================================================

ALTER TABLE ocer_dns.dns_logs
    ADD INDEX IF NOT EXISTS idx_domain
    domain
    TYPE bloom_filter(0.01)
    GRANULARITY 2;

ALTER TABLE ocer_dns.dns_logs
    ADD INDEX IF NOT EXISTS idx_client_ip
    client_ip
    TYPE bloom_filter(0.01)
    GRANULARITY 2;

ALTER TABLE ocer_dns.dns_logs
    ADD INDEX IF NOT EXISTS idx_action
    action
    TYPE set(100)
    GRANULARITY 2;

ALTER TABLE ocer_dns.dns_logs
    ADD INDEX IF NOT EXISTS idx_profile
    profile_id
    TYPE bloom_filter(0.01)
    GRANULARITY 2;