# 09 - 闭环与数据落点

> 本文件定义 portal-web、dns-resolver、geodns、Redis、ClickHouse、MySQL 之间的闭环。生成代码时不得改变这些方向。

## 1. 核心原则

```text
dns-resolver 只负责 DNS 执行和上报。
portal-web(原 console 域) 是 resolver 的唯一 API 对接系统。
portal-web 是用户业务和财务的唯一归属。
Redis 是短期状态，不是事实库。
ClickHouse 是 DNS 日志分析库，不是财务库。
MySQL 是业务、财务、控制面事实库。
```

## 2. resolver 数据上报方向

`dns-resolver` 只能调用 `portal-web` 的 Agent API：

| 数据 | 接口 | 用途 | 后续落点 |
|---|---|---|---|
| 节点凭据 | `resolver install` 一次性写入 `configs/server.yaml`；不存在 resolver 侧注册端点 | 节点凭据三元组 | portal-web MySQL（仅存 hash） |
| 心跳 | `POST /api/v1/node/nodes/heartbeat` | 在线、状态、版本、配置版本 | node_heartbeats + nodes 表更新 |
| 配置拉取 | `GET /api/v1/node/resolver/config` | 获取配置包 | 从 portal-web 读取 config_versions |
| 配置 ACK | `POST /api/v1/node/resolver/config/ack` | 确认配置应用结果 | portal-web MySQL |
| 查询日志 | `POST /api/v1/node/query-logs/batch` | DNS 查询日志 | ClickHouse + ingest batch MySQL |

禁止：

```text
dns-resolver -> Redis
dns-resolver -> ClickHouse
dns-resolver -> MySQL
dns-resolver -> portal-web
dns-resolver -> payment provider
```

## 3. Redis 使用层

Redis 用于控制面短期状态：

```text
resolver 节点最新心跳
resolver online/offline 状态
节点健康视图
GeoDNS 调度视图
配置发布短态
幂等锁
限流计数
Web session / cache
```

Redis 写入方：

```text
portal-web
```

Redis 读取方：

```text
portal-web
geodns
```

禁止 Redis 保存：

```text
订单事实
发票事实
支付事实
退款事实
账务流水事实
长期 DNS 日志事实
```

## 4. ClickHouse 使用层

ClickHouse 用于 OLAP 日志分析：

```text
DNS query logs
blocked logs
top domains
top blocked domains
profile analytics
device analytics
node analytics
latency reports
```

写入方：

```text
portal-web log worker
```

读取方：

```text
portal-web 日志/统计页面
portal-web 节点分析页面
运营分析任务
```

禁止 ClickHouse 保存或生成：

```text
订单
发票
支付
退款
Credit Note
Ledger
自动扣费单
```

## 5. MySQL 使用层

portal-web MySQL：

```text
users
profiles
profile_rules
devices
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
usage_records
usage_counters
payment_webhook_events
reconciliation_runs
reconciliation_items
audit_logs
```

portal-web(原 console 域) MySQL：

```text
nodes
node_tokens
node_heartbeats
config_versions
publish_tasks
task_executions
quota_snapshots
query_log_ingest_batches
metric_ingest_batches
sync_failures
```

## 6. portal-web Member 域与 Console 域通信

### 6.1 portal-web Member 域 -> Console 域

| 场景 | 接口 | 鉴权 | 说明 |
|---|---|---|---|
| 发布 Profile 配置 | `POST /api/v1/internal/profile-publishes` | HMAC / mTLS | 用户修改规则后发布配置；请求体中 `quota` 字段随包下发到 `quota_snapshots` |
| 查询发布状态 | `GET /api/v1/admin/publishes?profile_id=...`（portal-web 内部） | adminBearer | V1 走 portal-web 主动轮询 |
| 查询 GeoDNS 健康视图 | `GET /api/v1/internal/geodns/health-view` | HMAC / mTLS | 管理后台展示节点调度状态 |
| 反向拉取 query log | `GET /api/v1/internal/query-logs?user_id=...` | HMAC / mTLS | portal-web 拉取自身用户日志 |
| 反向拉取 analytics | `GET /api/v1/internal/query-analytics?user_id=...` | HMAC / mTLS | portal-web 拉取自身用户聚合 |

