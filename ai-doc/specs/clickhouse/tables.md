# ClickHouse 日志表规格

> ClickHouse 用于 DNS 查询日志和用量事件记录。MVP 至少实现 `dns_logs` 和 `usage_events`。
> 财务计费用量的最终写入在 `portal-web` 的 `usage_records` / `usage_counters`，ClickHouse 只作为日志和分析库。
>
> **容量规划**（批次大小、压缩格式、TTL、分区、扩容路径）见 [`capacity.md`](./capacity.md)。

---

## 实际部署的表结构（线上 ocer_dns 数据库）

以下为线上实际部署的版本，通过 `SHOW CREATE TABLE` 获取。

### 1. usage_events（用量事件表）

```sql
CREATE TABLE ocer_dns.usage_events (
    timestamp DateTime64(3),
    user_id String,
    profile_id UInt32,
    device_id UInt64,
    billing_category LowCardinality(String)
) ENGINE = MergeTree()
ORDER BY (user_id, profile_id, toDate(timestamp))
SETTINGS index_granularity = 8192
```

**字段说明：**

| 字段 | 类型 | 说明 |
|------|------|------|
| timestamp | DateTime64(3) | 事件时间（毫秒精度） |
| user_id | String | 用户 ID（字符串） |
| profile_id | UInt32 | 配置档案 ID |
| device_id | UInt64 | 设备 ID |
| billing_category | LowCardinality(String) | 计费类别（如 query、block） |

**写入方**：`portal-web` 的 `QueryLogController` 通过 `ClickHouseClient::insertJsonEachRow` 写入。
**用途**：由 `UsageBillingService::fetchUsageEvents` 读取，`usage:aggregate` 定时任务汇总到 MySQL `usage_records`。

**引擎说明**：
- 使用 `MergeTree`（2026-07-09 由 `SummingMergeTree` 迁移而来）
- `SummingMergeTree` 后台合并可能永不触发，导致 INSERT 返回 200 但 SELECT 看不到数据
- `MergeTree` 写入后立即可見，聚合统计由 PHP 侧完成

---

### 2. dns_logs（DNS 查询日志表）

```sql
CREATE TABLE ocer_dns.dns_logs (
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
SETTINGS index_granularity = 8192
```

**字段说明：**

| 字段 | 类型 | 说明 |
|------|------|------|
| event_id | String | 事件唯一 ID（UUID，由 PHP 生成） |
| event_time | DateTime64(3) | 查询时间（毫秒精度） |
| user_id | String | 用户 ID |
| profile_id | String | 配置档案 ID |
| device_id | String | 设备 UID |
| node_id | String | 节点 ID |
| domain | String | 查询域名 |
| query_type | LowCardinality(String) | 查询类型（A、AAAA、CNAME 等） |
| action | LowCardinality(String) | 动作（BLOCK、ALLOW 等） |
| reason | LowCardinality(String) | 拦截原因 |
| protocol | LowCardinality(String) | 协议（UDP、TCP、DoH、DoT 等） |
| client_ip | String | 客户端 IP（SHA-256 哈希） |
| rcode | UInt16 | DNS 响应码 |
| latency_ms | Float32 | 延迟（毫秒） |

**索引说明**：
- `idx_domain`：域名 bloom_filter 索引（加速域名查询）
- `idx_client_ip`：客户端 IP bloom_filter 索引
- `idx_action`：动作 set 索引（加速 action = 'BLOCK' 过滤）
- `idx_profile`：档案 ID bloom_filter 索引

**写入方**：`portal-web` 的 `QueryLogController` 通过 `ClickHouseClient::insertJsonEachRow` 写入。
**TTL**：数据保留 90 天自动清理。

---

## 表初始化方式

项目提供 `ClickHouseSetupCommand` 命令用于建表：

```bash
# 在 web 服务器上执行
php artisan clickhouse:setup
```

该命令：
- 使用 `CREATE TABLE IF NOT EXISTS`，幂等，可重复执行
- 通过 `.env` 的 `CLICKHOUSE_HOST` 配置支持远程 ClickHouse
- 实际部署的表结构保存在 `app/Console/Commands/ClickHouseSetupCommand.php`

---

## 设计规格（参考，未完全实现）

以下为最初设计的完整规格，仅供未来扩展参考。

### 设计版 dns_logs（与实际部署有差异）

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

**差异说明**：

| 项目 | 设计规格 | 实际部署 |
|------|----------|----------|
| 引擎 | ReplacingMergeTree(inserted_at) | MergeTree |
| 列数 | 27 列 | 14 列 |
| team_id / rule_id / cache_hit 等 | 有 | 无 |
| 索引 | 无 | 4 个 bloom_filter/set 索引 |
| 默认 TTL | 由 expires_at 字段决定 | event_time + 90 天固定 |

---

## NextDNS Lite V1 边界

ClickHouse 只存 DNS 查询日志和用量事件数据。ClickHouse **不保存**：

- 订单、发票、支付、退款或账务流水
- 用户账户信息（仅存 user_id 引用）
- 配置档案详情（仅存 profile_id 引用）

V1 的 query count 由 `portal-web` 的 usage worker 从 `usage_events` 聚合后上报 MySQL，仅用于 Free quota、统计展示、风控和容量规划。
