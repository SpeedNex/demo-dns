# 模块职责边界

> 本文件用于把“架构图”落到可生成代码的模块边界。生成代码时必须按本文件划分包、目录、接口和测试。

## 1. portal-web

### 1.1 职责

`portal-web` 是用户与业务门户，包含官网、会员控制台和管理后台。

负责：

- 用户注册、登录、邮箱验证、密码重置。
- 用户 Profile 创建、编辑、删除、复制。
- 白名单、黑名单、自定义规则、规则优先级。
- 设备管理和接入说明。
- 查询日志、统计报表、Top 域名。
- 套餐、订阅、订单、发票的业务模型。
- 后台用户管理、套餐管理、订单管理、账单管理、服务管理、审计日志、团队管理。
- 调用 `portal-web(原 console 域)` 发起配置发布(合并后为同进程内部服务调用)。

不负责：

- resolver 进程生命周期管理。
- 节点心跳接收。
- 实时 DNS 查询处理。
- 直接写 resolver 本地配置。

### 1.2 portal-web 实际 Laravel 领域模块(2026-06-16 实测)

> 原 §1.2 推荐结构含 `Device/Plan/Billing/Usage/Service/Admin` 等子域,但实际代码采用更贴合业务的命名。下列子域与 `ocer-dns/portal-web/app/Domain/*` 一一对应,后续 §1.3 服务表以本表为准。

```text
# portal-web Member 域
app/Domain/ApiKey               # API Key 管理
app/Domain/Audit                # 会员/计费审计
app/Domain/Auth                 # AuthService / NodeTokenService / PermissionService
app/Domain/ClickHouse           # ClickHouseStatsService(统计分析)
app/Domain/Heartbeat            # HeartbeatService(心跳接收)
app/Domain/Ingest               # QueryLogIngestService / QueryLogReadService
app/Domain/Profile              # MemberCenter / MemberWorkspace / DomainNormalizer / ProfileConfigBuilder / ProfilePublish / ProfileService
app/Domain/Rule                 # ProfileRuleService / RuleService
app/Domain/System               # HealthCheckService
app/Domain/Team                 # TeamService
# portal-web(原 console 域)子域
app/Domain/ConfigVersion        # CanonicalJson / ChecksumService / ConfigAckService / ConfigBuildService
app/Domain/HealthView           # NodeHealthViewService
app/Domain/Publish              # PublishService(发布任务)
# 基础设施
app/Infrastructure/ClickHouse   # ClickHouseClient / MemberAnalyticsService
```

> `app/Infrastructure/DnsConsole` 兼容旧名已删除;`portal-web` 不再保留单独的 DnsConsole 客户端目录,所有跨域调用改为进程内服务注入。

### 1.3 关键服务

| 服务 | 责任 |
|---|---|
| `ProfileService` | Profile CRUD、默认配置、版本草案 |
| `RuleService` | 规则校验、归一化、冲突检测 |
| `ProfilePublishService` | 生成配置版本并调用 portal-web(原 console 域) 发布 |
| `UsageQueryService` | 查询 MySQL / ClickHouse 聚合结果 |
| `BillingLedgerService` | 订单、发票、支付、退款、credit note 的 ledger 追加写 |
| `BillingReconciliationService` | 支付渠道对账和差异处理 |
| `BillingAdminService` | 账单查询、发票管理、Credit Note、交易流水 |
| `ServiceTicketService` | 用户工单、退款审核、售后处理 |
| `AuditService` | 管理员和用户关键操作审计 |
| `TeamService` | 团队创建、成员管理、角色分配、团队切换 |
| **原 console 域新增(合并后)** | |
| `NodeTokenService` | 预签发 / 重新签发 / 吊销 (api_key, secret);portal-web 仅存 hash,plain 仅返回一次 |
| `HeartbeatService` | 心跳校验、状态计算、健康快照写 Redis |
| `ConfigBuildService` | 将 Profile 版本组织成 resolver config bundle |
| `PublishTaskService` | 发布任务创建、重试、失败记录 |
| `ConfigAckService` | 处理 resolver 配置应用结果 |
| `NodeHealthViewService` | 给 geodns 输出健康节点视图(进程内) |
| `RuleLibraryService` | 规则源 CRUD、批量同步、立即同步 |
| `SystemConfigService` | DNS/日志/安全参数配置 |
| `AdminConsoleAuditService` | 节点/发布/控制面操作审计(写 admin_audit_logs) |

### 1.4 数据所有权与同步方向（硬约束）

> **Source of Truth 单一原则**：本节是 AI 生成代码时的硬约束，违反任一条直接判定为不合格。