发布配置必须包含 quota：

```json
{
  "quota": {
    "plan_code": "free",
    "monthly_query_limit": 300000,
    "used_query_count": 299000,
    "quota_status": "normal",
    "log_retention_days": 90
  }
}
```

Pro / Business / Education：

```json
{
  "quota": {
    "plan_code": "pro",
    "monthly_query_limit": null,
    "used_query_count": 123456789,
    "quota_status": "unlimited",
    "log_retention_days": 90
  }
}
```

### 6.2 Console 域 -> portal-web Member 域

> V1 **不实现** push 形态的「Console 域 → portal-web Member 域」反向写入。
> V1 由 portal-web 主动反向拉取（§6.1 末两行），并在 portal-web 自身派生
> `usage_records` / `usage_counters`。
>
> V2+ 评估的 push 端点（**当前不存在**）：
>
> - `POST /api/v1/internal/usage/batches`（计费用量 push）
> - `POST /api/v1/internal/publish-status/callback`（发布状态 push）
> - `POST /api/v1/internal/quota/snapshots`（portal 下发 quota snapshot）

## 7. NextDNS Lite 计费闭环

```text
1. 用户注册
   -> portal-web 创建 Free subscription
   -> query_limit_monthly = 300000

2. 用户创建 Profile / 规则
   -> portal-web 保存业务数据
   -> portal-web 调用 Console 域发布配置

3. resolver 拉取配置
   -> 得到 plan_code=free、monthly_query_limit、used_query_count、quota_status

4. resolver 处理 DNS 查询
   -> protected mode（quota_status=normal）：执行过滤并写日志
   -> rejected mode（quota_status=exceeded）：DNS 协议层硬拒绝返回 SERVFAIL

5. resolver 批量上报 query logs
   ->5. portal-web 写 ClickHouse
   -> usage worker 聚合 query count

6. portal-web 上报 usage batch
   -> portal-web 幂等累加 usage_counters

7. Free 达到 300,000
   -> portal-web 生成 quota_status=exceeded 状态
   ->8. portal-web 更新 config version  -> resolver 拉取后切换到 rejected mode（硬拒绝 SERVFAIL）

8. 用户升级 Pro
   -> portal-web 创建订单/发票/支付
   -> payment succeeded 后 subscription active
   -> quota 变为 quota_status=unlimited，monthly_query_limit=null
   -> resolver 恢复 protected mode
```

## 8. 幂等要求

```text
query log batch：batch_id 唯一
usage batch：source + source_batch_id + profile_id 唯一
payment webhook：provider + provider_event_id 唯一
order creation：Idempotency-Key 唯一
refund creation：Idempotency-Key 唯一
ledger entry：idempotency_key 唯一
```

## 9. 错误处理

| 故障 | 行为 |
|---|---|
| portal-web 暂时不可用 | resolver 本地 buffer query logs，超过阈值落盘 |
| ClickHouse 不可用 | portal-web 保留 ingest batch 状态为 accepted/failed，重试写入 |
| portal-web usage API 不可用 | usage worker 重试，不得丢弃 usage batch |
| Redis 不可用 | geodns 使用最近内存快照并降低 TTL |
| payment webhook 重复 | portal-web 幂等忽略重复事件 |
| usage batch 重复 | portal-web 不重复累加 counters |


## 会员中心功能闭环补充

`portal-web` 会员中心的安全、隐私、家长监护、黑名单、白名单、设置都属于 Profile 配置草案。用户发布后才进入 `portal-web(原 console 域)` 的配置版本，并最终进入 `dns-resolver` 本地内存配置。统计和日志从 ClickHouse 读取；会员/套餐/订单/发票/支付/退款从 MySQL 财务事实表读取。

```text
安全/隐私/家长/黑白名单/设置
  -> portal-web MySQL
  -> profile_versions.config_json
  -> portal-web(原 console 域) config_versions
  -> resolver config bundle
  -> dns-resolver 执行

日志/统计
  -> dns-resolver query logs
  -> portal-web(原 console 域)
  -> ClickHouse
  -> portal-web 只读展示

会员/套餐
  -> portal-web MySQL 财务事实表
  -> quota snapshot
  -> portal-web(原 console 域)
  -> resolver config
```
