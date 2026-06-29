# ClickHouse 日志表规格

> ClickHouse 用于 DNS 查询日志和聚合分析。MVP 至少实现 `dns_logs`。财务计费用量的最终写入在 `portal-web` 的 `usage_records` / `usage_counters`，ClickHouse 只作为日志和分析库。
>
> **容量规划**（批次大小、压缩格式、TTL、分区、扩容路径）见 [`capacity.md`](./capacity.md)；表结构变更前必须先读该文件第 12 节的容量变更审查清单。

## 1. 日志保留策略

套餐建议：

| plan | log_retention_days |
|---|---:|
| free | 7 |
| pro | 90 |
| enterprise | 365 |

为了避免固定 TTL 与套餐冲突，日志行写入时必须带：

```text
retention_days
expires_at
```

ClickHouse TTL 使用 `expires_at`。`expires_at` 由 log worker 根据 profile quota / plan 计算，resolver 不可信任为保留策略来源。

## 2. 原始日志表

```sql
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
```

说明：

- `event_id` 由 resolver 生成，建议为 `node_id + batch_id + sequence` 的稳定 hash 或 ULID。
- API 层仍必须使用 `batch_id + content_sha256` 幂等，ClickHouse 不承担唯一约束职责。
- `ReplacingMergeTree` 只能降低重复行影响；财务用量必须以幂等 usage_records 为准。

## 3. 常用查询

```text
profile_id + time range
profile_id + action + time range
profile_id + domain keyword/hash
device_id + time range
node_id + time range
```

## 4. 分钟用量聚合视图

只保存可累加计数，避免把 `avg()` / `quantile()` 直接写入 `SummingMergeTree` 后长期合并不准确。

```sql
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
```

平均延迟查询时计算：

```sql
latency_avg_ms = latency_sum_ms / query_count
```

P95/P99 延迟后续使用 `AggregatingMergeTree + quantileTDigestState` 实现，不在 MVP 中用错误聚合替代。

## 5. Top 域名视图

```sql
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

## 6. 隐私要求

- 默认不保存明文 client IP。
- `client_ip_hash` 必须由 resolver 或 ingest 服务使用带 salt 的 hash 生成。
- `domain` 属于敏感上网数据，应限制后台访问权限。
- 用户删除账户后，应通过异步任务删除或匿名化其日志。
- 导出日志必须写审计记录。


## NextDNS Lite V1 边界

ClickHouse 只存 DNS 查询日志、拦截日志和统计分析数据。ClickHouse 不保存订单、发票、支付、退款或账务流水，也不能直接生成 DNS 查询按量收费。V1 的 query count 由 dns-console-web usage worker 聚合后上报 portal-web，仅用于 Free quota、统计展示、风控和容量规划。
