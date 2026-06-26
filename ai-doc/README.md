# OcerDNS Security Platform 文档

> DNS 安全过滤服务平台文档工作区，面向 AI 协作设计、实现和交付。

---

## 项目结构

```text
ai-doc-v1/
├── README.md                    ← 本文档：项目概览、结构说明与提示词使用说明
├── START.md                     ← 项目启动入口（AI 生成代码的唯一起始点）
├── REQUIREMENTS.md              ← 原始需求草案（追溯产品意图用，非实现依据）
│
├── rules/                       ← 规范定义
│   ├── checklist.md             ← 完整性检查清单（生成规格后逐条对照检查）
│   ├── coding.md                ← 编码规范（PSR 标准、错误处理、资源释放）
│   ├── naming.md                ← 命名规范（文件、变量、函数、数据库命名）
│   ├── template-defs.md         ← AI 文档生成框架 - 模板定义
│   ├── testing.md               ← 测试规范（单元/功能/集成/E2E 测试标准）
│   └── ui.md                    ← UI 设计规范（Element Plus、色板、布局、组件）
│
├── prompts/                     ← AI 动作指令（各场景提示词）
│   ├── generate-mvp.md          ← AI 生成 MVP 提示词
│   ├── feature-start.md         ← 功能开发提示词
│   ├── refactor.md              ← 重构提示词
│   ├── bug-fix.md               ← Bug 修复提示词
│   └── review.md                ← 代码审查提示词
│
├── project-doc/                 ← 总览文档
│   ├── 00-GOAL.md               ← 产品目标、技术栈和边界
│   ├── 01-ARCHITECTURE.md       ← 系统架构（控制面/数据面分离）
│   ├── 02-MODULES.md            ← 每个包的职责边界
│   ├── 03-DATA-FLOW.md          ← 数据流（配置、DNS 查询、心跳、日志、调度）
│   ├── 04-FEATURES.md           ← 全量产品功能蓝图（含未来版本）
│   ├── 05-PLANS.md              ← 实施计划
│   ├── 06-MVP-SCOPE.md          ← MVP 范围
│   ├── 07-CHANGE-LOG.md         ← 变更日志
│   ├── 08-DELIVERY-CRITERIA.md  ← 交付门槛与验收标准
│   ├── 09-CLOSED-LOOP-AND-DATA-DESTINATIONS.md  ← 闭环与数据目标
│   ├── 10-NEXTDNS-LITE-BILLING.md                ← NextDNS Lite 计费方案
│   ├── 11-MEMBER-CENTER-V1.md   ← 会员中心 V1 规格（第一版必须实现的功能）
│   └── adr/                     ← 架构决策记录 (Architecture Decision Records)
│       ├── adr-001-laravel.md
│       ├── adr-002-superseded-mysql-redis.md
│       ├── adr-003-sanctum.md
│       └── adr-004-storage-mysql-redis-clickhouse-nats.md
│
├── specs/                       ← 按系统拆解的详细规格
│   ├── portal-web/              ← 用户门户规格
│   │   ├── api.md               ← REST API 接口规格
│   │   ├── data-schema.md       ← 数据表结构
│   │   └── billing-finance.md   ← 计费与财务规格
│   ├── dns-console-web/         ← DNS 控制台规格（已并入 portal-web）
│   │   ├── api.md               ← API 接口规格
│   │   └── data-model.md        ← 数据模型
│   ├── dns-resolver/            ← DNS 解析节点规格
│   │   ├── data-model.md        ← 数据结构定义
│   │   └── protocol.md          ← 通信协议
│   ├── geodns/                  ← 地域调度规格
│   │   ├── api.md               ← API 接口规格
│   │   └── data-model.md        ← 数据模型
│   ├── nats/                    ← 消息总线规格（后续扩展参考）
│   │   └── events.md            ← 事件定义
│   └── clickhouse/              ← 日志分析规格
│       └── tables.md            ← 表结构定义
│
├── contracts/                   ← API 契约定义
│   ├── openapi.yaml             ← OpenAPI 规范（生成 API 路由、请求、响应的依据）
│   ├── billing.schema.json      ← 计费数据校验 Schema
│   ├── geodns-health-view.schema.json
│   ├── node-heartbeat.schema.json    ← 节点心跳 Schema
│   ├── query-log.schema.json         ← 查询日志 Schema
│   ├── resolver-config.schema.json   ← Resolver 配置 Schema
│   ├── resolver-metrics.schema.json  ← Resolver 指标 Schema
│   └── examples/                ← 契约示例数据
│       ├── billing-usage-batch.sample.json
│       ├── nextdns-lite-plan-catalog.sample.json
│       ├── node-heartbeat.sample.json
│       ├── query-log.sample.json
│       ├── quota-snapshot-free-exceeded.sample.json
│       ├── quota-snapshot-pro-unlimited.sample.json
│       └── resolver-config.sample.json
│   ├── migrations/                  ← 数据库迁移脚本（Laravel PHP 迁移，MySQL）
│   └── clickhouse/
│       └── 001_dns_logs.sql                 ← DNS 日志表结构
│
├── deploy/                      ← 部署配置
│   ├── docker-compose.yml       ← 本地开发 Docker 编排
│   ├── env.example              ← 环境变量模板
│   └── local-dev.md             ← 本地开发指南
│
└── reports/                     ← 审查/改进报告
    ├── IMPROVEMENT_REPORT.md
    ├── MEMBER_CENTER_V1_CHANGE_REPORT.md
    ├── NEXTDNS_LITE_CHANGE_REPORT.md
    └── REVIEW_FINDINGS_FINANCE_AND_CLOSED_LOOP.md
```

