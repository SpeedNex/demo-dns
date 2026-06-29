# ClickHouse 容量规划（capacity.md）

> 目标规模：V1 稳定支持 10,000 用户、每人 5,000 queries/day ≈ **5,000 万条 / 天**（≈ 600 rows/s 平均、峰值 1,500 rows/s）。本文件约束批次大小、压缩格式、TTL、分区、副本和扩容路径，防止 AI 生成的代码把 ClickHouse 写爆或慢到不可用。

## 1. 设计前提（容量估算基线）

| 维度 | V1 目标 | V2+ 阶段 | 来源 |
|---|---|---|---|
| 用户数 | 10,000 | 100,000 | 用户增长模型 |
| 单用户日均查询 | 5,000 | 5,000 | NextDNS 行业均值 |
| 日均行数 | **50,000,000 / day** | 500,000,000 / day | = 用户数 × 单量 |
| 平均 QPS | ≈ 580 rows/s | ≈ 5,800 rows/s | 50M / 86400 |
| 峰值 QPS | 1,500 rows/s | 15,000 rows/s | ≈ 峰值 2.5× |
| 单行平均大小 | ≈ 280 bytes（压缩前） | ≈ 280 bytes | 字段映射（见 §6） |
| 原始写入量 | ≈ 14 GB/day | ≈ 140 GB/day | 50M × 280B |
| 压缩比 | 8 ~ 10×（ZSTD） | 同 | 经验值 |
| 压缩后写入 | **≈ 1.5 GB/day** | ≈ 15 GB/day | 14GB / 10 |
| 单分区日数据量 | ≈ 1.5 GB | — | toYYYYMM(月分区) |
| Free 用户保留 | 7 天 | 同 | 套餐策略 |
| Pro 用户保留 | 90 天 | 同 | 套餐策略 |
| Enterprise 用户保留 | 365 天 | 同 | 套餐策略 |
| 平均并发节点 | 20 ~ 50 | 200+ | resolver 池 |

> 财务计费用量从 MySQL 的 `usage_records` 派生（`portal-web` usage worker），ClickHouse 不参与计费。

## 2. 批次大小（HTTP batch + 落盘 buffer）

### 2.1 resolver → portal-web 上报

| 阶段 | 字段 | 推荐值 | 硬上限 | 禁止值 |
|---|---|---:|---:|---|
| resolver 内存聚合 | `batch_size` | 500 | 1,000 | ≤ 1（等于关闭批量） |
| resolver 强制 flush 间隔 | `flush_interval` | 5s | 15s | ≥ 60s（数据延迟过高） |
| 单 batch 字节大小 | `max_batch_bytes` | 2 MB | 4 MB | 无上限 |
| 落盘 buffer 上限 | `max_buffer_size_mb` | 1,024 MB | 4,096 MB | 不设上限（磁盘爆） |
| HTTP `items[]` | `maxItems` | 1,000 | 1,000 | — |

> 上述值与 `contracts/query-log.schema.json`（`maxItems: 1000`）和 `specs/dns-resolver/data-model.md`（`logging.batch_size: 500`）保持一致，禁止私自调整。

### 2.2 portal-web → ClickHouse 写入

| 阶段 | 字段 | 推荐值 | 硬上限 |
|---|---|---:|---:|
| ingest worker 单批写入 | `clickhouse_batch_rows` | 5,000 | 10,000 |
| ingest worker 单批字节 | `clickhouse_batch_bytes` | 8 MB | 16 MB |
| flush 间隔 | `flush_interval` | 2s | 5s |
| 写表方式 | INSERT 协议 | Native（`clickhouse-go`） | 禁止 HTTP JSON（慢 3×） |
| 写入线程数 | `writer_count` | 4 | 8 |

### 2.3 拥塞控制

- resolver 必须在丢 batch 前先落本地 buffer，**禁止丢弃**。
- buffer 满时按策略：先阻塞 flush 协程（等待 buffer 释放），再触发本地 `query-log-dead-letter.jsonl`。
- 禁止出现"超过 batch_size 直接 drop"的兜底逻辑。

## 3. 压缩格式

