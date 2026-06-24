# 架构设计（OcerDNS Security Platform）

> 当前架构固定为 `portal-web`、`dns-resolver`、`geodns` 三个业务包。原 `portal-web(原 console 域)` 已并入 `portal-web` 的总后台(原 console 域)子命名空间,代码改造已在 Stage M.1–M.16 全部落地（详见 [`05-PLANS.md` §8A](05-PLANS.md) 与 [`07-CHANGE-LOG.md` 2026-06-15 合并条目](07-CHANGE-LOG.md)）。核心设计目标是控制面与数据面分离,保证 DNS 查询链路短、稳定、无降级中间态;Free 超额、节点掉线、所有服务域失败等场景均按"硬拒绝"语义实现,严格匹配。

## 1. 总体架构

```text
                                      ┌────────────────────────────┐
                                      │         portal-web          │
                                      │ 用户 / Profile / 规则 / 日志 │
                                      └─────────────┬──────────────┘
                                                    │ Internal API / Event
                                                    ▼
                                      ┌────────────────────────────┐
                                      │      portal-web(原 console 域)        │
                                      │ 节点 / 心跳 / 配置版本 / 发布 │
                                      └───────┬─────────┬──────────┘
                                              │         │
                        注册 / 心跳 / 拉配置 / ACK       │ 健康节点视图
                                              │         ▼
                                              │   ┌─────────────┐
                                              │   │   geodns     │
                                              │   │ 入口调度 / 摘流 │
                                              │   └──────┬──────┘
                                              │          │ 返回 resolver 地址
                                              ▼          │
用户设备 ── DoH / DoT / UDP / TCP ─────────▶ dns-resolver ◀───────── 用户设备
                                              │
                                              │ 上游查询
                                              ▼
                                      Cloudflare / Quad9 / Google / 自定义上游
                                              │
                                              │ 查询日志 / 指标
                                              ▼
│                                      portal-web Agent API / NATS
                                              │
                                              ▼
                                      log worker → ClickHouse
```

## 2. GeoDNS 与实际查询链路

GeoDNS 的职责是**入口寻址**，不是每次 DNS 递归查询的中间代理。

### 2.1 服务发现链路

```text
用户设备解析 doh.example.com / dns.example.com
  → geodns 根据来源 IP、地域、节点健康、权重
  → 返回一个或多个 resolver 地址
```

### 2.2 实际 DNS 查询链路

```text
用户设备
  → dns-resolver
  → Profile / Device 识别
  → 本地内存规则引擎
  → 命中则直接返回拦截响应
  → 未命中则请求 upstream DNS
  → 返回用户设备
```

这样可以避免 GeoDNS 成为实时查询链路瓶颈。

## 3. 三个包职责

> `portal-web(原 console 域)` 已于 2026-06-15 至 2026-06-16 之间并入 `portal-web` 的总后台(原 console 域)子命名空间,代码改造在 Stage M.1–M.16 全部落地(详见 [`05-PLANS.md` §8A](05-PLANS.md) 与 [`07-CHANGE-LOG.md` 2026-06-15 合并条目](07-CHANGE-LOG.md))。`dns-resolver` / `geodns` 的 Go 代码零修改,只改部署配置中 `Endpoint` / `healthview.url` 指向 `portal-web`。

### 3.0 职责表

| 包 | 技术栈 | 核心职责 | 关键接口 |
|---|---|---|---|
| `portal-web` | Laravel + Vue 3 | 会员控制台 + 总后台(含原 console 域:节点管理、心跳、配置版本、发布任务、ACK、健康视图、规则库、系统配置、节点侧审计、GeoDNS 映射) | Public / User / Admin / Node / Internal API |
| `dns-resolver` | Go | DNS 协议接入、规则匹配、缓存、日志、心跳、配置热加载 | DNS / Node HTTP(指向 `portal-web`) |
| `geodns` | Go | 地域调度、权重路由、健康摘除、灰度;从 `portal-web` 拉健康视图 | Authoritative DNS / Health View(指向 `portal-web`) |

> `portal-web(原 console 域)/` 目录在仓库中仅保留 `web/src/views/Layout.vue` 一个占位文件,所有 Admin / Node / Internal 控制器与 middleware 整段并入 `portal-web`,路由路径已从 `/api/v1/agent/*` 更新为 `/api/v1/node/*`;

## 4. 存储划分