---

## 当前目标架构（V1）

### 业务系统

| 系统 | 代码包 | 技术方向 | 数据存储 | 通信 |
|------|--------|---------|---------|------|
| 用户门户 | `portal-web` | Laravel + Vue 3 | MySQL / Redis | REST API（含原 console 域：节点管理、心跳、配置发布） |
| DNS 节点 | `dns-resolver` | Go 单二进制 | 本地内存 / 文件 buffer | DNS / HTTPS Agent API |
| 接入调度 | `geodns` | Go | 内存 | Health View / Selector Internal API |

### 基础设施

| 组件 | 版本 | 职责 | 阶段 |
|------|------|------|------|
| MySQL | 8.0 | 业务主库和控制面元数据 | V1 必选 |
| Redis | 7 | 缓存、限流、健康快照、短态数据 | V1 必选 |
| ClickHouse | 24.x | DNS 查询日志和聚合分析 | V1 必选 |
| NATS JetStream | 2.x | 异步事件、日志队列、用量事件 | V2+ 可选 |

> **NATS** 为 V2+ 可选事件总线，不属于 V1 必选链路。MVP 阶段使用同步 API + DB/Redis/队列任务即可。

### 职责描述

- **`portal-web`**：前台 + 会员控制台 + 总后台（含原 console 域：节点管理、心跳、配置版本、发布任务、ACK）；负责用户、订阅、支付、套餐、团队、审计
- **`dns-resolver`**：Go，部署在 DNS 服务器上的解析软件包；负责 DNS 查询处理、规则匹配、日志上报。V1 通过 HTTPS Agent API 上报给 `portal-web`，不直接写 Redis / ClickHouse / MySQL
- **`geodns`**：Go，负责地域调度和健康路由；只做入口调度，不参与实际 DNS 过滤查询

---

## 当前实现目录树

> 本节反映 `ocer-dns/` 仓库在 2026-06-16 的真实文件结构。  
> 与上文"目标架构（V1）"相比，`dns-console-web` 已被并入 `portal-web` 总后台。  
> 推荐结构（见 `02-MODULES.md` §1.2 / §2.2 / §3.2 / §4.2）与实际实现的差异见末尾"实现差异说明"。

