-- ============================================================================
-- 007_clickhouse_stats.sql
-- ClickHouse 统计聚合表
-- ============================================================================
-- ClickHouse 适合：写入密集、宽表聚合、时序分析
-- 原始日志 dns_logs 在 001 中已创建

-- ============================================================================
-- 1. 每小时统计表 dns_hourly_stats
-- ============================================================================
CREATE TABLE IF NOT EXISTS dns_hourly_stats (
    hour                     DateTime('UTC'),
    profile_id               String,
    user_id                  String,
    team_id                  Nullable(String),
    node_id                  String,

    -- 基础计数
    query_count              UInt64,
    blocked_count            UInt64,
    allowed_count            UInt64,

    -- 性能指标
    latency_p50_ms           Float32,
    latency_p95_ms           Float32,
    latency_p99_ms           Float32,
    avg_latency_ms           Float32,

    -- 命中率
    cache_hit_count          UInt64,
    cache_hit_rate           Float32,

    -- 上游统计
    upstream_failure_count   UInt64,
    upstream_avg_latency_ms  Float32,

    -- 分类统计
    security_block_count     UInt64,
    privacy_block_count      UInt64,
    parental_block_count     UInt64,

    -- DNS 状态码分布 (简化为 top5)
    rcode_nxdomain_count     UInt64,
    rcode_servfail_count     UInt64,
    rcode_refused_count      UInt64,

    inserted_at              DateTime64(3, 'UTC') DEFAULT now64(3)
)
ENGINE = SummingMergeTree()
PARTITION BY toYYYYMM(hour)
ORDER BY (profile_id, user_id, team_id, node_id, hour)
TTL hour + INTERVAL 90 DAY
SETTINGS index_granularity = 8192;

-- ============================================================================
-- 2. 每日统计表 dns_daily_stats
-- ============================================================================
CREATE TABLE IF NOT EXISTS dns_daily_stats (
    day                      Date('UTC'),
    profile_id               String,
    user_id                  String,
    team_id                  Nullable(String),

    -- 基础计数
    query_count              UInt64,
    blocked_count            UInt64,
    allowed_count            UInt64,

    -- 性能指标
    avg_latency_ms           Float32,
    max_latency_ms           UInt32,

    -- 命中率
    cache_hit_rate           Float32,

    -- 分类统计
    security_block_count     UInt64,
    privacy_block_count      UInt64,
    parental_block_count      UInt64,

    -- Unique client 计数 (近似)
    unique_client_ips        UInt64,

    -- Top 域名数量 (近似)
    unique_domains           UInt64,

    inserted_at              DateTime64(3, 'UTC') DEFAULT now64(3)
)
ENGINE = SummingMergeTree()
PARTITION BY toYYYYMM(day)
ORDER BY (profile_id, user_id, team_id, day)
TTL day + INTERVAL 365 DAY
SETTINGS index_granularity = 8192;

-- ============================================================================
-- 3. 域名统计表 dns_domain_stats
-- ============================================================================
CREATE TABLE IF NOT EXISTS dns_domain_stats (
    day                      Date('UTC'),
    profile_id               String,
    user_id                  String,

    -- 域名 (一级域名或二级域名)
    domain                   String,

    -- 统计
    query_count              UInt64,
    blocked_count            UInt64,

    -- 分类
    category                 LowCardinality(String),
    is_blocked               UInt8,

    -- 客户端分布 (top client ip)
    top_client_ip            String,
    top_client_ip_count      UInt64,

    inserted_at              DateTime64(3, 'UTC') DEFAULT now64(3)
)
ENGINE = SummingMergeTree()
PARTITION BY toYYYYMM(day)
ORDER BY (profile_id, user_id, day, domain)
TTL day + INTERVAL 180 DAY
SETTINGS index_granularity = 8192;

-- ============================================================================
-- 4. 地区统计表 dns_geo_stats
-- ============================================================================
CREATE TABLE IF NOT EXISTS dns_geo_stats (
    hour                     DateTime('UTC'),
    profile_id               String,
    user_id                  String,

    -- 地理位置
    country                  LowCardinality(String),
    city                     LowCardinality(String),
    isp                      LowCardinality(String),
    asn                      UInt32,

    -- 统计
    query_count              UInt64,
    blocked_count            UInt64,

    -- 性能
    avg_latency_ms           Float32,

    inserted_at              DateTime64(3, 'UTC') DEFAULT now64(3)
)
ENGINE = SummingMergeTree()
PARTITION BY toYYYYMM(hour)
ORDER BY (profile_id, user_id, country, hour)
TTL hour + INTERVAL 180 DAY
SETTINGS index_granularity = 8192;