| 列族 | 编码 | 压缩 | 理由 |
|---|---|---|---|
| `event_id` | `Delta(8)` + `ZSTD(1)` | ZSTD | 高基数稳定 ID，Delta 编码后极小 |
| `timestamp` | `DoubleDelta` + `ZSTD(1)` | ZSTD | 严格按时间递增，差分压缩比高 |
| `event_date` | 不压缩（`LowCardinality` 即可） | — | 月分区列，低基数 |
| `expires_at` | `Delta(8)` + `ZSTD(1)` | ZSTD | 单调递增 |
| `retention_days` | `ZSTD(1)` | ZSTD | 7/90/365 三档 |
| `profile_id` | `ZSTD(1)` | ZSTD | 高基数 |
| `user_id` | `ZSTD(1)` | ZSTD | 高基数 |
| `team_id` | `LowCardinality(Nullable)` + `ZSTD(1)` | ZSTD | 大多数为 NULL |
| `device_id` | `LowCardinality(Nullable)` + `ZSTD(1)` | ZSTD | 设备级低基数 |
| `domain` | `Delta(4)` + `ZSTD(3)` | ZSTD | 重复域名多，差分+ZSTD 压缩比 ≈ 15× |
| `domain_hash` | `ZSTD(1)` | ZSTD | 高基数 |
| `query_type` | `LowCardinality(String)` + `ZSTD(1)` | ZSTD | A/AAAA/HTTPS 等数个值 |
| `action` | `LowCardinality(String)` + `ZSTD(1)` | ZSTD | 4 个枚举 |
| `reason` | `LowCardinality(String)` + `ZSTD(1)` | ZSTD | 数十枚举 |
| `category` | `LowCardinality(Nullable(String))` + `ZSTD(1)` | ZSTD | 数十枚举 |
| `rule_id` | `LowCardinality(Nullable(String))` + `ZSTD(1)` | ZSTD | 高基数但稀疏 |
| `node_id` | `ZSTD(1)` | ZSTD | 高基数 |
| `node_region` | `LowCardinality(String)` + `ZSTD(1)` | ZSTD | 数十枚举 |
| `node_country` | `LowCardinality(String)` + `ZSTD(1)` | ZSTD | 数百枚举 |
| `client_ip_hash` | `ZSTD(1)` | ZSTD | 64 字符定长 |
| `latency_ms` | `ZSTD(1)` | ZSTD | 整数 |
| `upstream` | `LowCardinality(Nullable(String))` + `ZSTD(1)` | ZSTD | 几个上游 |
| `rcode` | `LowCardinality(String)` + `ZSTD(1)` | ZSTD | 枚举 |
| `profile_version` | `Delta(8)` + `ZSTD(1)` | ZSTD | 单调递增 |
| `cache_hit` | `ZSTD(1)` | ZSTD | 0/1 |
| `protocol` | `LowCardinality(String)` + `ZSTD(1)` | ZSTD | 5 枚举 |
| `ingest_batch_id` | `ZSTD(1)` | ZSTD | 中基数 |
| `inserted_at` | `DoubleDelta` + `ZSTD(1)` | ZSTD | 写入时间，近似单调 |

> 表级默认压缩必须使用 `ZSTD`，不允许默认 `LZ4` 或 `NONE`。V1 验证目标：50M 行/天 ≤ 2 GB / 天磁盘写入。

## 4. TTL 策略

### 4.1 表级 TTL

TTL 与 `expires_at` 字段绑定，由 `portal-web` log worker 在写入前根据用户 plan 计算。**resolver 不可信任为保留策略来源**。

```sql
ALTER TABLE dns_logs
  MODIFY TTL expires_at;
```

### 4.2 expires_at 计算规则（强制）

| Plan | log_retention_days | expires_at = timestamp + |
|---|---:|---|
| free | 7 | 7 days |
| pro | 90 | 90 days |
| business | 90 | 90 days |
| education | 90 | 90 days |
| enterprise | 365 | 365 days |
| unknown / null | 7 | 7 days（保守） |

### 4.3 衰减任务