```text
ocer-dns/
├── README.md
├── start-all.sh                    ← 一键启动脚本
├── stop-all.sh                     ← 一键停止脚本
├── .gitignore
│
├── dns-resolver/                   # 🐹 Go DNS 解析节点（单二进制部署）
│   ├── cmd/dns-resolver/
│   │   ├── install.go              ← resolver install 子命令
│   │   ├── main.go
│   │   └── main_test.go
│   ├── configs/
│   │   ├── config.example.yaml
│   │   └── server.yaml
│   ├── internal/                   # 14 个 Go 包
│   │   ├── agent/                  #    节点 agent（含 _test.go）
│   │   ├── blockresponse/          #    拦截响应
│   │   ├── cache/                  #    内存缓存（含 _test.go）
│   │   ├── clickhouse/             #    CH 客户端
│   │   ├── config/                 #    配置加载
│   │   ├── dnsserver/              #    DNS 服务
│   │   ├── doh/                    #    DoH 协议
│   │   ├── logging/                #    日志缓冲（含 _test.go）
│   │   ├── matching/               #    匹配引擎 + trie
│   │   ├── metrics/                #    指标
│   │   ├── profile/                #    Profile 解析
│   │   ├── resolver/               #    解析器核心
│   │   ├── rules/                  #    规则引擎 + 归一化
│   │   └── storage/                #    本地配置存储
│   ├── tests/                      # 端到端测试
│   │   ├── engine_test.go
│   │   ├── normalize_test.go
│   │   └── profile_resolver_test.go
│   ├── Dockerfile
│   ├── README.md
│   ├── dns-resolver                 # 编译产物
│   ├── go.mod
│   └── go.sum
│
├── geodns/                          # 🐹 Go 地域调度服务
│   ├── cmd/geodns/main.go
│   ├── configs/config.example.yaml
│   ├── internal/
│   │   ├── config/
│   │   ├── healthview/              #    健康视图 client（client.go + types.go）
│   │   ├── router/                  #    区域/权重/健康路由
│   │   └── server/                  #    HTTP 服务
│   ├── tests/router_test.go
│   ├── Dockerfile
│   ├── README.md
│   ├── go.mod
│   └── go.sum
│
└── portal-web/                      # 🐘 Laravel 12 + Vue 3 主应用
    ├── app/
    │   ├── Domain/                  # 业务领域层（13 个子域）
    │   │   ├── ApiKey/              #    API Key 服务
    │   │   ├── Audit/               #    审计
    │   │   ├── Auth/                #    Auth / NodeToken / Permission
    │   │   ├── ClickHouse/          #    CH 统计
    │   │   ├── ConfigVersion/       #    CanonicalJson / Checksum / ConfigAck / ConfigBuild
    │   │   ├── HealthView/          #    节点健康视图
    │   │   ├── Heartbeat/           #    心跳服务
    │   │   ├── Ingest/              #    QueryLogIngest / QueryLogRead
    │   │   ├── Profile/             #    DomainNormalizer / MemberCenter / MemberWorkspace
    │   │   │                        #    ProfileConfigBuilder / ProfilePublish / ProfileService
    │   │   ├── Publish/             #    发布任务
    │   │   ├── Rule/                #    ProfileRule / RuleService
    │   │   ├── System/              #    健康检查
    │   │   └── Team/                #    团队
    │   ├── Http/
    │   │   ├── Controllers/
    │   │   │   ├── Api/V1/
    │   │   │   │   ├── Admin/       # 管理后台（13 个控制器）
    │   │   │   │   │   ├── AdminAlertController.php
    │   │   │   │   │   ├── AdminAuditLogController.php
    │   │   │   │   │   ├── AdminConsoleAuditLogController.php
    │   │   │   │   │   ├── AdminDeviceController.php
    │   │   │   │   │   ├── AdminGeoDnsController.php
    │   │   │   │   │   ├── AdminNodeController.php
    │   │   │   │   │   ├── AdminPublishController.php
    │   │   │   │   │   ├── AdminQueryLogController.php
    │   │   │   │   │   ├── AdminRuleController.php
    │   │   │   │   │   ├── AdminStatsController.php
    │   │   │   │   │   ├── AdminSystemConfigController.php
    │   │   │   │   │   ├── AdminTeamController.php
    │   │   │   │   │   └── AdminUserController.php
    │   │   │   │   ├── Agent/       # Resolver 上报（4 个）
    │   │   │   │   │   ├── ConfigAckController.php
    │   │   │   │   │   ├── ConfigPullController.php
    │   │   │   │   │   ├── HeartbeatController.php
    │   │   │   │   │   └── QueryLogController.php
    │   │   │   │   ├── Internal/    # 内部 API（3 个）
    │   │   │   │   │   ├── HealthViewController.php
    │   │   │   │   │   ├── ProfilePublishController.php
    │   │   │   │   │   └── QueryLogReadController.php
    │   │   │   │   ├── Member/      # 会员中心（7 个）
    │   │   │   │   │   ├── ApiKeyController.php
    │   │   │   │   │   ├── MemberCenterController.php
    │   │   │   │   │   ├── MemberWorkspaceController.php
    │   │   │   │   │   ├── ProfileController.php
    │   │   │   │   │   ├── ProfilePublishController.php
    │   │   │   │   │   ├── ProfileRuleController.php
    │   │   │   │   │   └── TeamController.php
    │   │   │   │   └── Public/
    │   │   │   │       └── AuthController.php
    │   │   │   └── Controller.php
    │   │   └── Middleware/          # 4 个中间件
    │   │       ├── AuthenticateNodeToken.php
    │   │       ├── CheckPermission.php
    │   │       ├── RequireSharedToken.php
    │   │       └── VerifyRequestSignature.php
    │   ├── Infrastructure/
    │   │   └── ClickHouse/          # ClickHouseClient / MemberAnalyticsService
    │   ├── Models/                  # 30 个 Eloquent 模型
    │   │   ├── Admin.php / AdminAuditLog.php / AdminPermission.php
    │   │   ├── AdminRole.php / AdminRoleNavRule.php
    │   │   ├── ApiKey.php / AuditLog.php
    │   │   ├── ConfigVersion.php / Device.php
    │   │   ├── GeoDnsMapping.php / NavigationCatalog.php
    │   │   ├── Node.php / NodeHeartbeat.php / NodeToken.php
    │   │   ├── Permission.php / Profile.php
    │   │   ├── ProfileRule.php / ProfileVersion.php
    │   │   ├── PublishTask.php / QueryLogEntry.php
    │   │   ├── QueryLogIngestBatch.php / RolePermission.php
    │   │   ├── RuleSource.php / SystemConfig.php
    │   │   ├── TaskExecution.php / Team.php
    │   │   ├── TeamInvitation.php / TeamMember.php
    │   │   └── User.php
    │   └── Providers/
    │       └── AppServiceProvider.php
    ├── bootstrap/                   # Laravel 启动
    │   ├── app.php
    │   ├── providers.php
    │   └── cache/
    ├── config/                      # 12 份 Laravel 配置
    │   ├── app.php / auth.php / cache.php
    │   ├── clickhouse.php / database.php
    │   ├── filesystems.php / logging.php
    │   ├── mail.php / queue.php
    │   └── sanctum.php / services.php / session.php
    ├── database/
    │   ├── migrations/              # 20 份迁移（19 PHP + 1 SQL）
    │   │   ├── 0001_01_01_000000_create_users_table.php
    │   │   ├── 0001_01_01_000001_create_profiles_table.php
    │   │   ├── 001_portal_web_mvp.sql
    │   │   ├── 2026_06_12_073324_create_cache_table.php
    │   │   ├── 2026_06_12_120000_add_member_center_settings.php
    │   │   ├── 2026_06_12_130000_create_teams_table.php
    │   │   ├── 2026_06_12_130001_create_team_members_table.php
    │   │   ├── 2026_06_12_130002_create_team_invitations_table.php
    │   │   ├── 2026_06_12_130003_create_audit_logs_table.php
    │   │   ├── 2026_06_12_130004_create_permissions_table.php
    │   │   ├── 2026_06_12_130005_create_role_permissions_table.php
    │   │   ├── 2026_06_12_130006_add_current_team_id_to_users.php
    │   │   ├── 2026_06_16_090000_create_console_web_tables.php
    │   │   ├── 2026_06_16_090001_create_query_log_entries_table.php
    │   │   ├── 2026_06_16_090002_add_admin_crud_tables.php
    │   │   ├── 2026_06_16_090003_add_hmac_key_hash_to_node_tokens.php
    │   │   ├── 2026_06_16_090004_create_cache_table_console.php
    │   │   ├── 2026_06_16_100000_create_dns_admins_table.php
    │   │   └── 2026_06_16_110000_create_api_keys_table.php
    │   ├── seeders/
    │   │   └── DatabaseSeeder.php
    │   └── factories/
    ├── public/                      # Web 入口
    │   ├── .htaccess
    │   ├── favicon.ico
    │   ├── index.php
    │   └── robots.txt
    ├── routes/
    │   ├── api.php
    │   ├── console.php
    │   └── web.php
    ├── tests/                       # PHPUnit
    │   ├── Feature/
    │   │   ├── AgentHmacSignatureTest.php
    │   │   ├── MemberWorkspaceTest.php
    │   │   └── ProfilePublishTest.php
    │   ├── Unit/
    │   │   └── .gitkeep
    │   └── TestCase.php
    ├── web/                         # 🎨 Vue 3 前端
    │   ├── src/
    │   │   ├── App.vue              #    Vue 根组件
    │   │   ├── main.js              #    入口（注册 Element Plus 图标/路由/i18n）
    │   │   ├── api/
    │   │   │   └── client.js        #    Axios 实例（请求/响应拦截器）
    │   │   ├── assets/
    │   │   │   └── theme.css
    │   │   ├── components/
    │   │   │   ├── AdminLayout.vue  #    管理后台侧边栏布局
    │   │   │   └── Layout.vue       #    会员中心顶部导航布局
    │   │   ├── locales/             # 4 份 i18n
    │   │   │   ├── index.js
    │   │   │   ├── zh-CN.js
    │   │   │   ├── en.js
    │   │   │   └── ja.js
    │   │   ├── router/
    │   │   │   └── index.js         #    Vue Router + 守卫
    │   │   └── views/
    │   │       ├── (21 个会员中心页面)
    │   │       │   ├── Home.vue / Login.vue / Register.vue
    │   │       │   ├── Dashboard.vue / ProfileList.vue / ProfileDetail.vue
    │   │       │   ├── Security.vue / Privacy.vue / ParentalControl.vue
    │   │       │   ├── blocklist.vue / Allowlist.vue
    │   │       │   ├── Analytics.vue / Logs.vue
    │   │       │   ├── Devices.vue / APIKeys.vue / Settings.vue
    │   │       │   ├── Membership.vue
    │   │       │   └── TeamList.vue / TeamCreate.vue
    │   │       │       / TeamDetail.vue / TeamInvitations.vue
    │   │       └── admin/           # 12 个管理后台页面
    │   │           ├── AdminLogin.vue
    │   │           ├── Dashboard.vue        # 仪表盘
    │   │           ├── Nodes.vue            # 节点管理
    │   │           ├── Publishes.vue        # 发布任务
    │   │           ├── GeoDNS.vue           # 地域调度
    │   │           ├── RuleLibrary.vue      # 规则库
    │   │           ├── QueryLogs.vue        # 查询日志
    │   │           ├── Alerts.vue           # 告警中心
    │   │           ├── Users.vue            # 用户管理
    │   │           ├── Devices.vue          # 设备管理
    │   │           ├── SystemConfig.vue     # 系统配置
    │   │           └── AuditLogs.vue        # 审计日志
    │   ├── index.html
    │   ├── package.json
    │   ├── package-lock.json
    │   └── vite.config.js
    ├── Dockerfile
    ├── Makefile
    ├── README.md
    ├── artisan
    ├── composer.json
    ├── composer.lock
    ├── package.json
    └── phpunit.xml
```

