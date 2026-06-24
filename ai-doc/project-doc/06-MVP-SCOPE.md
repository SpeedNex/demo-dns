# 06 - MVP 范围：NextDNS Lite V1

> MVP 目标不是一次性做完整企业级 DNS SaaS，而是先跑通 NextDNS-like 的最小可用闭环：用户创建配置、resolver 执行过滤、日志可查、Free 限额硬拒绝、Pro 订阅可无限使用。

## NATS JetStream 状态

```
Status: Deferred

NATS 不属于 MVP。
不允许作为 V1 主链路依赖。

V1 消息通道使用同步 API + DB/Redis/队列任务。
NATS 在 V2+ 用于：日志异步 ingestion、事件驱动配置同步、企业版高级特性。
EOF
```

## 1. MVP 必须包含

### 1.1 portal-web

```text
注册 / 登录
套餐展示：Free / Pro / Business / Education / Enterprise
Pro 月付 / 年付 checkout
Business / Education 按人数单位 checkout
当前订阅状态
订单 / 发票 / 支付 / 退款基础页面
Profile CRUD
黑名单 / 白名单规则
设备 / 接入指引
会员中心一级入口：安全 / 隐私 / 家长监护 / 黑名单 / 白名单 / 统计 / 日志 / 设置 / 会员中心
安全防护 Lite：恶意 / 钓鱼 / C2 / Cryptojacking 基础拦截
隐私保护 Lite：跟踪器 / 遥测阻断、日志模式、IP 匿名化
家长监护 Lite：成人内容拦截、安全搜索、YouTube 受限模式
查询日志与基础统计
Free 300,000 queries/月额度展示
超过 Free 额度后硬拒绝（无降级中间态）
团队管理：创建团队、邀请成员、角色分配（owner/admin/member）、成员管理
```

> 本节原为 `dns-console-web` 段,2026-06-15 合并后整体迁入 `portal-web` 总后台(原 console 域)子命名空间,功能行为完全保留。路由路径与契约未变;实现位置从 `dns-console-web/app/Http/Controllers/Api/V1/*` 迁至 `portal-web/app/Http/Controllers/Api/V1/{Admin,Agent,Internal}/*`。详见 [`07-CHANGE-LOG.md` 2026-06-15 合并条目](07-CHANGE-LOG.md) 与 [`05-PLANS.md` §8A](05-PLANS.md)。

```text
resolver 节点预创建(管理员在 portal-web 总后台的 `POST /api/v1/admin/nodes` 创建)
ApiKey/Secret 预签发(NodeTokenService.issueToken,三元组仅返回一次,portal-web 仅存 hash)
resolver 节点心跳(Bearer + HMAC 双因子)
节点健康状态缓存到 Redis
Profile 配置版本管理
portal-web 发布配置接收 API(in-process)
resolver 配置拉取 API
resolver 配置 ACK API
query logs batch 接收 API
log worker 写 ClickHouse
usage worker 聚合 query count 并上报 portal-web Member
quota snapshot 接收 / 分发
```

> **已下线**:resolver 自助注册 / `bootstrap_token` / `identity.json` / `POST /api/v1/agent/nodes/register`。
> 节点凭据只通过 `POST /api/v1/admin/nodes`(或 reissue)签发。

### 1.3 dns-resolver

```text
UDP 53
DoH
Profile 识别
本地内存规则引擎
安全 / 隐私 / 家长监护 / 黑白名单规则优先级执行
配置热加载
Free quota 检查
query log 批量上报到 portal-web
metrics 批量上报到 portal-web
heartbeat 上报到 portal-web（Bear + HMAC 双因子）
```

> **已下线**：节点自助注册 / `bootstrap_token` / `identity.json`。凭据必须由 `resolver install` 写入 `configs/server.yaml`，启动时 `cfg.Validate()` 校验。

### 1.4 geodns

```text
基于 Redis 健康视图读取 healthy resolver nodes
返回就近/健康 resolver 地址
异常节点摘除
不参与每一次递归 DNS 查询
```

## 2. MVP 明确不做

```text
DNS 查询按量计费
自动超额扣费
复杂用量阶梯价格
按节点资源计费
财务数据写 ClickHouse
resolver 直接连接 Redis / ClickHouse / MySQL
resolver 直接调用 portal-web
resolver 侧自助注册（已下线）
bootstrap_token / identity.json（已下线）
多币种自动汇率结算
复杂税务地区自动判断
高级渠道商 / MSP 分账
Anycast 全球生产网络
复杂 EDR / SIEM 集成
```

## 3. MVP 成功闭环

```text
用户注册
  -> 默认 Free subscription
  -> 创建 Profile 和规则
  -> portal-web 调用 portal-web(原 console 域) 发布配置
  -> portal-web(原 console 域) 生成 config version
  -> resolver 拉取配置
  -> 用户设备发起 DNS 查询
  -> resolver 执行过滤
  -> resolver 上报 query logs
  -> portal-web(原 console 域) 写 ClickHouse
  -> usage worker 汇总 query count
  -> portal-web 更新 usage_counters
  -> Free 达到 300,000 后生成 quota snapshot
  -> quota_status=exceeded,resolver 硬拒绝返回 SERVFAIL
  -> 用户升级 Pro
  -> portal-web 支付成功,subscription active
  -> quota snapshot 变为 unlimited
  -> resolver 恢复 protected mode
```

## 4. 财务 MVP

财务 MVP 必须实现：

```text
plans / plan_prices
subscriptions
orders / order_items
invoices / invoice_lines
payments
refunds
billing_ledger_entries
payment_webhook_events
usage_records / usage_counters
reconciliation_runs / reconciliation_items
```

但 V1 只允许产生以下收费明细：

```text
subscription
seat_block
manual_adjustment
credit
```

禁止产生：

```text
usage_overage
query_usage_charge
```

## 5. 验收标准

| 编号 | 验收项 | 必须结果 |
|---|---|---|
| MVP-01 | Free 299,999 queries | 继续 protected filtering |
| MVP-02 | Free 300,000 queries | resolver 在 DNS 协议层硬拒绝返回 SERVFAIL（无降级中间态） |
| MVP-03 | Free 超额 | 不生成收费 invoice line |
| MVP-04 | Pro active | resolver config 中 `monthly_query_limit=null, quota_status=unlimited` |
| MVP-05 | Business 51 employees | 计费 quantity = 2 |
| MVP-06 | resolver 上报 | 只上报到 portal-web Agent API |
| MVP-07 | ClickHouse | 只存 DNS 日志和统计分析 |
| MVP-08 | Redis | 只存短态、锁、调度视图 |
| MVP-09 | 财务金额 | 只用整数 `amount_minor` |
| MVP-10 | 重放 usage batch | 不重复累加 |
| MVP-11 | 会员中心导航 | 必须包含安全、隐私、家长监护、黑名单、白名单、统计、日志、设置、会员中心 |
| MVP-12 | 白名单与黑名单冲突 | 白名单优先生效 |
| MVP-13 | 隐私日志关闭 | 不展示详细日志，但仍允许最小化 query_count 聚合 |
| MVP-14 | 家长监护 Lite | SafeSearch / 成人内容基础开关能进入 resolver config |