| 存储 | 归属 | 数据 |
|---|---|---|
| MySQL | `portal-web` | users、profiles、rules、devices、plans、plan_prices、plan_features、orders、payment_transactions、stripe_webhook_logs、wallets、wallet_transactions、billing_periods、billing_items、usage_records、audit_logs |
| MySQL | `portal-web(原 console 域)` | nodes、node_tokens、node_heartbeats、config_versions、publish_tasks、task_executions、query_log_ingest_batches、geo_dns_mappings、rule_sources、system_config、admin_audit_logs、policy_snapshots、policy_publish_logs、alerts、aggregation_offsets、job_executions、admin_menu_rules |
| Redis | `portal-web(原 console 域)` / `geodns` | 节点健康快照、配置缓存、调度视图、限流 |
| 本地文件 | `dns-resolver` | server.yaml、profile config、规则编译产物、本地日志 buffer |
| ClickHouse | 日志链路 | dns_logs、分钟 / 小时 / 天聚合、Top 域名统计 |
| NATS JetStream | 异步链路 | profile.updated、dns.logs、billing.usage、alerts.created；`billing.usage` 只能由 query log 派生，不能由 metrics/heartbeat 派生 |

## 5. 包间通信

| 来源 | 目标 | 协议 | 内容 | 同步性 |
|---|---|---|---|---|
| `portal-web` | `portal-web(原 console 域)` | Internal HTTPS / HMAC(进程内)/ Internal HTTP(跨进程) | Profile 发布、配额同步、发布状态查询 | 同步请求 + 异步任务 |
| `dns-resolver` | `portal-web(原 console 域)` | Node HTTPS / HMAC (Bearer + HMAC-SHA256) | 心跳、拉配置、ACK、查询日志批量上报；凭据由 `install.sh` → `geo-dns install` 一次性写入 | 周期 / 拉取 |
| `portal-web(原 console 域)` | `dns-resolver` | NATS / 配置版本通知 | 提醒节点有新配置 | 异步通知，可丢失后由轮询补偿 |
| `dns-resolver` | `portal-web(原 console 域)` | Node HTTPS / NATS | 查询日志、指标；MVP 走 HTTP batch | 异步批量 |
| `geodns` | `portal-web(原 console 域)` / Redis | Internal API / Cache | 健康节点视图 | 周期拉取 |
| `portal-web(原 console 域)` | ClickHouse | Worker / HTTP client | 写入 DNS 查询日志和聚合 | 异步 |
| `portal-web` | ClickHouse | 查询 API / 服务层 | 日志查询、统计查询，只读 | 只读 |
| `portal-web(原 console 域)` | `portal-web` | Internal HTTPS / HMAC | usage batch、发布状态 callback | 异步幂等 |

## 6. 核心运行链路

### 6.1 用户配置发布

```text
用户修改 Profile
  → portal-web 生成 profile_versions 草案
  → 用户点击发布
  → portal-web 调用 portal-web(原 console 域)internal publish
  → portal-web(原 console 域)生成 config_versions / publish_tasks
  → resolver 心跳发现新版本或收到通知
  → resolver 拉取 config bundle
  → checksum 校验
  → 原子写入本地文件
  → 热加载内存规则
  → POST config/ack
```

### 6.2 节点生命周期（三步：Console 创建 + 签发 Token + 一键安装）

```text
1. 管理员在 portal-web 总后台创建节点
   POST /api/v1/admin/nodes
   入参: node_code, name, region, city, public_ipv4, weight, supported_protocols...
   行为: 创建 Node 记录，status=pending，node_code 自动生成为 nd_<random10>

2. 管理员签发节点 Token（与创建节点分开，两步操作）
   POST /api/v1/admin/nodes/{nodeCode}/tokens
   入参: scopes (可选), expires_in_days (可选，默认 365)
   行为: NodeToken::createForNode() 签发凭据：
     - token (api_key) = ocnd_<random40> （Bearer 凭证，前端展示为 ocnd_****）
     - hmac_secret = hmk_<random32> （HMAC-SHA256 签名密钥）
     - 服务端仅存 sha256(token) + sha256(hmac_secret) + encrypt(hmac_secret)
     - 凭据在响应中**仅返回一次**，响应头 Cache-Control: no-store

3. 运维在目标机执行一键安装（通过 install.sh 脚本）
   curl -fsSL https://<host>/build/install.sh | sh -s -- \
       --server=https://<host> \
       --token=ocnd_xxxxx \
       --node-id=xxxxx

   install.sh 行为：
     - 检测 OS/Arch (linux/amd64 或 linux/arm64)
     - 下载 dns-resolver 二进制到 /usr/local/bin/geo-dns
     - 校验 ELF 文件头
     - 执行 geo-dns install --server=... --token=... --node-id=...

   geo-dns install (install.go) 行为：
     - 用 --token 调用 POST /api/v1/node/tokens/verify 换取 api_key + secret
     - 校验 --server 必须为 http(s) URL
     - 构造 config（覆盖 Endpoint / APIKey / Secret / NodeID / 节点元数据）
     - cfg.Validate()：缺任何凭据直接退出，不写文件
     - 原子写 configs/server.yaml（temp + rename，权限 0600）
     - 打印结果：node_id + 脱敏的 api_key/secret

4. 启动 resolver (geo-dns)：
     - 加载 configs/server.yaml
     - cfg.Validate()：缺凭据则 log.Fatalf 拒绝启动
     - 用 api_key / secret 构造 Agent 凭据
     - 心跳 / 拉配置 / ACK / 日志批量 全部使用 Bearer token + HMAC 签名
     - 周期心跳（默认 30s），console 收到心跳即标记 online
     - 超时 offline 由 console 定时任务后置判定（默认 90s 阈值）
     - geodns 根据健康视图调度或摘除
```

