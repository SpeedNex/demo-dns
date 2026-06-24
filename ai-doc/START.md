# START.md — 项目生成入口（改进版）

> 使用本文件作为唯一启动入口。目标是先生成稳定文档规格，再生成可运行 MVP 代码，避免旧文档、旧命名和未确认范围干扰生成结果。

## 0. 变更记录规则

代码变更后必须同步更新 `project-doc/07-CHANGE-LOG.md`：

```text
| 日期 | 类型 | 描述 | 涉及文件 | 状态 |
|---|---|---|---|---|
| YYYY-MM-DD | code/docs/test | 简要描述 | file1, file2 | ok/pending |
```

- **时机**：每次功能增减、Bug 修复、文档变更时
- **类型**：code=代码, docs=文档, test=测试
- **状态**：doc_status / impl_status / test_status

---

## 1. 总原则

生成或开发本项目时必须遵守：

```text
必须按以下顺序读取，禁止跳过或乱序：

  先读 README + project-doc     → 确认架构和范围
  再读 specs + contracts         → 确认模块规格和契约
  再读 migrations + deploy       → 确认数据库和部署
  最后才能生成代码               → 在充分理解后开始生成

禁止直接从历史目录生成代码。
```

禁止作为当前包名使用：

```text
- admin-web
- dns-control-web
- control-plane
- dns-console-web           ← 历史包,已并入 portal-web,目录仅留 Layout.vue 占位

当前唯一业务包：
- portal-web
- dns-resolver
- geodns
```

目录约束：

```text
portal-web 前端源码必须位于 portal-web/web
禁止把当前业务前端源码放在仓库根级共享目录（例如 ocer-dns/web）
dns-console-web 仅保留 web/src/views/Layout.vue 一个占位文件,实际实现见 portal-web
```

## 2. 必读顺序

| 顺序 | 文件 | 目的 |
|---:|---|---|
| 1 | `README.md` | 确认包结构、历史目录和当前目标 |
| 2 | `project-doc/00-GOAL.md` | 确认产品目标、技术栈和边界 |
| 3 | `project-doc/01-ARCHITECTURE.md` | 确认系统架构和控制面 / 数据面分离 |
| 4 | `project-doc/02-MODULES.md` | 确认每个包的职责边界 |
| 5 | `project-doc/03-DATA-FLOW.md` | 确认用户配置、DNS 查询、心跳、日志、调度链路 |
| 6 | `project-doc/04-FEATURES.md` | 确认全量产品功能蓝图（区分 V1 / V2+） |
| 7 | `project-doc/06-MVP-SCOPE.md` | 确认第一版只做哪些能力 |
| 8 | `project-doc/11-MEMBER-CENTER-V1.md` | 确认会员中心安全、隐私、家长监护、黑白名单、统计、日志、设置、套餐入口 |
| 9 | `project-doc/10-NEXTDNS-LITE-BILLING.md` | 确认计费方案（Free 300k / Pro Unlimited / Business Seat） |
| 10 | `project-doc/08-DELIVERY-CRITERIA.md` | 确认验收标准和交付等级 |
| 11 | `contracts/openapi.yaml` | 生成 API 路由、请求、响应和测试 |
| 12 | `contracts/*.schema.json` | 生成配置、心跳、日志、指标校验 |
| 13 | `specs/*` | 生成模块实现细节 |
| 14 | `migrations/*` | 生成数据库结构 |
| 15 | `project-doc/15-CONFIG-ARCHITECTURE.md` | 确认配置拉取架构（Global Config + Lazy Profile） |
| 16 | `deploy/*` | 生成本地启动和部署配置 |

## 2.1 API 路径约定（已锁定）

```text
Auth API:     /api/v1/auth/*        ← 无鉴权，注册、登录、管理员登录
User API:     /api/v1/user/*        ← 用户登录（Sanctum Token），会员中心
Admin API:    /api/v1/admin/*       ← 管理员登录（Sanctum Token），后台管理
Node API:     /api/v1/node/*        ← resolver 心跳、Global Config 拉取、Profile 按需拉取、版本检查、日志上报
Internal API: /api/v1/internal/*    ← 跨进程调用，Shared Token 鉴权
Build API:    /api/v1/build/*       ← 静态资源，安装脚本下载
Stripe:       /api/v1/stripe/webhook ← Stripe Webhook（无鉴权）
```

Node API 子路径（`node.api_key` 鉴权）：

