# 项目目标（OcerDNS Security Platform）

> 对标 NextDNS / AdGuard DNS / CleanBrowsing 的 DNS 安全过滤服务平台。当前目标不是一次生成全量商业系统，而是先生成一个架构正确、链路闭环、可运行、可验收的 MVP。

## 1. 产品定位

OcerDNS Security Platform 是一个面向个人、家庭和团队的 DNS 安全过滤平台，提供：

- 会员中心：安全、隐私、家长监护、黑名单、白名单、统计、日志、设置、套餐订阅
- 广告与追踪域名拦截
- 恶意域名、钓鱼、C2、Cryptojacking 防护
- 家长控制与基础分类拦截
- 自定义白名单 / 黑名单
- Profile 与设备管理
- DNS 查询日志与统计
- 多节点 resolver 管理
- 后续扩展套餐、团队、企业能力

## 2. 当前唯一系统边界

项目固定为 3 个业务包(原 `dns-console-web` 已于 2026-06-15 并入 `portal-web` 总后台,详见 [`07-CHANGE-LOG.md`](07-CHANGE-LOG.md) 与 [`05-PLANS.md` §8A](05-PLANS.md)):

| 包 | 职责一句话 | 不负责 |
|---|---|---|
| `portal-web` | 管用户、业务配置、Profile、规则、日志展示、套餐、后台(含原 console 域:节点管理、心跳、配置版本、发布任务、节点健康视图、规则库、系统配置、节点侧审计) | 不直接管理 resolver 进程；不参与 DNS 实时查询 |
| `dns-resolver` | 处理真实 DNS 查询，执行规则，转发上游，上报心跳和日志 | 不访问 MySQL；不调用 portal-web 业务 API |
| `geodns` | 根据地域、健康、权重把接入域名解析到合适 resolver | 不做过滤规则；不作为每次递归查询的代理 |

历史命名处理：

| 历史命名 | 当前处理 |
|---|---|
| `admin-web` | 并入 `portal-web` 的后台管理能力 |
| `dns-control-web` | 改名并收敛为 `dns-console-web`,后并入 `portal-web` |
| `control-plane` | 不再作为独立包；控制面能力归入 `dns-console-web`,后并入 `portal-web` |
| `dns-console-web` | 已并入 `portal-web` 的总后台(原 console 域),目录仅留 `Layout.vue` 占位 |

## 3. 目标用户

| 用户角色 | 目标价值 |
|---|---|
| 个人用户 | 获得更干净、更安全的 DNS 上网体验 |
| 家庭 / 家长 | 管理家庭设备、拦截不良内容、查看访问统计 |
| 团队 / 企业 | 统一配置团队网络安全策略，后续支持审计和企业能力 |
| 技术用户 | 获得自定义规则、专属 Profile、DoH / DoT / UDP 接入 |

## 4. MVP 必须完成的闭环

第一版必须完成：

```text
注册 / 登录
创建 Profile
会员中心入口：安全 / 隐私 / 家长监护 / 黑名单 / 白名单 / 统计 / 日志 / 设置 / 会员中心
添加白名单 / 黑名单
配置安全、隐私、家长监护 Lite
发布 Profile 配置版本
resolver 节点预创建与凭据签发
resolver 心跳上报
resolver 拉取配置并热加载
DoH 查询和 UDP 查询
规则匹配：白名单优先，黑名单拦截，默认放行
查询日志批量上报
portal-web 展示查询日志
console-web 展示节点状态
```

MVP 不强制完成：

```text
DNS 查询按量计费
自动超额扣费
Anycast
SCIM
Webhook
企业专属节点
多规则源商业运营
复杂风控系统
```

## 5. 技术选型

| 类别 | 选择 | 说明 |
|---|---|---|
| Web 后端 | Laravel 12 或项目约定稳定版本 | `portal-web`（含 Member 域与原 console 域）统一采用 Laravel，保持团队栈单一 |
| 前端 | Vue 3 + Vite + TypeScript + Element Plus | 会员控制台和管理后台 |
| DNS / Agent | Go 1.22+ 单二进制 | 适合网络服务、并发和部署 |
| DNS 框架 | CoreDNS 插件化或自研 DNS handler | MVP 可先实现 DoH / UDP 基础能力，后续扩展 |
| 业务数据库 | MySQL 8.0 | 唯一业务主库，支持事务、约束和复杂查询 |
| 缓存 | Redis 7 | 配置缓存、节点健康快照、限流、短态数据 |
| 日志分析 | ClickHouse 24.x | DNS 查询日志和聚合分析 |
| 消息总线 | NATS JetStream 2.x | 规模化阶段引入，MVP 可先 HTTP 批量上报 |
| 监控 | Prometheus + Grafana | resolver 和 Web 指标 |

## 6. 核心原则

必须遵守：

- 控制面和数据面分离。
- DNS 查询链路不访问 MySQL。
- DNS 查询链路不调用 Laravel API。
- DNS 查询链路尽量不访问 Redis；必要短态缺失时按硬拒绝处理。
- Profile 配置必须版本化。
- 配置包必须带 checksum。
- resolver 必须支持热加载和回滚。
- DNS 查询日志必须异步、批量、可缓冲。
- 节点必须支持横向扩容和健康摘除。
- 心跳、日志、指标必须拆分接口和语义。

严禁设计：

- Laravel 直接 SSH 操作 DNS 服务器。
- resolver 每次查询实时查数据库。
- resolver 直接写 MySQL 或 ClickHouse。
- 用户修改规则后重启 resolver。
- GeoDNS 参与每一次真实递归解析。
- 把旧目录 `admin-web`、`dns-control-web`、`control-plane` 当成当前项目包。

## 7. 成功标准

| 指标 | MVP 目标 | 生产目标 |
|---|---:|---:|
| 单节点 QPS | 5,000 | 50,000+ |
| DNS P95 响应时间 | < 30ms | < 20ms |
| 配置同步延迟 | < 30s | < 3s |
| 节点离线检测 | < 90s | < 45s |
| 日志可见延迟 | < 5min | < 30s |
| 配置回滚 | 支持手动回滚 | 支持自动 / 灰度回滚 |
| 单节点故障 | 可手动摘除 | 自动摘除与流量迁移 |

## 8. 当前交付定位

当前文档包用于生成：

```text
L3 MVP：可运行、可测试、可演示、可小流量内部试运行。
```

不能直接宣称：

```text
L4 生产级商业交付。
```

达到 L4 前必须补齐压测、容量评估、监控告警、隐私合规、灰度发布、灾备和安全审计证据。


## NextDNS Lite V1 商业目标补充

当前 V1 不做复杂创新计费，目标是参考 NextDNS 的低复杂度套餐结构：Free 300,000 queries/month，Pro unlimited queries，Business 按员工 block，Education 按学生 block，Enterprise 合同价。Query count 是 quota/统计/风控数据，不是 Pro/Business 的按量扣费数据。