| 数据对象 | 主写入方 | 只读/镜像方 | 同步方向 |
|---|---|---|---|
| `users / accounts / credentials` | `portal-web` Member/Auth | — | 自有 |
| `teams / team_members / team_invitations` | `portal-web` Member/Auth | `portal-web(原 console 域)` 只读查询(按 `team_id` 过滤后台审计) | 单向 |
| `plans / subscriptions / orders / invoices / payments / refunds / billing_ledger_entries` | `portal-web` Billing | `portal-web(原 console 域)` 读 billing_usage 关联计划 | 单向 |
| `profiles / profile_rules / profile_feature_settings / profile_versions`(主数据) | **`portal-web` Member 独占** | `portal-web(原 console 域)` **不得直接读写** | 单向(portal-web Member → portal-web 原 console 域) |
| `config_versions / publish_tasks / task_executions`(配置快照与发布任务) | `portal-web(原 console 域)` | `portal-web` Member 读 `config_version` 状态回显给用户 | 反向只读 |
| `usage_counters / usage_records` | `portal-web` Billing(由 portal-web 原 console 域 usage worker 回调写) | `portal-web(原 console 域)` 写入、portal-web Member 落库 | 单向(原 console 域 → Member) |
| `devices / device_bindings` | `portal-web` Member | `portal-web(原 console 域)` 读 `device_id` 用于 source-IP 识别 | 单向 |
| `nodes / node_tokens / node_heartbeats` | `portal-web(原 console 域)` Admin/Node + Agent | — | 自有 |
| `dns_logs`(ClickHouse) | `portal-web(原 console 域)` log worker 写 | `portal-web` Member 读(只读 API 层) | 单向 |
| `audit_logs` | `portal-web` Member/Auth(用户/计费审计) | `portal-web` Admin | 独立 |
| `admin_audit_logs` | `portal-web(原 console 域)` Admin/Console(节点/发布审计) | `portal-web(原 console 域)` Admin/Audit | 独立(**不与 audit_logs 合并**) |

硬约束：

1. **`portal-web` Member 是 `profiles / profile_rules / profile_feature_settings` 的唯一主写方**。`portal-web(原 console 域)` 不得提供 Profile CRUD 端点，不得直接 INSERT/UPDATE/DELETE 这三张表;同样**不得**绕过 Member 域直接写 `users / teams / subscriptions / usage_records`。
2. **`portal-web` Member 不得直接写 `config_versions / publish_tasks`**，只能通过 `portal-web(原 console 域)` 暴露的进程内服务(`PortalInternalPublishService` / 原 `ProfilePublishService`)发起发布，并仅依赖返回的 `publish_id / status` 展示状态。`POST /api/v1/internal/profile-publishes` 路径保留供跨进程调试使用,但**生产**调用走进程内服务。
3. **双向同步禁止**:`profiles.*` 与 `config_versions.*` 必须通过"portal Member 写 → portal Member 调 portal(原 console 域)内部 publish → portal(原 console 域)写 config_versions" 单向链路,禁止任何反向同步或双写。
4. **配置消费**:`dns-resolver` 只通过 `GET /api/v1/node/resolver/config` 读取 `config_versions` + `profile_versions` 编译产物,从不直接读 `portal-web` 数据库。
5. **审计日志分离**:`portal-web` Member 写 `audit_logs`(用户/计费/订阅审计);`portal-web(原 console 域)` 写 `admin_audit_logs`(节点/发布/配置审计);二者仍是**两张独立表**,不合并字段,不交叉写。

违反上述任一条的代码，code-review 必须直接拒绝并要求重构。

## 2. portal-web(原 console 域)

> 原 `dns-console-web` 已于 2026-06-15 至 2026-06-16 之间整体并入 `portal-web`,包名 `dns-console-web` 消失,功能归入 `portal-web(原 console 域)` 子命名空间。实现位置:`portal-web/app/Domain/*` 与 `portal-web/app/Http/Controllers/Api/V1/{Admin,Agent,Internal}/*`,路由路径完全不变。

### 2.1 职责

`portal-web(原 console 域)` 是 DNS 控制面,负责 resolver 节点管理和配置发布。与 `portal-web` Member 域共享同一个 Laravel 进程和 MySQL 库,但保持以下独立边界:

- 路由命名空间:`/api/v1/admin/{nodes,publishes,geo-dns,system-config,rule-library,audit-logs,...}`、`/api/v1/node/*`、`/api/v1/internal/*`。
- 中间件:`/api/v1/admin/*` 中节点签发类路由走 `shared.token:admin`(沿用原 console 行为);`/api/v1/node/*` 走 `node.hmac` (Bearer + HMAC-SHA256);`/api/v1/internal/*` 走 `shared.token:internal`。
- 数据表:`nodes / node_tokens / node_heartbeats / config_versions / publish_tasks / task_executions / query_log_ingest_batches / geo_dns_mappings / rule_sources / system_config / admin_audit_logs` 全部位于 `portal-web` 的 `ocer_dns` 库;`audit_logs` 与 `admin_audit_logs` **仍是两张独立表**。