```text
dns-resolver/
├── config                          GET    全局配置（Global Config，不含任何用户数据）
├── config/ack                      POST   配置确认
├── profiles/{profile_id}           GET    单个 Profile 配置（按需拉取）
├── profiles/check                  POST   批量版本检查
├── query-logs                      POST   查询日志上报
└── devices/seen                    POST   设备上报
```

> **历史路径已废弃**：`/api/v1/member/*` → `/api/v1/user/*`；`/api/v1/agent/*` → `/api/v1/node/*`；`/api/v1/public/*` → `/api/v1/auth/*`

## 3. 当前系统清单

| 系统 | 代码包 | 技术方向 | 数据存储 | 通信 |
|---|---|---|---|---|
| 用户门户 + 总后台 | `portal-web`(含 User 域与原 console 域) | Laravel + Vue 3 | MySQL / File Cache | REST API / Node API / Internal API |
| DNS 节点 | `dns-resolver` | Go 单二进制 | 本地内存 / 文件 buffer | DNS / HTTPS Agent API |
| 接入调度 | `geodns` | Go | 内存 / Redis 快照 | Authoritative DNS / Internal API |
| 日志分析 | `clickhouse` | ClickHouse | MergeTree | HTTP / Native |
| 消息总线 | `nats`（V2+ 可选） | NATS JetStream | Stream | Pub/Sub |

## 4. 生成阶段

### 阶段 A：文档完整性校验

生成代码前必须检查：

- `portal-web` 是否有 API、数据表、权限、验收标准。
- `portal-web` 前端是否收口在 `portal-web/web`，不得漂移到包外共享目录。
- `dns-console-web` 是否有节点注册、心跳、配置发布、配置拉取、ACK、日志 / 指标接收。
- `dns-resolver` 是否有配置结构、Profile 识别、规则引擎、日志 buffer、心跳、热加载。
- `geodns` 是否只做入口调度，不参与实际 DNS 过滤查询。
- `contracts/` 是否有 OpenAPI 和 JSON Schema。
- `migrations/` 是否有 MySQL 和 ClickHouse 迁移。
- （历史目录 `archive/`、`_original_source/` 等已清理，不再存在。）

### 阶段 B：生成 MVP 代码

MVP 只生成以下闭环：

```text
用户注册登录
创建 Profile
会员中心页面：安全 / 隐私 / 家长监护 / 黑名单 / 白名单 / 统计 / 日志 / 设置 / 会员中心
添加白名单 / 黑名单
配置安全、隐私、家长监护 Lite
发布配置版本
resolver 节点预创建 + `resolver install` 写入凭据
resolver 拉取配置并热加载
DoH + UDP DNS 查询
规则命中拦截 / 默认放行
日志批量上报
portal-web 查询日志
console-web 查看节点在线状态
超额计费（用量从 query log batch 派生，写接口幂等）
余额 / 账单 / 充值 / 退款（amount_minor bigint，发票定稿不可变）
告警通知（用量超额、扣费失败、节点离线、Heartbeat 异常、登录风控、Payment webhook 失败）
域名分类统计（按安全/隐私/家长等分类维度对查询/拦截/用量做聚合）
```

第一版明确不做（保持向后兼容、可后续叠加）：

```text
Anycast
SCIM
企业专属节点
复杂规则源商业化管理
```

### 阶段 C：生成测试与运行材料

每个包至少生成：

- `.env.example`
- README / local run command
- 数据库迁移
- API Feature Test
- 核心 Service 单元测试
- 构建脚本
- 健康检查端点

### 阶段 D：验收

必须按 `project-doc/08-DELIVERY-CRITERIA.md` 给出证据：

```text
composer test / php artisan test
npm build
go test ./...
go vet ./...
docker compose config
database migration dry-run
OpenAPI 路由覆盖检查
JSON Schema 校验样例
```

没有证据时，只能标记为“文档完成”或“代码草案”，不能标记为“生产完成”。

## 5. 重要边界说明

### 5.1 心跳与日志上报的区别

```text
节点心跳：resolver → portal-web(原 console 域)
用于证明节点在线、健康、负载、配置版本是否一致。

查询日志：resolver → portal-web(原 console 域) Node API（HTTP batch `/api/v1/node/query-logs/batch`，本地 buffer 持久化）；V2+ 规模化阶段可再切换为 NATS ingestion → portal-web(原 console 域) log worker → ClickHouse
用于记录用户 DNS 查询、命中规则、拦截动作、延迟和用量；resolver 不得直接写 ClickHouse。
```

两者都可能由 `dns-resolver` 发起，但不能混用同一个接口。

### 5.2 GeoDNS 与 DNS 查询的区别