**Token 验证 API（安装时自动调用）**：

```
POST /api/v1/node/tokens/verify
入参: { "token": "ocnd_xxxxx" }
行为: sha256(token) 查 node_tokens 表 → 解密 hmac_secret_encrypted → 返回 api_key + secret
此端点无鉴权中间件，仅用于安装时 token 兑换凭据。
```

**禁止路径**：

- ❌ 任何旧版 `/api/v1/agent/*` 注册端点（已删除）
- ❌ resolver 侧 `bootstrap_token` / `identity.json` 自助注册
- ❌ 任何"凭据缺失就走旧流程"的兜底 / 回退 / 虚拟代码
- ❌ 创建节点时自动签发 token（创建和签发是两步独立操作）

API 契约见 `contracts/openapi.yaml` 中 `/api/v1/admin/nodes*` 和 `/api/v1/node/tokens/verify` 端点。

### 6.3 查询日志链路

```text
resolver 处理 DNS 查询
  → 生成 QueryLogItem
  → 写入内存队列
  → MVP 批量发送到 portal-web(原 console 域)：POST /api/v1/node/query-logs/batch
  → 规模化时可发送到 portal-web(原 console 域)管理的 NATS dns.logs 入口
  → 失败时写 resolver 本地 buffer
  → 恢复后重放到 portal-web(原 console 域)ingestion
  → portal-web(原 console 域)log worker 幂等写 ClickHouse
  → portal-web(原 console 域)usage worker 派生 usage batch 并调用 portal-web Member
  → portal-web Member 查询 ClickHouse 展示日志，同时使用 usage_records/usage_counters 处理计费
```

### 6.4 数据上报落点强约束

- resolver 不直接连接 MySQL、Redis、ClickHouse。
- resolver 日志和指标先到 portal-web(原 console 域)Agent API；规模化可替换为 portal-web(原 console 域)管理的 NATS ingestion 入口，不能绕过 portal-web(原 console 域)写 ClickHouse。
- ClickHouse 由 portal-web(原 console 域)log worker 写入。
- Redis 只存健康快照、配置缓存和限流状态。
- 财务计费用量由 portal-web(原 console 域)usage worker 调用 portal-web Member Internal API 写入，不从 metrics/heartbeat 扣费。

### 6.5 当前已落地的生产闭环

当前代码已按 `START.md` 的 MVP 顺序形成以下可运行闭环：

```text
用户下发 DNS 查询
  → geodns 返回 resolver 入口
  → dns-resolver 按 active.json 做 IP → Device → Profile 识别
  → 命中安全 / 隐私 / 家长规则则 BLOCK
  → 命中 SafeSearch 重写则返回 CNAME
  → 未命中则上游递归解析
  → query log batch 上报 portal-web
  → portal-web 聚合 usage_records / billing_periods
  → 生成 billing_items
  → 用户创建 orders
  → PaymentService 创建 Stripe Checkout
  → Stripe webhook 回写 payment_transactions / orders
  → SubscriptionService 生效套餐
  → Wallet / BillingItem / FinanceVerifier 完成财务闭环
```

当前强约束：

- DoH 与 UDP 都不能在未知 profile 下回退到 legacy/default profile，必须拒绝。
- SafeSearch 不能对全体用户生效，只能由 `parental.safe_search` / `force_safe_search` 开关驱动。
- 用量计费只能来自 `usage_records`，而 `usage_records` 只能来自 resolver 查询日志聚合，不能来自 heartbeat / metrics。
- 订单与支付必须幂等：
  - `orders.idempotency_key`
  - pending `payment_transactions` 复用
  - Stripe webhook 按 `event_id` 去重
- 钱包真相在 `wallets`，余额变动必须伴随 `wallet_transactions`，并记录 `wallet_id`、`transaction_no`、`balance_after`。

当前已验证证据：

- `portal-web`: `php artisan test --filter=ApiTest` 通过
- `portal-web`: `php artisan test --filter=MemberWorkspaceTest` 通过
- `portal-web/web`: `npm run build` 通过
- `dns-resolver`: `go test ./...` 通过