负责:

- 节点**预创建**(不在 resolver 端做自助注册),调用 `NodeTokenService.issueToken()` 签发 `(node_id, api_key, secret)` 三元组。
- 节点心跳接收和在线状态判断。
- 节点配置版本管理。
- 接收 `portal-web` Member 域的发布请求(进程内服务调用)。
- 编译或组织 resolver 可消费配置 bundle。
- 发布任务、任务执行记录、ACK。
- 给 `geodns` 提供健康节点视图(`geodns` Go 代码零修改,只改 Endpoint 指向 `portal-web`)。
- 接收 resolver 日志 / 指标上报的 MVP HTTP 入口。
- 管理后台(节点列表/详情、GeoDNS 映射、Profile 发布中心、规则库管理、系统配置、审计日志)。

不负责:

- 用户套餐订阅业务(归 Member 域)。
- 用户规则编辑 UI(归 Member 域)。
- 实时 DNS 查询。
- 持久化 DNS 查询日志到业务主库作为长期方案(只写 ClickHouse,业务主库只放 `query_log_ingest_batches` 幂等批元数据)。

### 2.2 推荐 Laravel 领域模块(并入 `portal-web` 后的最终形态)

```text
# 已在 §1.2 列出 portal-web Member 域;本节为原 console 域新增
app/Domain/Node
app/Domain/Heartbeat
app/Domain/ConfigVersion
app/Domain/PublishTask
app/Domain/HealthView
app/Domain/Ingest
app/Domain/RuleLibrary
app/Domain/SystemConfig
app/Domain/AdminConsoleAudit
app/Infrastructure/ClickHouse
```

### 2.3 关键服务(并入 `portal-web`)

| 服务 | 责任 |
|---|---|
| `NodeTokenService` | 预签发 / 重新签发 / 吊销 (api_key, secret);portal-web 仅存 hash,plain 仅返回一次 |
| `HeartbeatService` | 心跳校验、状态计算、健康快照写 Redis |
| `ConfigBuildService` | 将 Profile 版本组织成 resolver config bundle |
| `PublishTaskService` | 发布任务创建、重试、失败记录 |
| `ConfigAckService` | 处理 resolver 配置应用结果 |
| `NodeHealthViewService` | 给 geodns 输出健康节点视图(进程内) |
| `RuleLibraryService` | 规则源 CRUD、批量同步、立即同步 |
| `SystemConfigService` | DNS/日志/安全参数配置 |
| `AdminConsoleAuditService` | 节点/发布/控制面操作审计(写 `admin_audit_logs`) |
| `QueryLogIngestService` | 接收 `dns-resolver` 批量查询日志;幂等写 `query_log_ingest_batches`;log worker 异步写 ClickHouse |

## 3. dns-resolver

### 3.1 职责

`dns-resolver` 是部署在 DNS 节点上的 Go 单二进制。

负责：

- UDP 53 / TCP 53 / DoH / DoT 查询接入。
- Profile 识别和 Device 识别。
- 域名归一化、IDNA / Punycode 处理。
- 白名单、黑名单、安全、隐私、家长、广告规则匹配。
- 本地缓存和上游 DNS fallback。
- **凭据直驱**心跳、配置拉取、配置 ACK（凭据来自 `resolver install` 写入的 `configs/server.yaml`）。
- 查询日志和指标批量上报。
- NATS / HTTP 失败时本地 buffer。

不负责：

- 用户注册登录。
- 规则编辑。
- 支付订阅。
- 直接查询 MySQL。
- 直接依赖 `portal-web` 业务 API(Node 协议 `/api/v1/node/*` 与 Internal 协议 `/api/v1/internal/*` 仍由 `portal-web(原 console 域)` 暴露;**禁止**调用 Member 域业务 API)。
- 任何自助注册、bootstrap token、identity.json 兜底。

### 3.2 推荐 Go 包结构

```text
cmd/dns-resolver/main.go
internal/config
internal/agent
internal/dnsserver
internal/profile
internal/rules
internal/cache
internal/upstream
internal/logging
internal/metrics
internal/storage
internal/security
```

### 3.3 核心组件