```text
GeoDNS：负责服务域名的入口调度，返回合适 resolver 地址。
resolver：负责真实用户 DNS 查询、规则匹配和上游解析。
```

GeoDNS 不应作为每一次用户 DNS 查询的中间代理。

### 5.3 Resolver 配置拉取机制（Global Config + Lazy Profile）

```text
架构：Global Config（全量同步）+ Profile Config（按需懒加载）

Global Config（GET /config）：
  - 启动时拉取 + 定时刷新（5 分钟）
  - 仅返回公共运行参数：upstreams / plans / rulesets / limits
  - 绝对不包含任何用户 Profile 数据
  - 保存为 data/global.json

Profile Config（GET /profiles/{profile_id}）：
  - 按需拉取：Memory Cache MISS → Disk Cache MISS → Portal
  - SingleFlight 防击穿：同一 Profile 并发 5000 请求只回源 1 次
  - 保存为 data/profiles/{prefix}/{profile_id}.json

版本检查（POST /profiles/check）：
  - 每 5 分钟批量检查本地缓存的 Profile 版本
  - Portal 返回有更新的 Profile ID 列表
  - V2 升级为 Redis PubSub 主动通知

淘汰策略：
  - Memory Cache：LRU，上限 5000，30 分钟无命中淘汰
  - Disk Cache：LRU，上限 20000，7 天无命中删除文件
```

### 5.4 dns-resolver 鉴权 Token 统一来源

```text
所有鉴权接口（heartbeat, config GET, config POST ack, query-logs, install register）
必须统一从 api_key_path 文件读取 token，且启动前校验文件存在性
缺失则 Fatal 拒绝启动，禁止 fallback 到 yaml 配置
```

### 5.5 ClickHouse 直写模式

```text
查询日志链路已优化：resolver → portal-web → ClickHouse（直接写入）
不再经过 MySQL 中间表（query_log_entries / query_log_ingest_batches 已删除）
```

### 5.6 节点 region 字段替代 node_type

```text
节点类型通过 region 字段区分：
- region LIKE 'resolver-%'  → resolver 节点
- region LIKE 'geodns-%'    → GeoDNS 节点
- region = 'local'          → 本地回环节点
node_type 字段已删除
```

### 5.7 后台导航菜单动态化

```text
菜单配置通过 admin_menu_groups + admin_menu_rules 表动态管理
前端从 API /admin/menu-config 的 groups 字段读取
不再硬编码分组数组
```

### 5.8 节点部署钥匙机制

```text
签发接口：POST /api/v1/admin/nodes/{id}/tokens（expires_in_days: 1）
缓存机制：本地缓存 24h，超期后自动重新签发
Token 格式：去除 ocnd_ 前缀后展示和复制
```

## 6. 生成提示词建议

```text
请使用 START.md 作为入口，严格忽略 archive/historical-specs 作为当前代码包，只按 portal-web（含原 console 域）、dns-resolver、geodns 三个目标包生成 MVP。先输出生成计划，再按 contracts/openapi.yaml、contracts/*.schema.json 和 specs/* 生成代码、迁移、测试和运行说明。
```

## 7. 数据库表命名规范

所有数据表必须使用 `dns_` 前缀，确保命名统一：

```text
正确：
- dns_users              ← 用户主表（主键 uid）
- dns_admins             ← 管理员主表（主键 admin_id）
- dns_profiles           ← DNS 配置方案
- dns_profile_versions   ← 配置方案版本
- dns_devices            ← 设备注册
- dns_teams              ← 团队
- dns_team_members       ← 团队成员
- dns_team_invitations   ← 团队邀请
- dns_team_roles         ← 团队角色
- dns_team_permissions   ← 团队权限
- dns_team_role_permissions ← 团队角色权限关联
- dns_team_user_roles    ← 团队用户角色关联
- dns_admin_user_roles   ← 管理员角色关联
- dns_resolver_nodes     ← resolver 节点（含 region 字段区分类型）
- dns_resolver_node_tokens     ← 节点 Token（HMAC 签名凭据）
- dns_resolver_node_heartbeats ← 节点心跳记录
- dns_geodns             ← GeoDNS 调度映射
- dns_geodns_tokens      ← GeoDNS 节点 Token
- dns_config_versions    ← 配置版本
- dns_publish_tasks      ← 发布任务
- dns_task_executions    ← 任务执行记录
- dns_rule_sources       ← 规则来源
- dns_profile_rules      ← 配置文件规则
- dns_rule_items         ← 规则条目
- dns_regions            ← 区域管理
- dns_subscriptions      ← 用户订阅
- dns_orders             ← 订单
- dns_wallets            ← 钱包
- dns_wallet_transactions ← 钱包流水
- dns_payment_transactions ← 支付交易
- dns_billing_periods    ← 账期
- dns_billings           ← 账单主表
- dns_billing_items      ← 账单明细
- dns_usage_records      ← 用量记录
- dns_aggregation_offsets ← 聚合偏移量
- dns_plans              ← 套餐
- dns_plan_prices        ← 套餐价格
- dns_plan_features      ← 套餐功能
- dns_alerts             ← 告警
- dns_admin_audit_logs   ← 审计日志
- dns_stripe_webhook_logs ← Stripe Webhook 日志
- dns_job_executions    ← 定时任务执行记录
- dns_personal_access_tokens ← Sanctum Token
- dns_admin_roles        ← 管理员角色
- dns_admin_permissions  ← 管理员权限
- dns_admin_role_permissions ← 角色权限关联
- dns_admin_menu_rules   ← 后台菜单规则
- dns_system_configs     ← 系统配置
- dns_api_keys           ← API 密钥
- dns_policy_snapshots   ← 策略快照
- dns_policy_publish_logs ← 策略发布日志
- dns_cache              ← 缓存
- dns_cache_locks        ← 缓存锁

错误（禁止）：
- users          ← 缺少前缀
- teams          ← 缺少前缀
- team_members   ← 缺少前缀
- node_tokens    ← 缺少前缀和命名空间
```