### 实现差异说明

| 类别 | 文档推荐（`02-MODULES.md`） | 实际实现 | 差异说明 |
|---|---|---|---|
| 包数量 | 4 包（独立） | 3 包（`dns-console-web` 已并入 `portal-web`） | 已合并，dns-console-web 不再作为独立包存在 |
| `portal-web/app/Domain` | `Node/Heartbeat/ConfigVersion/PublishTask/HealthView/Ingest/RuleLibrary/SystemConfig/AdminConsoleAudit` 等 10+ 子域平铺 | 13 个子域：**`ConfigVersion/Heartbeat/HealthView/Ingest/Publish`** 替代了推荐命名 | 命名对齐 `config_version` / `heartbeat` / `health_view` 等业务概念 |
| `portal-web/app/Domain`（Member 域） | `Plan/Billing/Usage/Service/Device/Admin` 等独立子域 | 实际未拆分 `Plan/Billing/Usage/Service` 独立子域；Member 业务集中在 `Profile/Member*/Team` 子域 | 推荐结构中"按业务垂直拆分"未完全落地 |
| `dns-resolver/internal` | `config/agent/dnsserver/profile/rules/cache/upstream/logging/metrics/storage/security`（11 个） | 14 个：多出 `blockresponse/clickhouse/doh/matching/resolver` | 实际多了 DoH 协议、CH 客户端、trie 匹配、解析器核心 |
| `geodns/internal` | `server/healthview/geoip/router/cache/adminapi`（6 个） | 4 个：**缺 `geoip/cache/adminapi`** | GeoIP 库与 admin API 暂未实现，路由逻辑集中在 `router` |
| 前端 `web/src/` | 未文档化 | 21 会员页 + 12 后台页 + 2 布局 + 4 份 i18n | 本节首次落档 |
| 后端 Controllers | 未列名 | 5 分类共 28 个（`Admin×13 / Agent×4 / Internal×3 / Member×7 / Public×1`） | 本节首次落档 |
| Eloquent Models | 未列名 | 30 个 | 本节首次落档 |
| Middleware | 未列名 | 4 个（`AuthenticateNodeToken / CheckPermission / RequireSharedToken / VerifyRequestSignature`） | 本节首次落档 |
| Migrations | 仅 3 份 SQL 草案 | 19 份 PHP 迁移 + 1 份 SQL | Laravel 风格迁移为主，SQL 草案仅保留 1 份 |
| 基础设施层 | `app/Infrastructure/DnsConsole`（兼容旧名） | 实际仅有 `app/Infrastructure/ClickHouse` | `DnsConsole` 客户端已删除，调用改为 `portal-web` 进程内服务 |

