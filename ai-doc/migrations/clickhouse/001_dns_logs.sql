-- ClickHouse migration draft for DNS logs.
-- Resolver does not write ClickHouse directly; dns-console-web log worker writes this table.

CREATE TABLE IF NOT EXISTS dns_logs (
    event_id            String,
    timestamp           DateTime64(3, 'UTC'),
    event_date          Date MATERIALIZED toDate(timestamp),
    expires_at          DateTime('UTC'),
    retention_days      UInt16,
    profile_id          String,
    user_id             String,
    team_id             Nullable(String),
    device_id           Nullable(String),
    domain              String,
    domain_hash         String,
    query_type          LowCardinality(String),
    action              LowCardinality(String),
    reason              LowCardinality(String),
    category            LowCardinality(String),
    rule_id             Nullable(String),
    node_id             String,
    node_region         LowCardinality(String),
    node_country        LowCardinality(String),
    client_ip_hash      String,
    latency_ms          UInt16,
    upstream            Nullable(String),
    rcode               LowCardinality(String),
    profile_version     UInt64,
    cache_hit           UInt8,
    protocol            LowCardinality(String),
    ingest_batch_id     String,
    inserted_at         DateTime64(3, 'UTC') DEFAULT now64(3)
)
ENGINE = ReplacingMergeTree(inserted_at)
PARTITION BY toYYYYMM(event_date)
ORDER BY (profile_id, event_date, event_id)
TTL expires_at
SETTINGS index_granularity = 8192;

CREATE MATERIALIZED VIEW IF NOT EXISTS dns_minute_usage
ENGINE = SummingMergeTree()
PARTITION BY toYYYYMM(minute)
ORDER BY (profile_id, minute)
AS SELECT
    toStartOfMinute(timestamp) AS minute,
    profile_id,
    user_id,
    count() AS query_count,
    sum(if(action = 'blocked', 1, 0)) AS blocked_count,
    sum(cache_hit) AS cache_hit_count,
    sum(latency_ms) AS latency_sum_ms
FROM dns_logs
GROUP BY minute, profile_id, user_id;

CREATE MATERIALIZED VIEW IF NOT EXISTS dns_daily_top_domains
ENGINE = SummingMergeTree()
PARTITION BY toYYYYMM(day)
ORDER BY (profile_id, day, action, query_count)
AS SELECT
    toDate(timestamp) AS day,
    profile_id,
    domain,
    action,
    count() AS query_count
FROM dns_logs
GROUP BY day, profile_id, domain, action;