### 7.1 主键命名规范

| 表类型 | 主键命名 | 说明 |
|---|---|---|
| 用户表 | `uid` (BIGINT UNSIGNED) | 用户唯一标识 |
| 管理员表 | `admin_id` (BIGINT UNSIGNED) | 管理员唯一标识 |
| 其他业务表 | `id` (bigIncrements) | 自增主键 |

模型文件中必须显式指定表名和主键，例如：
```php
class User extends Model
{
    protected $table = 'dns_users';
    protected $primaryKey = 'uid';
    public $incrementing = true;
    protected $keyType = 'int';
}

class Admin extends Model
{
    protected $table = 'dns_admins';
    protected $primaryKey = 'admin_id';
    public $incrementing = true;
    protected $keyType = 'int';
}
```

### 7.2 外键引用规范

关联关系中必须显式声明外键，避免 Laravel 默认推断错误：
```php
// 正确
return $this->belongsTo(User::class, 'user_id');

// 错误（依赖 Laravel 自动推断）
return $this->belongsTo(User::class);
```

### 7.3 节点 Token 表重命名记录

| 日期 | 旧名称 | 新名称 | 说明 |
|---|---|---|---|
| 2026-06-23 | `dns_node_tokens` | `dns_resolver_node_tokens` | 统一 resolver 相关子表命名 |
| 2026-06-23 | `dns_node_heartbeats` | `dns_resolver_node_heartbeats` | 统一 resolver 相关子表命名 |



## 8. Hard Constraints（已锁定，代码中必须遵守）

```text
1. Go Resolver 禁止全量重写，必须采用最小化修改方案
2. Engine 必须支持多 Profile 隔离，通过 map[profileID]*profileEngine 实现
3. loadBundleIntoEngine 必须循环加载所有 Profile，不得仅取第一个 Profile
4. Resolver 必须按 ProfileID 路由规则匹配，使用 MatchWithProfile 方法
5. Member 接口需按已修改的 User 接口使用，禁止使用 Member 接口
6. User 接口路由必须使用 user.only 中间件，Admin 接口路由必须使用 admin.only 中间件
7. 数据库表中用户ID字段必须命名为 uid，管理员ID字段必须命名为 admin_id
8. 所有外键引用需指向新主键名
9. Eloquent Model 需声明新主键（User模型 primaryKey='uid'，Admin模型 primaryKey='admin_id'）
10. 代码中所有 $user->id 引用需更新为 $user->uid，$admin->id 引用需更新为 $admin->admin_id
11. 关联关系中需显式声明外键（如 belongsTo(User::class, 'user_id')）以避免Laravel默认推断错误
12. Redis 和 ClickHouse 链接信息必须使用数据库基本配置中的数据，禁止使用环境变量中的数据
13. DNS resolver 本地日志 buffer 路径必须使用 /var/lib/ocer-dns/log-buffer，禁止使用 /tmp 路径
14. 必须定期执行 php artisan clickhouse:retry-failed-batches 命令
15. ClickHouse dns_logs 表必须包含 protocol 字段
16. Resolver 不得直接连接 ClickHouse
17. 必须定期执行 php artisan quota:check 命令（每5分钟）
18. Resolver 必须实现配额超限拒绝策略（quota_status=exceeded 时 DNS 返回 REFUSED，DoH 返回 403）
19. 注册时必须自动创建 Free 订阅记录
20. 设备IP识别必须去端口化
21. GeoDNS 端口统一为 15354
22. GeoDNS 权威 DNS 服务必须监听 UDP/TCP 53 端口
23. GeoDNS 必须定时拉取并本地缓存 portal-web 健康视图
24. DoH 查询直接访问 Resolver，不经过 GeoDNS
25. DoT/DoQ 查询必须经过 GeoDNS 调度
26. Resolver 配置拉取机制应使用版本号比较
27. Resolver 端口配置：DNS UDP 53、DNS TCP 53、DoT 853、DoQ 853/UDP、DoH 8443
28. dns-resolver 日志上报必须使用与 heartbeat/config 相同的 api_key
29. dns-resolver 所有鉴权接口必须统一从 api_key_path 文件读取 token
30. Profile 同名冲突检查：ProfileService::create() 需添加同名检查
```