-- ============================================================================
-- 5. 节点统计表 dns_node_stats
-- ============================================================================
CREATE TABLE IF NOT EXISTS dns_node_stats (
    hour                     DateTime('UTC'),
    node_id                  String,
    node_region              LowCardinality(String),

    -- 负载统计
    query_count              UInt64,
    blocked_count            UInt64,

    -- 性能
    avg_latency_ms           Float32,
    p95_latency_ms           Float32,

    -- 命中率
    cache_hit_rate           Float32,

    -- 上游
    upstream_failure_count   UInt64,
    upstream_timeout_count   UInt64,

    -- 配置版本
    config_version           UInt64,

    inserted_at              DateTime64(3, 'UTC') DEFAULT now64(3)
)
ENGINE = SummingMergeTree()
PARTITION BY toYYYYMM(hour)
ORDER BY (node_id, hour)
TTL hour + INTERVAL 90 DAY
SETTINGS index_granularity = 8192;

-- ============================================================================
-- 6. 设备统计表 dns_device_stats
-- ============================================================================
CREATE TABLE IF NOT EXISTS dns_device_stats (
    day                      Date('UTC'),
    device_id                String,
    profile_id                String,
    user_id                  String,

    -- 设备信息
    device_name              Nullable(String),
    device_type              Nullable(String),

    -- 统计
    query_count              UInt64,
    blocked_count            UInt64,

    -- 最后活跃
    last_seen_at             DateTime('UTC'),

    inserted_at              DateTime64(3, 'UTC') DEFAULT now64(3)
)
ENGINE = SummingMergeTree()
PARTITION BY toYYYYMM(day)
ORDER BY (device_id, profile_id, day)
TTL day + INTERVAL 365 DAY
SETTINGS index_granularity = 8192;

-- ============================================================================
-- 7. Materialized View: 从 dns_logs 聚合到 hourly_stats
-- ============================================================================
CREATE MATERIALIZED VIEW IF NOT EXISTS mv_dns_hourly_stats
ENGINE = SummingMergeTree()
PARTITION BY toYYYYMM(hour)
ORDER BY (profile_id, user_id, team_id, node_id, hour)
FOR SELECT
    toStartOfHour(timestamp) AS hour,
    profile_id,
    user_id,
    team_id,
    node_id,
    count() AS query_count,
    sum(if(action = 'blocked', 1, 0)) AS blocked_count,
    sum(if(action = 'allowed', 1, 0)) AS allowed_count,
    quantileExact(0.50)(latency_ms) AS latency_p50_ms,
    quantileExact(0.95)(latency_ms) AS latency_p95_ms,
    quantileExact(0.99)(latency_ms) AS latency_p99_ms,
    avg(latency_ms) AS avg_latency_ms,
    sum(cache_hit) AS cache_hit_count,
    avg(cache_hit) AS cache_hit_rate,
    sum(if(rcode != 'NOERROR', 1, 0)) AS upstream_failure_count,
    avg(if(upstream != '', latency_ms, NULL)) AS upstream_avg_latency_ms,
    sum(if(category = 'security', 1, 0)) AS security_block_count,
    sum(if(category = 'privacy', 1, 0)) AS privacy_block_count,
    sum(if(category = 'parental', 1, 0)) AS parental_block_count,
    sum(if(rcode = 'NXDOMAIN', 1, 0)) AS rcode_nxdomain_count,
    sum(if(rcode = 'SERVFAIL', 1, 0)) AS rcode_servfail_count,
    sum(if(rcode = 'REFUSED', 1, 0)) AS rcode_refused_count
FROM dns_logs
GROUP BY hour, profile_id, user_id, team_id, node_id;

-- ============================================================================
-- 8. Materialized View: 从 dns_logs 聚合到 daily_stats
-- ============================================================================
CREATE MATERIALIZED VIEW IF NOT EXISTS mv_dns_daily_stats
ENGINE = SummingMergeTree()
PARTITION BY toYYYYMM(day)
ORDER BY (profile_id, user_id, team_id, day)
FOR SELECT
    toDate(timestamp) AS day,
    profile_id,
    user_id,
    team_id,
    count() AS query_count,
    sum(if(action = 'blocked', 1, 0)) AS blocked_count,
    sum(if(action = 'allowed', 1, 0)) AS allowed_count,
    avg(latency_ms) AS avg_latency_ms,
    max(latency_ms) AS max_latency_ms,
    avg(cache_hit) AS cache_hit_rate,
    sum(if(category = 'security', 1, 0)) AS security_block_count,
    sum(if(category = 'privacy', 1, 0)) AS privacy_block_count,
    sum(if(category = 'parental', 1, 0)) AS parental_block_count,
    uniqExact(client_ip_hash) AS unique_client_ips,
    uniqExact(query_name) AS unique_domains
FROM dns_logs
GROUP BY day, profile_id, user_id, team_id;

-- ============================================================================
-- 统计表用途说明
-- ============================================================================
-- dns_hourly_stats   : 实时监控仪表盘, 90天保留
-- dns_daily_stats    : 日报表, 365天保留
-- dns_domain_stats   : 域名分析, 180天保留
-- dns_geo_stats      : 地理位置分析, 180天保留
-- dns_node_stats     : 节点性能分析, 90天保留
-- dns_device_stats   : 设备统计, 365天保留
-- ============================================================================