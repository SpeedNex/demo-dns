# 10 - NextDNS Lite V1 计费模型

> 本文件是 V1 商业模式的主规则。目标是参考 NextDNS 的简单订阅结构，不设计复杂的 DNS 按量计费。所有财务金额、发票、支付、退款、账务流水仍以 `specs/portal-web/billing-finance.md` 为准。

## 1. 设计原则

```text
低版本优先复制成熟模式，不做计费创新。
用户理解成本必须低。
财务计算必须简单、可审计、可对账。
DNS 查询量用于 Free 限额和统计，不用于 Pro/Business 的逐量扣费。
```

V1 禁止实现：

```text
按每百万 DNS 查询自动收费
超额后自动补扣
按 resolver 节点资源收费
按 heartbeat / metrics 扣费
ClickHouse 直接生成财务账单
Redis 保存财务事实
resolver 直接调用财务系统
Free 超额后仍提供"关闭过滤"的任何中间态（无降级路径）
```

## 2. 套餐结构

| code | 名称 | 计费方式 | DNS 查询量 | 设备 | 配置/Profile | 支持 |
|---|---|---|---|---|---|---|
| `free` | Free | 免费 | 300,000 queries/month | unlimited | unlimited | 社区 / 自助 |
| `pro` | Pro | 固定月付或年付 | unlimited | unlimited | unlimited | 社区 / 自助 |
| `business` | Business | 每 50 名员工一个订阅单位 | unlimited | unlimited | unlimited | 邮件支持 |
| `education` | Education | 每 250 名学生一个订阅单位 | unlimited | unlimited | unlimited | 邮件支持 |
| `enterprise` | Enterprise | 合同价 | custom / unlimited | custom | custom | SLA / 私有化 / 专属支持 |

默认参考价格以 `plan_prices` 数据为准，禁止在代码中硬编码展示价格。

推荐默认种子：

| plan | interval | unit_amount_minor | currency | 计费单位 |
|---|---|---:|---|---|
| free | month | 0 | USD | account |
| pro | month | 199 | USD | account |
| pro | year | 1990 | USD | account |
| business | month | 1990 | USD | employee_block_50 |
| business | year | 19900 | USD | employee_block_50 |
| education | month | 1990 | USD | student_block_250 |
| education | year | 19900 | USD | student_block_250 |

## 3. Free 超出额度后的行为

Free 用户每月可使用 300,000 次受保护 DNS 查询。超出后**严格匹配，无降级路径**：

```text
resolver 在 DNS 协议层硬拒绝，对超额 Free 用户的查询直接返回 SERVFAIL / REFUSED；
不执行 classic_dns 降级模式；
不执行广告拦截、安全过滤、家长控制、自定义规则；
不写详细查询日志；
不向上游递归转发。
```

禁止：

```text
超出 Free 后自动扣费
超出 Free 后生成 overage invoice line
超出 Free 后按 DNS 查询量收费
```

## 4. Pro / Business / Education 行为

付费订阅有效时：

```text
monthly_query_limit = null
quota_status = unlimited
```

这表示 resolver 不需要因 query count 限制关闭过滤。系统仍需统计 query count，用于：

```text
用户统计页
Top 域名 / Top blocked
容量规划
异常流量风控
企业审计
```

但这些统计不能自动生成按量账单。

## 5. Business / Education 的人数单位计费

Business：

```text
计费单位 = employee_block_50
员工数 1 - 50  => quantity = 1
员工数 51 - 100 => quantity = 2
员工数 101 - 150 => quantity = 3
```

Education：

```text
计费单位 = student_block_250
学生数 1 - 250 => quantity = 1
学生数 251 - 500 => quantity = 2
学生数 501 - 750 => quantity = 3
```

计算公式：

```text
block_quantity = ceil(seat_count / block_size)
line_subtotal_minor = unit_amount_minor * block_quantity
line_total_minor = line_subtotal_minor - discount_amount_minor + tax_amount_minor
```

必须使用整数计算。`ceil` 对整数实现：

```text
block_quantity = (seat_count + block_size - 1) // block_size
```

## 6. 财务数据完整性

即使 V1 不做按量扣费，财务数据仍必须完整：

```text
plans
plan_prices
billing_accounts
subscriptions
orders
order_items
invoices
invoice_lines
payments
refunds
credit_notes
billing_ledger_entries
payment_webhook_events
reconciliation_runs
reconciliation_items
usage_records
usage_counters
```

其中：

```text
orders / invoices / payments / refunds / ledger = 财务事实
usage_records / usage_counters = 查询量事实，用于 Free quota 和报表，不直接生成收费明细
ClickHouse dns_logs = 日志分析事实，不是财务事实
```

## 7. 用量闭环

```text
dns-resolver 处理 DNS 查询
  -> 批量上报 query logs 到 dns-console-web
  -> dns-console-web 写 ClickHouse
  -> dns-console-web usage worker 聚合 query_count
  -> dns-console-web 调用 portal-web /api/v1/internal/usage/batches
  -> portal-web 幂等写 usage_records / usage_counters
  -> portal-web 判断 Free 是否超过 300,000
  -> portal-web 生成 quota snapshot
  -> dns-console-web 将 quota 放入 resolver config
  -> dns-resolver 执行 classic_dns 降级或 unlimited 正常策略
```

## 8. 月度重置

每个 billing account 必须有 `billing_cycle_anchor` 或 subscription period。

Free：

```text
每月 UTC 自然月重置 usage_counters
period_month = UTC 月第一天
query_count 达到 300,000 后进入 quota_exceeded
下月自动恢复 protected mode
```

Pro / Business / Education：

```text
以 subscription current_period_start / current_period_end 为账期
计费由订阅续费生成，不由 query_count 生成
```

## 9. 验收标准

必须通过以下验收：

1. Free 用户 query_count < 300,000 时执行过滤。
2. Free 用户 query_count >= 300,000 后 resolver 降级为 classic_dns。
3. Free 超额不产生 invoice line、不产生 payment、不产生 ledger charge。
4. Pro 用户 query_count 任意增长时不触发 quota 限制。
5. Business 51 名员工时 quantity = 2，金额 = unit_amount_minor * 2。
6. Education 251 名学生时 quantity = 2，金额 = unit_amount_minor * 2。
7. 所有金额均使用 `amount_minor bigint`，禁止 float / double。
8. payment webhook 重放不会重复入账。
9. usage batch 重放不会重复增加 usage_counters。
10. refund 累计成功金额不能超过原 payment 成功金额。