| 组件 | 责任 |
|---|---|
| `Agent` | 心跳、拉配置、ACK、日志 / 指标上报（凭据来自 `Credentials` 内存结构，源自 yaml） |
| `ConfigStore` | 本地配置文件、版本、checksum、原子替换 |
| `ProfileResolver` | 通过 DoH path、DoT SNI、UDP 来源映射识别 Profile |
| `RuleEngine` | 规则优先级、精确 / 后缀 / 通配符匹配 |
| `DNSHandler` | DNS 查询处理、拦截响应、上游转发 |
| `LogBuffer` | 异步批量日志、本地失败缓冲、重放 |

## 4. geodns

### 4.1 职责

`geodns` 是服务入口调度层。

负责：

- 权威 DNS 响应 `dns.example.com` / `doh.example.com` 等服务域名。
- 根据来源 IP、GeoIP、节点健康、权重、优先级返回 resolver 地址。
- 离线节点摘除。
- 灰度调度和故障回退。

不负责：

- 用户域名过滤规则。
- 递归 DNS 上游查询。
- 用户 Profile 配置。
- 查询日志存储。
- DNS 递归查询。

### 4.2 实际 Go 包结构(2026-06-16 实测)

> 原 §4.2 推荐 6 个包,实际代码仅落地 4 个包:`internal/geoip` / `internal/cache` / `internal/adminapi` 三个包本期未实现,功能合并在 `internal/router` / `internal/server` 中;新增 `internal/config` 用于 yaml 加载。GeoIP 库与 admin API 待 Stage 06 补齐。

```text
cmd/geodns/main.go
internal/config        # yaml 加载与默认值
internal/server        # HTTP 服务 /health /health-view /pick
internal/healthview    # HealthViewClient + types
internal/router        # 区域/权重/健康路由决策
```

### 4.3 核心组件

| 组件 | 责任 |
|---|---|
| `DNSServer` | 权威 DNS 响应 `dns.example.com` / `doh.example.com` 等服务域名 |
| `GeoIPRouter` | 根据来源 IP 国家/区域选择节点区域 |
| `HealthViewClient` | 从 `portal-web(原 console 域)` `GET /api/v1/internal/geodns/health-view` 拉取健康节点视图(Go 代码零修改,只改 Endpoint) |
| `WeightRouter` | 根据节点权重分配流量，故障时回退 |
| `GrayScaleRouter` | 灰度调度逐步切流（Stage 06 完整） |
| `NodeCache` | 节点列表和健康状态本地缓存 |

## 5. 基础设施模块

| 模块 | MVP 用法 | 规模化用法 |
|---|---|---|
| MySQL | 业务和控制面元数据 | 主从 / 分库逻辑隔离 |
| Redis | 心跳快照、配置缓存 | 高可用、限流、调度状态 |
| ClickHouse | DNS 查询日志 | 集群、冷热分层、物化视图 |
| NATS | 可延后 | 日志、事件、配置变更通知 |
| Prometheus | 基础指标 | 告警、SLO、容量管理 |

## 6. 生成代码时的边界检查

生成后必须核对：

- `portal-web` Member 域中没有 resolver 心跳 controller;resolver 心跳 controller 位于 `portal-web(原 console 域)`(合并后)。
- `portal-web(原 console 域)` 中没有支付订单主流程(归 Member 域)。
- `portal-web` 财务表金额字段必须使用 `amount_minor bigint + currency`。
- `portal-web(原 console 域)` 只能通过进程内服务或 Internal API 向 `portal-web` Member 域上报 usage,不得直接修改财务表。
- `dns-resolver` 中没有 MySQL 连接配置。
- `dns-resolver` 中**没有** `bootstrap_token`、`identity.json`、`/api/v1/node/tokens/register` 调用;启动只走 `cfg.Validate()`。凭据在安装时通过 `POST /api/v1/node/tokens/verify` 一次性换取。
- `dns-resolver` Node 凭据(token/secret)必须从 `configs/server.yaml` 直读,**不允许**任何"凭据缺失就走旧流程"的兜底 / 回退 / 虚拟代码。
- `geodns` 中没有规则引擎。
- 所有配置变更都经过版本号和 checksum。
- 所有节点上报接口都使用 Bearer Token + HMAC-SHA256 双因子鉴权（`node.hmac` 中间件）。
- `audit_logs` 与 `admin_audit_logs` 是**两张独立表**,不允许任何代码通过 `INSERT INTO ... SELECT *` 跨表搬数。


## NextDNS Lite V1 模块补充

`portal-web` Member 域 Billing 模块只实现订阅费、Business/Education 人数 block 计费、退款、账务和对账;不实现 DNS query overage 自动收费。`portal-web(原 console 域)` 的 UsageWorker 只聚合 query count 并上报 portal-web Member 域,用于 quota 和统计。