> 维护说明：当 `ocer-dns/` 发生目录变更时（新增/删除/重命名包或控制器），需同步更新本节。变更较大的情况下应拆出独立的 `12-ACTUAL-IMPLEMENTATION.md`，并把本文此节替换为指向新文档的链接。

---

## 提示词使用说明

### 1. `prompts/generate-mvp.md` — AI 生成 MVP 提示词

**用途**：在确认生成 MVP 代码时，将该文件内容作为提示词开头使用。

**使用场景**：从零开始生成可运行 MVP 代码时。

**使用方式**：将该文件中的提示词模板复制到 AI 对话中，AI 会按 START.md 入口顺序读取文档并生成代码。

**触发词**：`请使用 START.md 作为入口生成 OcerDNS Security Platform 的 MVP`

---

### 2. `prompts/feature-start.md` — 功能开发提示词

**用途**：AI 执行新功能开发任务时使用，涵盖从需求理解到编码实现、文档同步的全流程。

**使用场景**：需要在已有代码基础上新增功能时。

**核心流程**：需求理解 → 设计评审（复杂功能） → 编码实现 → 同步更新文档 → 自检

**文档同步要求**：必须同步更新 `specs/{system}/api.md`、`data-schema.md`、`project-doc/05-PLANS.md`、`07-CHANGE-LOG.md`。