- TTL 删除由 ClickHouse 后台自动执行，不得依赖人工清理。
- 调度建议：`background_pool_size=16`、`background_schedule_pool_size=16`。
- 监控 `system.parts` 中的 `delete_ttl_info` 字段；删除速率应低于每秒 50,000 行，否则需要扩容 `background_pool_size`。

## 5. 分区策略

### 5.1 主表分区

```sql
PARTITION BY toYYYYMM(event_date)
```

理由：
- 月分区粒度足够覆盖 1.5GB/天的 V1 单分区数据量。
- DROP PARTITION 比行级 DELETE 快 3 个数量级。
- 自由用户 7 天保留自然让分区部分数据在 TTL 后自动清理。

### 5.2 物化视图分区

```sql
-- 分钟用量：按月分区
PARTITION BY toYYYYMM(minute)

-- 每日 Top 域名：按月分区
PARTITION BY toYYYYMM(day)
```

### 5.3 分区裁剪（Partition Pruning）

查询必须显式带时间范围：

```sql
SELECT ...
FROM dns_logs
WHERE profile_id = :pid
  AND event_date >= toDate(:start)
  AND event_date <  toDate(:end)
```

禁止不带时间范围的 `SELECT * FROM dns_logs`，会让 ClickHouse 扫描全部分区。

### 5.4 未来可选：周分区

V2+ 阶段如果单月分区超过 100 GB，再切到 `toYear(event_date) * 100 + toWeekOfYear(event_date)`，并加 Zookeeper 协调。V1 **不** 引入周分区。

## 6. 单行大小约束（与表结构对齐）

| 字段 | 类型 | 平均字节 | 备注 |
|---|---|---:|---|
| `event_id` | String(24) | 24 | ULID |
| `timestamp` | DateTime64(3) | 8 | |
| `expires_at` | DateTime | 4 | |
| `retention_days` | UInt16 | 2 | |
| `profile_id` | String(24) | 24 | ULID |
| `user_id` | String(24) | 24 | ULID |
| `team_id` | Nullable(String(24)) | 12 | 大多数 NULL |
| `device_id` | Nullable(String(24)) | 12 | |
| `domain` | String(60) | 35 | 平均域名长度 |
| `domain_hash` | String(64) | 64 | SHA-256 |
| `query_type` | LowCardinality(String) | 1 | |
| `action` | LowCardinality(String) | 1 | |
| `reason` | LowCardinality(String) | 4 | |
| `category` | LowCardinality(Nullable(String)) | 4 | |
| `rule_id` | LowCardinality(Nullable(String)) | 6 | |
| `node_id` | String(24) | 24 | |
| `node_region` | LowCardinality(String) | 4 | |
| `node_country` | LowCardinality(String) | 2 | |
| `client_ip_hash` | String(64) | 64 | |
| `latency_ms` | UInt16 | 2 | |
| `upstream` | LowCardinality(Nullable(String)) | 8 | |
| `rcode` | LowCardinality(String) | 1 | |
| `profile_version` | UInt64 | 8 | |
| `cache_hit` | UInt8 | 1 | |
| `protocol` | LowCardinality(String) | 1 | |
| `ingest_batch_id` | String(24) | 24 | |
| `inserted_at` | DateTime64(3) | 8 | |
| **合计** | | **≈ 280 bytes** | 压缩前 |

> 任何新增字段需先在 §6 表格中增加字节预算，单行总预算上限 512 bytes（压缩前）。超出必须拆分到附属表。

## 7. 性能基线与 SLO

| 指标 | V1 SLO | 监控告警阈值 |
|---|---|---|
| ingest P99 延迟（dns-resolver → ClickHouse） | ≤ 30s | > 60s |
| 单 partition merge 时间 | ≤ 10 min | > 30 min |
| 查询 P95 延迟（profile + 1 day 范围） | ≤ 1s | > 3s |
| 查询 P95 延迟（profile + 30 day 范围） | ≤ 5s | > 10s |
| 磁盘使用率 | ≤ 70% | > 80% |
| 活跃 part 数（单分区） | ≤ 100 | > 200 |
| 复制队列延迟 | ≤ 30s | > 120s |
| 写入失败率 | ≤ 0.01% | > 0.1% |