注意：

- PHPUnit 不能并发共用同一 MySQL 测试库，否则会出现 `dns_migrations` / 表清理互相踩库；当前项目测试需要串行执行。

## 7. 可用性与故障行为

| 故障 | 服务行为 |
|---|---|
| `portal-web` 故障 | 已加载配置的 resolver 继续提供 DNS 服务；用户无法修改配置 |
| `portal-web(原 console 域)` 故障 | resolver 使用本地最后成功配置；心跳 / 配置拉取重试 |
| Redis 故障 | 控制台退回 MySQL 快照；GeoDNS 使用本地最近健康视图 |
| NATS 故障 | resolver 切换 HTTP batch 或本地 buffer |
| ClickHouse 故障 | DNS 查询不受影响；日志在队列 / buffer 中等待恢复 |
| 单个 resolver 故障 | 心跳超时，console 标记 offline，geodns 摘除节点 |
| 上游 DNS 故障 | resolver 切换备用 upstream；超过阈值返回 SERVFAIL |

## 8. 安全边界

- Internal API 使用 HMAC-SHA256 或 mTLS。
- Node API（心跳/拉配置/ACK/日志上报）使用两层鉴权中间件策略：
  - `POST /api/v1/node/tokens/verify`：无鉴权，安装时用 token 一次性兑换 api_key
  - `POST /api/v1/node/dns-resolver/register`、`POST /api/v1/node/geodns/register`：`node.token` 中间件，凭旧 token 签发 api_key
  - 其余业务接口（心跳、拉配置、ACK、日志上报）：`node.api_key` 中间件，凭注册时签发的明文 api_key 鉴权
- Admin API（节点凭据签发）：管理员 Sanctum session + `admin.access` 权限。
- 节点凭据（token + hmac_secret）**只**在 `POST /api/v1/admin/nodes/{nodeCode}/tokens` 响应中返回一次；之后只能 reissue 或 revoke。
- 生产环境禁止默认管理员密码。
- client IP 默认只保存 hash，不保存明文。
- 日志保留周期按套餐和隐私策略执行。
- 配置 bundle 必须带版本号、checksum 和签名字段。
- resolver 不接受来自公网的控制命令，只主动拉取配置。

**已下线**：bootstrap token / `Authorization: Bootstrap ...` 任何用法；resolver 侧 `identity.json` 任何用法；`POST /api/v1/agent/nodes/register` 端点；`X-Hmac-Key` 作为主要鉴权方式。

## 9. 租户/团队隔离模型（user + team 双层）

V1 不引入 `tenant_id` / `organization_id` / 多组织层级，而是采用 **`user` + `team` 双层**：

| 层级 | 标识字段 | 数量级 | 适用场景 |
|---|---|---|---|
| User | `users.id` | 单平台数十万~百万 | 个人账户、个人 Profile、个人设备 |
| Team | `teams.id` | 单平台数千~数万 | Business 多员工订阅、团队共享 Profile、团队审计 |

关键规则：

1. **个人资源**：所有 `profiles / devices / personal_access_tokens` 必须有 `owner_user_id`。
2. **团队资源**：所有团队共享 `profiles / audit_logs` 必须有 `team_id`，并通过 `team_members(user_id, team_id, role)` 关联。
3. **可见域（强制 WHERE 子句）**：
   - 个人资源查询必须 `WHERE owner_user_id = :current_user_id`。
   - 团队资源查询必须 `WHERE team_id IN (SELECT team_id FROM team_members WHERE user_id = :current_user_id)`。
   - 管理员后台查询必须 `AND is_admin = true` 才允许跨用户。
4. **跨服务传递**：team_id 在 `internal API` 调用时通过 HMAC 签名 body 显式传递，禁止"由接收方按 ip / cookie 推断"。
5. **不出现 `organization_id` / `tenant_id`**：所有现有数据模型（`users / teams / team_members / profiles / devices / audit_logs / dns_logs`）均使用上述两层 id，V1 阶段不引入第三层。V2+ 评估是否升级到"组织 → 团队 → 成员"三层时需另起 ADR。
6. **审计日志**：`portal-web` 写 `audit_logs(team_id?, user_id, action, target_type, target_id, ...)`，`portal-web(原 console 域)` 写 `admin_audit_logs(node_id?, actor_user_id, action, ...)`；`team_id` 与 `node_id` 互不污染。