---

### 3. `prompts/refactor.md` — 重构提示词

**用途**：AI 执行代码重构任务时使用，确保重构不改变外部行为。

**使用场景**：需要优化代码结构、提升可维护性时。

**核心原则**：保持行为不变、小步前进、随时可停止、配套测试验证。

**常见重构类型**：函数重构（提取/合并/参数对象）、变量重构（重命名/提取/内联/魔术数字）、类重构（提取/合并/移动方法）、条件表达式重构（提取条件/合并条件/卫语句）。

**使用前必须阅读**：`project-doc/01-ARCHITECTURE.md`、`rules/coding.md`、`rules/naming.md`

---

### 4. `prompts/bug-fix.md` — Bug 修复提示词

**用途**：AI 执行 Bug 修复任务时使用，遵循最小化修改原则。

**使用场景**：代码中出现 Bug 需要定位和修复时。

**核心原则**：只修改与 Bug 直接相关的代码、不破坏现有行为、不改变公共 API、不降低安全性、修复根因不掩盖现象。

**修复流程**：问题分析（复现→定位→根因） → 修复实施（最小化修改） → 验证

**输出格式**：Bug 修复报告（问题描述、根因分析、修复方案、修改文件、检查清单）

---

### 5. `prompts/review.md` — 代码审查提示词

**用途**：AI 执行生产级后端代码审查时使用。

**使用场景**：代码合并请求（PR/MR）审查时。

**审查重点**（按优先级）：
1. 安全漏洞（注入风险、认证绕过、数据泄露）
2. 数据正确性风险
3. 线上稳定性风险
4. 性能问题
5. 可扩展性问题

**审查前必须阅读**：`rules/checklist.md`、`project-doc/adr/*`、`rules/coding.md`、相关业务设计文档

---

## 启动方式

```text
提示词："请使用 START.md 作为入口，启动我的项目。"
```

AI 会按照 START.md 定义的必读顺序加载文档，依次读取项目结构 → 产品目标 → 系统架构 → 模块边界 → 数据流 → MVP 范围 → 交付标准，然后开始代码生成。

---

## 说明

- 旧文档中的 `dns-control-web`、`admin-web`、`control-plane`、`dns-console-web` 属于历史命名，不再作为目标架构定义。当前唯一业务包为 `portal-web`、`dns-resolver`、`geodns`。
- 这套文档适合做 AI 辅助设计和开发，不应把"文档已设计完成"等同于"工程已验收完成"。
- 进入实现阶段前，必须同时阅读 `project-doc/08-DELIVERY-CRITERIA.md`，按交付门槛验证，而不是只看功能是否齐全。
- 每个功能开发完成后，必须同步更新 `specs/`、`project-doc/` 下的对应文档，并在 `07-CHANGE-LOG.md` 中记录变更。
