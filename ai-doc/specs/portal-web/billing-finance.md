# portal-web 财务与计费规格 — NextDNS Lite V1

> 本文件是财务数据和计费逻辑的主规格。V1 按 NextDNS-like 低复杂度模型实现：Free 300,000 queries/month，Pro unlimited queries，Business 按 50 名员工 block，Education 按 250 名学生 block，Enterprise 合同价。V1 不实现 DNS 查询按量收费和自动超额扣费。

## 1. 核心边界

```text
portal-web = 财务事实归属

dns-console-web = resolver 数据入口和用量聚合方

dns-resolver = DNS 执行和日志/指标/心跳上报方
```

禁止：

```text
dns-resolver 直接创建订单/发票/支付/退款
dns-console-web 直接修改订单/发票/支付/退款/账务流水
ClickHouse 作为财务账本
Redis 作为财务账本
metrics / heartbeat 作为计费事实
DNS 查询量自动生成 Pro/Business 超额账单
```

## 2. 金额强约束

### 2.1 金额表示

- 所有金额字段必须使用最小货币单位整数：`amount_minor bigint`。
- 禁止在数据库、后端、接口和前端金额计算中使用 `float` / `double`。
- 每条金额记录必须包含 `currency char(3)`。
- 每张订单、发票、支付、退款只能使用一个币种。
- 跨币种必须拆单或生成独立结算记录。

示例：

```text
USD 1.99  -> amount_minor = 199, currency = USD
USD 19.90 -> amount_minor = 1990, currency = USD
JPY 1000  -> amount_minor = 1000, currency = JPY
```

### 2.2 舍入规则

- 统一使用 `ROUND_HALF_UP`。
- 税率使用 basis points：`tax_rate_bps`，10000 = 100%。
- 计算顺序固定：

```text
line_subtotal = unit_amount_minor * quantity
line_discount = fixed_discount + percentage_discount
line_taxable = line_subtotal - line_discount
line_tax = round_half_up(line_taxable * tax_rate_bps / 10000)
line_total = line_taxable + line_tax
invoice_total = sum(line_total)
amount_due = invoice_total - amount_paid_minor - credit_applied_minor
```

### 2.3 财务单据不可变

- 发票 `finalized_at` 非空后不得修改金额字段。
- 成功支付不得删除，只能通过退款或 Credit Note 修正。
- 成功退款累计金额不得超过原支付成功金额。
- Ledger entry 不得 update/delete。
- Payment webhook 必须幂等。

## 3. 套餐模型

### 3.1 plans

| 字段 | 类型 | 约束 | 说明 |
|---|---|---|---|
| id | uuid | pk | 套餐 ID |
| code | varchar(50) | unique | `free` / `pro` / `business` / `education` / `enterprise` |
| name | varchar(100) | not null | 套餐名称 |
| billing_model | varchar(40) | not null | `free_quota` / `flat_subscription` / `employee_block` / `student_block` / `custom_contract` |
| status | varchar(30) | not null | active / archived |
| profile_limit | integer | null | null 表示 unlimited |
| device_limit | integer | null | null 表示 unlimited |
| query_limit_monthly | bigint | null | Free=300000；付费 unlimited 用 null |
| quota_status | varchar(30) | not null | `normal` / `exceeded` / `unlimited` |
| monthly_query_limit | bigint | null | Free 套餐月查询上限；付费套餐为 null |
| log_retention_days | integer | not null | 默认日志保留；V1 不作为主要价格差异 |
| block_size | integer | null | Business=50，Education=250 |
| unit_label | varchar(40) | not null | account / employee_block_50 / student_block_250 / contract |
| features | jsonb | not null | 功能开关；V1 各套餐功能尽量一致 |
| created_at | timestamptz | not null | 创建时间 |
| updated_at | timestamptz | not null | 更新时间 |

语义：

```text
query_limit_monthly = 300000, quota_status=normal/exceeded  -> Free
query_limit_monthly = null, quota_status=unlimited  -> Pro/Business/Education/Enterprise
profile_limit = null / device_limit = null           -> unlimited
```

### 3.2 plan_prices

| 字段 | 类型 | 约束 | 说明 |
|---|---|---|---|
| id | uuid | pk | 价格 ID |
| plan_id | uuid | fk plans.id | 套餐 |
| billing_interval | varchar(20) | month / year | 账期 |
| currency | char(3) | fk currencies.code | 币种 |
| unit_amount_minor | bigint | >=0 | 单位价格 |
| unit_label | varchar(40) | not null | account / employee_block_50 / student_block_250 |
| block_size | integer | null | 人数 block 大小 |
| trial_days | integer | default 0 | 试用天数 |
| status | varchar(30) | active / archived | 状态 |
| effective_from | timestamptz | not null | 生效时间 |
| effective_to | timestamptz | null | 失效时间 |

默认价格种子：

```text
Free monthly: USD 0.00
Pro monthly: USD 1.99
Pro yearly: USD 19.90
Business monthly: USD 19.90 per 50 employees
Business yearly: USD 199.00 per 50 employees
Education monthly: USD 19.90 per 250 students
Education yearly: USD 199.00 per 250 students
Enterprise: manual contract, no public fixed price
```


## 3A. 会员中心 V1 功能入口