## 9. 已完成 P0 修复清单

| 编号 | 问题 | 状态 | 说明 |
|---|---|---|---|
| P0#1 | 多 Profile 加载 | ok | loadBundleIntoEngine 循环加载所有 Profile |
| P0#2 | 配置版本主键对齐 | ok | task_executions.id 改为 string ULID |
| P0#3 | GeoDNS HMAC 签名 | ok | geodns/internal/signing/signing.go |
| P0#4 | Resolver DoQ 集成 | ok | DoQ server + Handler 统一处理 |
| P0#5 | 用量记录 device_id NOT NULL | ok | DEFAULT 0 |
| P0#6 | 审计日志 actor_admin_id nullable | ok | 支持 system/cron 字符串 |
| P0#N1 | job_executions.job_name→job_type | ok | 迁移 + 模型修复 |
| P0#N2 | build 路由路径修复 | ok | base_path('public/build/') |
| P0 计费 | 配额闭环 | ok | quota_status + quota:check |

## 10. 关键 Artisan Commands

```text
php artisan quota:check          # 每5分钟检测 Free 套餐用量是否超限
php artisan clickhouse:retry-failed-batches  # 补传失败的 ClickHouse 批次
php artisan admin:create          # 创建/重置管理员账号
php artisan billing:generate      # 生成账单
php artisan usage:aggregate       # 聚合用量记录
php artisan finance:verify        # 财务核对
```

## 11. MVP 成功闭环（已验证）

```text
用户注册 → 自动创建 Free subscription
  → 创建 Profile 和规则
  → portal-web 发布配置（同步触发，无需手动）
  → resolver 拉取配置（心跳返回 latest_config_version）
  → 用户设备发起 DNS/DoH/DoT/DoQ 查询
  → resolver 执行过滤（白名单 > 黑名单 > 安全/隐私/家长）
  → resolver 上报 query logs（直写 ClickHouse）
  → portal-web 查询日志/统计
  → usage:aggregate 聚合用量
  → quota:check 检测 Free 300,000 上限
  → quota_status=exceeded 时 resolver 返回 REFUSED/403
  → 用户升级 Pro → quota_status=unlimited → resolver 恢复正常
```

## 财务生成强约束

生成代码时必须优先读取：

```text
specs/portal-web/billing-finance.md
migrations/mysql/003_billing_finance.sql
contracts/billing.schema.json
project-doc/09-CLOSED-LOOP-AND-DATA-DESTINATIONS.md
```

财务规则：金额用 `amount_minor bigint`，禁止 float/double，所有财务写接口幂等，发票定稿后不可变，计费用量只从 query log batch 派生。

## 当前商业模式锁定：NextDNS Lite V1

本版本按低复杂度参考模型生成：Free 300,000 queries/month；Pro unlimited queries；Business 按 50 employees block；Education 按 250 students block；Enterprise custom contract。V1 阶段必须实现：套餐用量计算、超额判定与扣费（用量仅从 query log batch 派生）；余额、账单、充值、退款（金额 amount_minor bigint，写接口幂等，发票定稿不可变）；告警通知（用量超额、扣费失败、节点离线、Heartbeat 异常、登录风控、Payment webhook 失败）。

生成时必须优先读取：

```text
project-doc/10-NEXTDNS-LITE-BILLING.md
project-doc/11-MEMBER-CENTER-V1.md
specs/portal-web/billing-finance.md
project-doc/09-CLOSED-LOOP-AND-DATA-DESTINATIONS.md
contracts/billing.schema.json
contracts/openapi.yaml
```
