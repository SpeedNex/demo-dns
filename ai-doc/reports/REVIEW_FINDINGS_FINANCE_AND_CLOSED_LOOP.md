# 二次审查与修正报告：财务准确性、API 通信、数据闭环

## 1. 审查结论

本次审查发现：当前架构主线已经清晰，但财务/计费规格仍然不足，且 resolver 日志、metrics、Redis、ClickHouse、dns-console-web 的数据落点有少量表达模糊。已在本版本中修正。

## 2. 已修正的关键问题

### 2.1 财务数据不完整

原版本只写了 plans、subscriptions，并把 orders / invoices / payments 放到后续 Stage，不能满足“财务数据完整正确”的要求。

已新增：

```text
specs/portal-web/billing-finance.md
migrations/postgresql/003_billing_finance.sql
contracts/billing.schema.json
contracts/examples/billing-usage-batch.sample.json
```

并明确：

- 金额统一用 `amount_minor bigint`。
- 禁止 float/double 参与金额计算。
- 使用 `ROUND_HALF_UP`。
- 发票定稿后不可修改金额。
- 支付、退款、webhook、usage batch 全部幂等。
- 财务事实进入 `billing_ledger_entries`。

### 2.2 portal-web 与 dns-console-web 通信方向不完整

原版本主要描述了 `portal-web → dns-console-web` 发布配置，但没有把 `dns-console-web → portal-web` 的用量上报和发布状态回调写成明确接口。

已新增闭环：

```text
dns-console-web → portal-web /api/v1/internal/usage/batches
dns-console-web → portal-web /api/v1/internal/publish-status/callback
```

### 2.3 resolver 数据上报落点有歧义

原版本有 “NATS / HTTP batch / ClickHouse” 的表达，容易被误解成 resolver 可以直接写 ClickHouse。

已明确：

```text
MVP：dns-resolver 只上报到 dns-console-web Agent API。
规模化：可以写 NATS，但仍由 worker 写 ClickHouse / portal usage。
禁止：dns-resolver 直接连接 PostgreSQL / Redis / ClickHouse。
```

### 2.4 计费用量来源不够严谨

原版本的 metrics 与 billing.usage 表达可能导致用指标数据扣费。

已明确：

```text
心跳、metrics 只用于运维，不用于扣费。
计费用量只从 query-log batch 或其派生的精确 usage_records 生成。
```

### 2.5 ClickHouse TTL 与套餐保留冲突

原版本表级 TTL 固定 90 天，无法支持 Enterprise 365 天。

已改为：

```text
日志行写入 retention_days 和 expires_at
ClickHouse TTL expires_at
```

### 2.6 ClickHouse 聚合视图存在统计风险

原版本把 `avg()` / `quantile()` 直接写入 `SummingMergeTree`，长期合并后可能不准确。

已改为：

```text
MVP 物化视图只保存可累加的 usage counts。
延迟分位数作为查询时统计或后续 AggregatingMergeTree 实现。
```

## 3. 本版本必须遵守的生成约束

1. `portal-web` 拥有财务主数据。
2. `dns-console-web` 不实现订单、发票、支付、退款主流程。
3. `dns-resolver` 不直接连接 Redis / ClickHouse / PostgreSQL。
4. Redis 不是事实库。
5. ClickHouse 不是财务账本。
6. usage billing 不从 heartbeat / metrics 扣费。
7. 所有金额用最小货币单位整数。
8. 所有财务写接口必须幂等。
9. 所有 Internal API 必须 HMAC/mTLS + nonce 防重放。
10. 财务计算必须有单元测试、属性测试和对账测试。

## 4. 二次补强项

本轮额外补强：

- `migrations/postgresql/003_billing_finance.sql` 新增发票金额不可变 trigger：`finalized_at` 非空后，币种、小计、折扣、税额、总额不得被 UPDATE。
- `migrations/postgresql/003_billing_finance.sql` 新增成功退款上限 trigger：同一 payment 的成功退款累计金额不得超过 payment 成功金额，且币种必须一致。
- `migrations/postgresql/002_dns_console_web_mvp.sql` 新增 `query_log_ingest_batches.content_sha256`、`usage_exported_at` 和 `metric_ingest_batches`，确保日志、指标、用量导出状态可追踪。
- `contracts/examples/query-log.sample.json` 新增 `event_id`，与 ClickHouse `dns_logs.event_id`、query log schema 一致。
- 已消除 “resolver 直接写 ClickHouse” 或 “metrics 直接扣费” 的歧义表达；规模化 NATS 入口也必须归 `dns-console-web` ingestion 管理。

## 5. 剩余边界说明

文档已经把财务计算规则、数据库约束、接口幂等和闭环落点写成强约束，但真正“不会出现计算错误”还取决于后续代码是否严格实现这些规格并通过测试。生成代码时必须把 `specs/portal-web/billing-finance.md`、`migrations/postgresql/003_billing_finance.sql`、`contracts/billing.schema.json` 和 `project-doc/06-CLOSED-LOOP-AND-DATA-DESTINATIONS.md` 设为最高优先级输入。
