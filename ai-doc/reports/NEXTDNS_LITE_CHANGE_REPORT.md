# NextDNS Lite V1 改版报告

## 改版目标

根据最新产品方向，本版本从“套餐 + DNS 查询额度 + 可选超额收费”的复杂模型，收敛为参考 NextDNS 的低版本模型：

```text
Free：300,000 queries/month，超出后 resolver 在 DNS 协议层硬拒绝返回 SERVFAIL，无降级路径。
Pro：固定月费/年费，unlimited queries。
Business：按 50 employees block 订阅，unlimited queries。
Education：按 250 students block 订阅，unlimited queries。
Enterprise：合同价。
```

## 已修改的核心文件

```text
README.md
START.md
REQUIREMENTS.md
project-doc/00-GOAL.md
project-doc/01-ARCHITECTURE.md
project-doc/02-MODULES.md
project-doc/03-DATA-FLOW.md
project-doc/04-MVP-SCOPE.md
project-doc/06-CLOSED-LOOP-AND-DATA-DESTINATIONS.md
project-doc/07-NEXTDNS-LITE-BILLING.md
specs/portal-web/api.md
specs/portal-web/billing-finance.md
specs/portal-web/data-schema.md
specs/dns-console-web/api.md
specs/dns-console-web/data-model.md
specs/dns-resolver/data-model.md
specs/clickhouse/tables.md
contracts/billing.schema.json
contracts/resolver-config.schema.json
contracts/openapi.yaml
contracts/examples/*
migrations/postgresql/001_portal_web_mvp.sql
migrations/postgresql/002_dns_console_web_mvp.sql
migrations/postgresql/003_billing_finance.sql
```

## 关键约束

```text
不做 DNS 查询按量计费。
不做自动超额扣费。
Query Count 只用于 Free quota、统计展示、风控、容量规划。
财务金额仍完整使用整数 amount_minor。
resolver 只通过 dns-console-web API 上报。
dns-console-web 再落 Redis / ClickHouse / PostgreSQL。
portal-web 是财务事实主系统。
```

## 数据闭环

```text
dns-resolver
  -> dns-console-web Agent API
  -> Redis：心跳/健康/调度短态
  -> ClickHouse：DNS 查询日志和分析
  -> PostgreSQL：控制面元数据
  -> portal-web Internal Usage API：query count
  -> portal-web PostgreSQL：usage_records / usage_counters / billing facts
  -> dns-console-web quota snapshot
  -> resolver config
```