违规（裸 `SELECT *`、跨用户读取、`LIMIT` 截断代替 WHERE、应用层过滤）一律被 code-review 拒收。详见 [rules/coding.md HC-04](file:///Users/472733389qq.com/Desktop/ai%20agent/docs/ai-doc/ai-doc/ai-doc-v1/rules/coding.md) 与 `project-doc/02-MODULES.md` §1.4。

## 10. 实施顺序

1. `portal-web`：账号、Profile、规则、设备、日志查询。
2. `portal-web(原 console 域)`：节点预创建、心跳、配置版本、发布任务、配置拉取、ACK。
3. `dns-resolver`：DoH / UDP、Profile 识别、规则引擎、本地配置、日志上报。
4. `geodns`：先用静态入口替代；多节点稳定后再接入健康调度。
5. `ClickHouse`：MVP 可用 ClickHouse 单机，规模化阶段再做集群冷热分层。V1 **不引入 NATS**（详见 [rules/coding.md HC-01](file:///Users/472733389qq.com/Desktop/ai%20agent/docs/ai-doc/ai-doc/ai-doc-v1/rules/coding.md)）。


## 11. NextDNS Lite V1 架构补充

计费闭环以 `portal-web` 为财务事实归属，以 `portal-web(原 console 域)` 为 resolver 数据入口（合并后控制面统一在 `portal-web`）。`dns-resolver` 只能通过 Agent API 上报到 `portal-web`，不得直连 Redis、ClickHouse、MySQL 或其它服务。Free quota 通过 resolver config 下发；Pro/Business/Education 使用 `quota_status=unlimited` 表示无限查询，Free 超额后 `quota_status=exceeded` resolver 硬拒绝返回 SERVFAIL。



## 12. Internal Service API 章节(合并后)

> **合并计划(待审批)**:原 `portal-web(原 console 域)` 作为目标侧的所有"内部 API"调用,在合并后**目标侧统一为 `portal-web(原 console 域)`**。调用方在 `portal-web` 内部走 Laravel 进程内服务调用,跨进程路径为 `dns-resolver` / `geodns` → `portal-web`。

| 调用方 | 目标 | 路径 | 鉴权 | 用途 |
|---|---|---|---|---|
| `portal-web` User/Profile | `portal-web(原 console 域)` | `POST /api/v1/internal/profile-publishes` | `shared.token:internal` | Profile 发布、配额同步 |
| `portal-web` User/Stats | `portal-web(原 console 域)` | `GET /api/v1/internal/query-logs?user_id=...&page=...` | `shared.token:internal` | 日志查询回读 |
| `portal-web` User/Stats | `portal-web(原 console 域)` | `GET /api/v1/internal/query-analytics?user_id=...&from=...&to=...` | `shared.token:internal` | 统计聚合回读 |
| `geodns` | `portal-web(原 console 域)` | `GET /api/v1/internal/geodns/health-view` | `shared.token:internal` | 健康节点视图(geodns Go 代码零修改,只改 Endpoint) |
| `dns-resolver` | `portal-web(原 console 域)` | `POST /api/v1/node/heartbeat` | `node.api_key` | 心跳 |
| `dns-resolver` | `portal-web(原 console 域)` | `GET /api/v1/node/dns-resolver/config` | `node.api_key` | 拉取配置 |
| `dns-resolver` | `portal-web(原 console 域)` | `POST /api/v1/node/dns-resolver/config/ack` | `node.api_key` | 配置 ACK |
| `dns-resolver` | `portal-web(原 console 域)` | `POST /api/v1/node/dns-resolver/query-logs` | `node.api_key` | 查询日志批量上报 |
| `dns-resolver` (安装) | `portal-web(原 console 域)` | `POST /api/v1/node/tokens/verify` | 无鉴权 | 安装时用 token 兑换 api_key + secret |
| `dns-resolver` (注册) | `portal-web(原 console 域)` | `POST /api/v1/node/dns-resolver/register` | `node.token` | 注册并签发 api_key |
| `geodns` (注册) | `portal-web(原 console 域)` | `POST /api/v1/node/geodns/register` | `node.token` | 注册并签发 api_key |
| `geodns` | `portal-web(原 console 域)` | `GET /api/v1/node/geodns/config` | `node.api_key` | 拉取 geo 配置 |

> 路径已从旧版 `/api/v1/agent/*` 更新为 `/api/v1/node/*`。`portal-web` 同时承载 `/api/v1/public/*`、`/api/v1/user/*`、`/api/v1/admin/*`、`/api/v1/node/*`、`/api/v1/internal/*` 五组路由。

## 13. Portal-Web Member 域与原 console 域职责边界(合并后)

> 本节定义同一个 Laravel 应用内两个域(Member / 原 console 域)的职责边界;`portal-web(原 console 域)` 整体消失,边界仍然存在但**进程内**。

### 13.1 数据所有权(合并后)

| 数据 | 主写入方 | 主读取方 | 写入语义 |
|---|---|---|---|
| `users / teams / team_members / team_invitations` | `portal-web` User/Auth | `portal-web` User/Auth | Sanctum 登录、注册、邀请 |
| `permissions / role_permissions` | `portal-web` User/Auth | `portal-web` User/Auth | RBAC |
| `audit_logs` | `portal-web` User/Auth | `portal-web` Admin | 会员/计费/团队操作审计 |
| `profiles / profile_rules / profile_feature_settings / profile_versions` | `portal-web` User/Profile | `portal-web` User/Profile | User 工作区 |
| `plans / plan_prices / plan_features / subscriptions / orders / payment_transactions / wallets / wallet_transactions / billing_periods / billing_items / usage_records / stripe_webhook_logs` | `portal-web` Billing | `portal-web` Billing | 财务事实归属 |
| `nodes / node_tokens / node_heartbeats` | `portal-web(原 console 域)` Admin/Node + Node | `portal-web(原 console 域)` Admin/Node + User(读状态) | 节点生命周期与心跳 |
| `config_versions / publish_tasks / task_executions / policy_snapshots / policy_publish_logs` | `portal-web(原 console 域)` Admin/Publish | `portal-web(原 console 域)` Admin/Publish + `dns-resolver` Node | 配置版本、发布任务、ACK 状态 |
| `query_log_ingest_batches` | `portal-web(原 console 域)` Node | `portal-web(原 console 域)` Admin/Audit + LogWorker | 上报幂等批 |
| `geo_dns_mappings / rule_sources / system_config / alerts / aggregation_offsets / job_executions / admin_menu_rules / regions` | `portal-web(原 console 域)` Admin | `portal-web(原 console 域)` Admin + `portal-web` User(读 system_config) | 控制面配置 |
| `admin_audit_logs` | `portal-web(原 console 域)` Admin/Console | `portal-web(原 console 域)` Admin/Audit | 节点/发布/控制面操作审计;与 `audit_logs` 互不污染 |
| `dns_logs`(ClickHouse) | `portal-web(原 console 域)` LogWorker | `portal-web` User/Stats + `portal-web(原 console 域)` Admin/Stats | DNS 查询日志与聚合 |

### 13.2 进程内边界

- User 域 Controller（命名空间 `User/`）**不直接**写 `config_versions / publish_tasks / nodes / node_heartbeats`;通过 `portal-web(原 console 域)` 暴露的进程内服务(原 `NodeService` / `ConfigVersionService` / `PublishTaskService`)调用,保持 13.1 的所有权约束。
- 原 console 域 Controller **不直接**写 `profiles / users / teams / subscriptions / usage_records`;配置版本/发布任务需要的配额与会员信息从 `portal-web` User 域进程内服务(`QuotaService` / `BillingUsageService`)读取,绝不允许直接 `INSERT INTO users`。
- `audit_logs` 与 `admin_audit_logs` 是**两张独立表**;不允许任何代码通过 `INSERT INTO ... SELECT *` 跨表搬数。
- Node/Internal 控制器与 User/Admin 控制器共享同一个 Laravel 进程,但路由命名空间与中间件独立:`/api/v1/node/*` 使用 `node.token`（注册）或 `node.api_key`（业务接口）中间件;`/api/v1/internal/*` 只接 `shared.token:internal`;`/api/v1/admin/*` 接 Sanctum + `admin.only` + `permission:admin.access`;`/api/v1/user/*` 接 `auth:api` + `user.only`。

## 14. 项目目录结构

### 14.1 顶层结构

```text
ocer-dns/
├── README.md
├── start-all.sh / stop-all.sh
├── .gitignore
├── .run/                   ← 运行 PID 记录
│   ├── portal-api.pid
│   ├── portal-web.pid
│   ├── resolver.pid
│   └── geodns.pid
│
├── portal-web/             ← 用户门户 + 总后台（Laravel + Vue 3）
├── dns-resolver/           ← Go DNS 解析节点
├── geodns/                 ← Go 接入调度
└── portal-web(原 console 域)/        ← 已删除（已并入 portal-web）
    └── web/src/views/Layout.vue
```

### 14.2 portal-web 目录结构

```text
portal-web/
├── artisan
├── composer.json / composer.lock
├── Makefile / Dockerfile
├── phpunit.xml
│
├── app/
│   ├── Domain/                          ← 业务服务层（13 子域，26 个 final class Service）
│   │   ├── ApiKey/         ApiKeyService.php
│   │   ├── Audit/          AuditService.php
│   │   ├── Auth/           AuthService.php, NodeTokenService.php, PermissionService.php
│   │   ├── Billing/        BillingService.php          ← 模拟实现
│   │   ├── ClickHouse/     ClickHouseStatsService.php
│   │   ├── ConfigVersion/  CanonicalJson.php, ChecksumService.php,
│   │   │                   ConfigAckService.php, ConfigBuildService.php
│   │   ├── HealthView/     NodeHealthViewService.php
│   │   ├── Heartbeat/      HeartbeatService.php
│   │   ├── Ingest/         QueryLogIngestService.php, QueryLogReadService.php
│   │   ├── Profile/        ProfileService.php, MemberCenterService.php,
│   │   │                   MemberWorkspaceService.php(28 方法), ProfileConfigBuilder.php,
│   │   │                   ProfilePublishService.php, DomainNormalizer.php
│   │   ├── Publish/        PublishService.php
│   │   ├── Rule/           ProfileRuleService.php, RuleService.php
│   │   ├── System/         HealthCheckService.php
│   │   └── Team/           TeamService.php             ← 18 方法，最大 CRUD
│   │
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Controller.php
│   │   │   └── Api/V1/
│   │   │       ├── Public/     AuthController.php      ← 注册/登录
│   │   │       ├── Member/     7 控制器 (~60 方法)
│   │   │       │   ├── ProfileController.php, ProfileRuleController.php
│   │   │       │   ├── ProfilePublishController.php, MemberCenterController.php
│   │   │       │   ├── MemberWorkspaceController.php, TeamController.php
│   │   │       │   └── ApiKeyController.php
│   │   │       ├── Admin/      17 控制器 (~85 方法)
│   │   │       │   ├── AdminUserController.php, AdminNodeController.php
│   │   │       │   ├── AdminRbacController.php, AdminDeviceController.php
│   │   │       │   ├── AdminPublishController.php, AdminRuleController.php
│   │   │       │   ├── AdminGeoDnsController.php, AdminBillingController.php
│   │   │       │   ├── AdminFinanceController.php, AdminBillingStatsController.php
│   │   │       │   ├── AdminStatsController.php, AdminAuditLogController.php
│   │   │       │   ├── AdminConsoleAuditLogController.php, AdminQueryLogController.php
│   │   │       │   ├── AdminSystemConfigController.php, AdminAlertController.php
│   │   │       │   └── AdminTeamController.php
│   │   │       ├── Agent/      4 控制器
│   │   │       │   ├── HeartbeatController.php, ConfigPullController.php
│   │   │       │   ├── ConfigAckController.php, QueryLogController.php
│   │   │       │   └── Internal/
│   │   │       └── Internal/   3 控制器
│   │   │           ├── HealthViewController.php, ProfilePublishController.php
│   │   │           └── QueryLogReadController.php
│   │   └── Middleware/
│   │       ├── AuthenticateNodeToken.php        ← 节点 HMAC 认证
│   │       ├── CheckPermission.php              ← 权限检查
│   │       ├── RequireSharedToken.php           ← 内部服务认证
│   │       └── VerifyRequestSignature.php
│   │
│   ├── Infrastructure/
│   │   └── ClickHouse/     ClickHouseClient.php, MemberAnalyticsService.php
│   │
│   ├── Models/             29 个 Eloquent Model（19 种 UUID 前缀）
│   │   ├── Admin.php, AdminAuditLog.php, AdminPermission.php
│   │   ├── AdminRole.php, AdminRoleNavRule.php, ApiKey.php
│   │   ├── AuditLog.php, ConfigVersion.php, Device.php
│   │   ├── GeoDnsMapping.php, NavigationCatalog.php
│   │   ├── Node.php, NodeHeartbeat.php, NodeToken.php
│   │   ├── Permission.php, Profile.php, ProfileRule.php, ProfileVersion.php
│   │   ├── PublishTask.php, QueryLogEntry.php, QueryLogIngestBatch.php
│   │   ├── RolePermission.php, RuleSource.php, SystemConfig.php
│   │   ├── TaskExecution.php, Team.php, TeamInvitation.php
│   │   ├── TeamMember.php, User.php
│   │   └── Providers/         AppServiceProvider.php
│   │
│   ├── config/               ← Laravel 配置
│   │   ├── app.php, auth.php, cache.php, clickhouse.php
│   │   ├── database.php, filesystems.php, logging.php
│   │   ├── mail.php, queue.php, sanctum.php, services.php
│   │   ├── session.php, shared-tokens.php
│   │
│   ├── database/
│   │   ├── migrations/       25 个迁移文件
│   │   └── seeders/          DatabaseSeeder.php, AdminRbacSeeder.php
│   │
│   ├── routes/
│   │   ├── api.php, web.php, console.php
│   │   └── v1/
│   │       ├── public.php    ← 3 路由
│   │       ├── member.php    ← 70 路由
│   │       ├── admin.php     ← 68 路由
│   │       ├── agent.php     ← 4 路由
│   │       └── internal.php  ← 4 路由
│   │
│   ├── tests/
│   │   ├── Feature/          AgentHmacSignatureTest.php, ApiTest.php,
│   │   │                     MemberWorkspaceTest.php, ProfilePublishTest.php
│   │   └── TestCase.php
│   │
│   ├── project-doc/
│   │   └── 07-CHANGE-LOG.md
│   │
│   └── web/                  ← Vue 3 前端源码
│       ├── index.html, package.json, vite.config.js
│       └── src/
│           ├── main.js, App.vue
│           ├── api/          client.js
│           ├── assets/       theme.css
│           ├── components/   AdminLayout.vue, Layout.vue, ListPage.vue
│           ├── locales/      en.js(1154行), zh-CN.js(1172行), ko.js(1005行), index.js
│           ├── router/       index.js（39 条路由）
│           └── views/
│               ├── 公共 (4): Home.vue, Login.vue, Register.vue, AdminLogin.vue
│               ├── 会员中心 (17):
│               │   Dashboard.vue, ProfileList.vue, ProfileDetail.vue
│               │   Security.vue, Privacy.vue, ParentalControl.vue
│               │   Allowlist.vue, Denylist.vue, Analytics.vue, Logs.vue
│               │   Devices.vue, APIKeys.vue, Settings.vue, Membership.vue
│               │   TeamList.vue, TeamCreate.vue, TeamDetail.vue, TeamInvitations.vue
│               └── admin/ (20):
│                   Dashboard.vue, Nodes.vue, Publishes.vue, GeoDNS.vue
│                   RuleLibrary.vue, Users.vue, Devices.vue, QueryLogs.vue
│                   Alerts.vue, Billing.vue, Balance.vue, Recharge.vue
│                   Bill.vue, RefundRecords.vue, SystemConfig.vue, BasicConfig.vue
│                   AuditLogs.vue, RoleManagement.vue, MenuConfig.vue
```

### 14.3 dns-resolver 目录结构

```text
dns-resolver/
├── go.mod / go.sum
├── Dockerfile
├── cmd/dns-resolver/           main.go, install.go, main_test.go
├── configs/                    config.example.yaml, server.yaml, test-config.yaml
├── internal/
│   ├── agent/                  agent.go, agent_test.go          ← 控制面通信
│   ├── blockresponse/          blockresponse.go                 ← 拦截响应构造
│   ├── cache/                  cache.go, cache_test.go          ← DNS 缓存
│   ├── clickhouse/             client.go                        ← ClickHouse 客户端
│   ├── config/                 config.go, types.go              ← 配置加载
│   ├── dnscache/               dnscache.go                      ← DNS 缓存层
│   ├── dnsserver/              server.go                        ← UDP DNS 服务器
│   ├── doh/                    server.go                        ← DoH 服务器
│   ├── logging/                buffer.go, buffer_test.go        ← 日志 buffer
│   ├── matching/               engine.go, trie.go               ← 规则匹配引擎
│   ├── metrics/                metrics.go                       ← 指标收集
│   ├── profile/                resolver.go                      ← Profile 配置解析
│   ├── resolver/               resolver.go                      ← 上游 DNS 解析
│   ├── rules/                  engine.go, normalize.go          ← 规则引擎
│   └── storage/                config_store.go                  ← 配置持久化
├── tests/                      engine_test.go, normalize_test.go, profile_resolver_test.go
└── bench/                      bench_100k.go, concurrent_bench.go, simple_bench.go
```

### 14.4 geodns 目录结构

```text
geodns/
├── go.mod / go.sum
├── Dockerfile
├── cmd/geodns/                 main.go
├── configs/                    config.example.yaml
├── internal/
│   ├── config/                 config.go
│   ├── healthview/             client.go, types.go             ← 健康视图客户端
│   ├── router/                 router.go                       ← 调度路由
│   └── server/                 server.go                       ← DNS 服务器
└── tests/                      router_test.go
```

### 14.5 统计汇总

| 维度 | portal-web | dns-resolver | geodns |
|---|---|---|---|
| **语言** | PHP(Laravel) + Vue 3 | Go | Go |
| **Model 数** | 29 | - | - |
| **Domain Service** | 26 | - | - |
| **Controller** | 24 (~145 方法) | - | - |
| **路由** | 151 条 | - | - |
| **前端页面** | 41 (4 公共 + 17 会员 + 20 管理) | - | - |
| **多语言** | 3 (en/zh-CN/ko) | - | - |
| **内部模块** | 5 中间件 | 12 internal pkg | 4 internal pkg |
| **数据库** | MySQL + Redis + ClickHouse | 本地文件/内存 | 内存/Redis |