## 8. 资源规划

### 8.1 V1 最低资源（10,000 用户 / 50M rows/day）

| 资源 | 规格 | 数量 |
|---|---|---:|
| ClickHouse 节点 | 8 vCPU / 32 GB RAM / 1 TB NVMe | 1（单机） |
| 写入吞吐 | ≥ 1,500 rows/s | — |
| 磁盘预留 | 1 TB 容量 + 30% 缓冲 | — |

### 8.2 V1 副本（可选）

- 启用 `ReplicatedMergeTree` + 1 副本时，资源翻倍但写入吞吐仍受单机限制。
- 副本主要用于高可用，不解决写入瓶颈。
- 副本开关应通过 `portal-web` 配置中心管控，禁止 resolver 端感知。

### 8.3 V2+ 阶段（100,000 用户 / 500M rows/day）

- 引入 3 节点 Sharded + Replicated 集群。
- 分片键：`intHash32(profile_id) % shard_count`。
- 增加冷热分层：Hot（NVMe，0~7 天）/ Warm（HDD，8~90 天）/ Cold（S3/对象存储，91~365 天）。
- 引入 Kafka 替代 NATS（V2+ 内部决策）。

## 9. 监控指标

必须采集：

```text
clickhouse_inserted_rows
clickhouse_inserted_bytes
clickhouse_query_count
clickhouse_query_duration_ms
clickhouse_active_parts
clickhouse_background_pool_tasks
clickhouse_replication_queue_size
clickhouse_disk_used_ratio
clickhouse_zookeeper_session
```

监控接入 Prometheus + Grafana；告警规则写入 `deploy/` 同名文件。**禁止** 自建"健康检查轮询 SELECT 1"。

## 10. 容量扩容决策表

| 触发条件 | 扩容动作 | 实施人 |
|---|---|---|
| 单机磁盘 > 70% | 加挂磁盘 / 清理历史分区 | ops |
| 写入 QPS > 1,500（持续 5 分钟） | 升级单机 vCPU 到 16 / 加 writer 线程 | ops |
| 活跃 part > 200 / partition | 减小 ingest 批次或加大后台 merge 池 | dev + ops |
| Free 用户 7 天数据 + Pro 90 天数据磁盘 > 1 TB | 启用冷热分层 / 缩短 Free TTL / 进入 V2 阶段 | product + ops |
| 查询 P95 > 5s 持续 1 小时 | 启用物化视图加速 / 增加 `index_granularity` | dev |

## 11. 容量规划禁止项

- ❌ 禁止使用 `MergeTree` 替代 `ReplacingMergeTree(inserted_at)`（必须保留 `event_id` 去重语义）。
- ❌ 禁止把 `domain` 设为 `String(255)`，必须限定 ≤ 253（DNS 协议上限）。
- ❌ 禁止在主表上做行级 UPDATE / DELETE（除 TTL 自动删除）。
- ❌ 禁止使用 `Buffer` 表作为长期落盘方案。
- ❌ 禁止不分区（`PARTITION BY tuple()` 等）。
- ❌ 禁止 ORDER BY 不带 `event_date`（否则分区裁剪失效）。
- ❌ 禁止使用 `Dictionary` 替换 `LowCardinality`（字典对 50M rows/day 写入过重）。
- ❌ 禁止把日志容量增长作为计费依据（ClickHouse 不参与计费）。
- ❌ 禁止将 `client_ip_hash` 替换为明文 IP（隐私要求见 `tables.md` §6）。

## 12. 容量变更审查清单

任何修改 `tables.md` / `capacity.md` 的 PR 必须同时回答：

```text
- 单行字节变化量？新总预算是否 ≤ 512 bytes？
- 每日总写入字节变化量？是否仍在 14 GB/day 估算内？
- 是否影响 TTL 自动清理速率？
- 是否引入新枚举（LowCardinality 字典膨胀）？
- 是否需要更新物化视图？
- 是否影响现有查询的分区裁剪？
```

未填完的 PR 不得合并。