套餐功能字段 `plans.features` 必须至少表达以下能力开关：

```json
{
  "security": true,
  "privacy": true,
  "parental": true,
  "allowlist": true,
  "denylist": true,
  "analytics": true,
  "logs": true,
  "settings": true
}
```

V1 的 Free / Pro / Business / Education 默认都开放这些基础功能；差异主要是 Free 有 300,000 queries/month 限制，付费套餐 unlimited queries。

## 4. 订阅和人数 block

### 4.1 subscriptions

| 字段 | 类型 | 说明 |
|---|---|---|
| id | uuid | 订阅 ID |
| billing_account_id | uuid | 账务账户 |
| user_id | uuid | 用户 |
| plan_id | uuid | 套餐 |
| plan_price_id | uuid | 锁定价格 |
| status | varchar(30) | trialing / active / past_due / canceled / expired |
| seat_count | integer | Business 员工数或 Education 学生数；个人套餐为 null |
| block_quantity | integer | 计算后的收费单位数量 |
| current_period_start | timestamptz | 当前周期开始 |
| current_period_end | timestamptz | 当前周期结束 |
| cancel_at_period_end | boolean | 是否周期末取消 |
| provider | varchar(50) | 支付渠道 |
| provider_subscription_id | varchar(255) | 渠道订阅 ID |

Business / Education block 计算：

```text
block_quantity = (seat_count + block_size - 1) // block_size
line_subtotal = unit_amount_minor * block_quantity
```

验收：

```text
Business seat_count=50  -> block_quantity=1
Business seat_count=51  -> block_quantity=2
Education seat_count=250 -> block_quantity=1
Education seat_count=251 -> block_quantity=2
```

## 5. 订单、发票、支付、退款

### 5.1 orders / order_items

`orders` 表示用户要购买什么；`order_items` 保存收费明细。

V1 允许 `item_type`：

```text
subscription
seat_block
manual_adjustment
credit
```

V1 禁止 `item_type`：

```text
usage_overage
query_usage_charge
```

### 5.2 invoices / invoice_lines

`invoices` 表示应收金额。`invoice_lines` 必须记录：

```text
subscription period
plan code
price id
quantity
unit_amount_minor
subtotal_amount_minor
discount_amount_minor
tax_rate_bps
tax_amount_minor
total_amount_minor
```

发票定稿后金额字段不可修改。

### 5.3 payments

`payments` 表示实收金额。Provider webhook 成功后必须：

```text
验证签名
写 payment_webhook_events
幂等更新 payment status
更新 invoice amount_paid / amount_due
写 billing_ledger_entries
激活或续费 subscription
生成 quota snapshot
```

### 5.4 refunds / credit_notes

退款必须满足：

```text
refund.currency = payment.currency
sum(successful_refunds.amount_minor) <= payment.amount_minor
```

已定稿发票金额错误时不能 update 原发票，只能：

```text
退款
credit note
manual adjustment ledger
```

## 6. Usage 数据：只做 quota 和统计，不做按量收费

### 6.1 usage_records

`usage_records` 是 query count 的事实来源。它来自：

```text
dns-console-web usage worker
```

接口：

```http
POST /api/v1/internal/usage/batches
```

唯一约束：

```text
source + source_batch_id + profile_id
```

### 6.2 usage_counters

`usage_counters` 保存月度累计：

```text
user_id
profile_id
period_month
query_count
blocked_count
```

Free quota 判断：

```text
if plan_code == free and monthly_query_limit is not null and query_count >= monthly_query_limit:
    quota_status = exceeded
else:
    quota_status = normal
```

Pro / Business / Education：

```text
monthly_query_limit = null
quota_status = unlimited
```

## 7. Quota Snapshot

portal-web 必须向 dns-console-web 提供最新 quota 状态：

```json
{
  "profile_id": "prf_01H...",
  "user_id": "usr_01H...",
  "plan_code": "free",
  "monthly_query_limit": 300000,
  "used_query_count": 300000,
  "quota_status": "exceeded",
  "log_retention_days": 90,
  "quota_version": 12
}
```

Pro 示例：

```json
{
  "profile_id": "prf_01H...",
  "user_id": "usr_01H...",
  "plan_code": "pro",
  "monthly_query_limit": null,
  "used_query_count": 999999999,
  "quota_status": "unlimited",
  "log_retention_days": 90,
  "quota_version": 13
}
```

## 8. 财务表清单

必须实现：

```text
currencies
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

## 9. 测试要求

1. Pro 月费 USD 1.99 保存为 `amount_minor=199`。
2. Pro 年费 USD 19.90 保存为 `amount_minor=1990`。
3. Business 51 employees 时 quantity=2，总额=1990*2=3980。
4. Education 251 students 时 quantity=2，总额=1990*2=3980。
5. Free query_count 300000 后 quota_status=exceeded。
6. Free 超额不生成 `usage_overage` invoice line。
7. Pro query_count 任意值不触发 quota exceeded。
8. Payment webhook 重放不重复入账。
9. Refund 累计成功金额不得超过 payment 成功金额。
10. Ledger entry 不允许 update/delete。
11. Invoice finalized 后金额字段不可 update。
12. 所有金额测试禁止 float / double。
