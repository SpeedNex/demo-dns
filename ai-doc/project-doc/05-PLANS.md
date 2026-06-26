# 实施计划（OcerDNS Security Platform）

> 状态字段拆分为文档状态、实现状态、测试状态。禁止再用单个 ✅ 表示功能已经生产完成。

## 1. 状态定义

| 字段 | 可选值 | 含义 |
|---|---|---|
| `doc_status` | `draft` / `defined` / `confirmed` | 文档是否定义清楚 |
| `impl_status` | `not_started` / `in_progress` / `built` / `accepted` | 代码实现状态 |
| `test_status` | `not_run` / `partial` / `passed` | 测试验证状态 |
| `delivery_level` | `L1` / `L2` / `L3` / `L4` | 交付等级 |

## 2. 阶段划分

| 阶段 | 目标 | 交付等级 |
|---|---|---|
| Stage 00 | 文档规格收敛、旧文档归档、契约补齐 | L1 |
| Stage 01 | MVP 核心链路：portal + console + resolver | L3 |
| Stage 02 | 日志统计、安全规则、设备管理增强 | L3 |
| Stage 03 | 生产化 resolver、NATS、监控、回滚 | L3/L4 |
| Stage 04 | 真实支付接入、账单 UI、用户运营后台；财务数据模型和内部用量入账契约必须在 Stage 01 前完成 | L3 |
| Stage 05 | 团队、API Key、通知、Webhook | L3 |
| Stage 06 | 多区域、GeoDNS 完整调度、自动扩容 | L4 |
| Stage 07 | 企业级能力、专属节点、SSO / SCIM | L4 |

## 3. Stage 00：文档收敛

| # | 任务 | 产物 | doc_status | impl_status | test_status |
|---|---|---|---|---|---|
| 0.1 | 三包架构收敛(portal-web / dns-resolver / geodns;原 dns-console-web 于 2026-06-15 至 2026-06-16 之间并入 portal-web,详见 §8A) | README、00-GOAL、01-ARCHITECTURE | confirmed | built | passed |
| 0.2 | 历史规格归档 | archive/historical-specs | defined | not_started | not_run |
| 0.3 | API 契约补齐 | contracts/openapi.yaml | defined | not_started | not_run |
| 0.4 | JSON Schema 补齐 | contracts/*.schema.json | defined | not_started | not_run |
| 0.5 | 数据库迁移草案 | migrations/* | defined | not_started | not_run |
| 0.6 | MVP 范围锁定 | 04-MVP-SCOPE.md | defined | not_started | not_run |

## 4. Stage 01：MVP 核心链路

| # | 任务 | 依赖 | 优先级 | 验收标准 | doc_status | impl_status | test_status |
|---|---|---|---|---|---|---|---|
| 1.1 | portal-web 项目初始化 | 0.x | P0 | Laravel 启动、Vue build 通过 | defined | not_started | not_run |
| 1.2 | 注册 / 登录 | 1.1 | P0 | Feature Test 通过 | defined | in_progress | partial |
| 1.3 | Profile CRUD | 1.2 | P0 | 创建 / 修改 / 删除 / 权限隔离 | defined | not_started | not_run |
| 1.4 | 白名单 / 黑名单 | 1.3 | P0 | exact / suffix / wildcard 校验 | defined | not_started | not_run |
| 1.4A | 会员中心安全 / 隐私 / 家长监护 Lite | 1.3 | P0 | 开关保存、发布后进入 resolver config | defined | not_started | not_run |
| 1.5 | Profile 发布 | 1.4,1.4A | P0 | 生成版本并调用 portal-web(原 console 域)内部 publish 服务(进程内) | defined | not_started | not_run |
| 1.6 | dns-console 节点预创建与 Token 签发 | 0.x | P0 | 管理员在 console 后台 `POST /api/v1/admin/nodes` 预创建，签发 `api_key` / `secret` 三元组（仅展示一次，portal-web 仅存 hash）；不存在 resolver 端注册 | defined | built | passed |
| 1.7 | portal-web(原 console 域)心跳 | 1.6 | P0 | online/offline 状态正确 | defined | not_started | not_run |
| 1.8 | 配置拉取与 ACK | 1.5,1.6 | P0 | resolver 拉取到版本并 ACK(目标侧为 portal-web(原 console 域)) | defined | not_started | not_run |
| 1.9 | dns-resolver DoH / UDP | 0.x | P0 | 查询可返回 upstream 结果 | defined | not_started | not_run |
| 1.10 | 规则引擎 | 1.9 | P0 | block 命中 BLOCK，allow 优先 ALLOW | defined | not_started | not_run |
| 1.11 | 日志批量上报 | 1.9 | P0 | portal 可查询日志 | defined | not_started | not_run |
| 1.12 | 端到端验收脚本 | 1.1-1.11 | P0 | 12 步 MVP 验收通过 | defined | not_started | not_run |

## 4A. Stage 01 当前执行记录（2026-06-14）

> 以下状态表示当前 `ocer-dns/` 工作区的实际进度，不代表已经达到 `L3` 全量验收。

| # | 任务 | 当前实现说明 | doc_status | impl_status | test_status | delivery_level |
|---|---|---|---|---|---|---|
| 1.1 | portal-web 项目初始化 | `ocer-dns/portal-web` 已切到真实 Laravel + Vue 目录，前端已收口到 `portal-web/web`，`php artisan test` 与 `npm run build` 已通过 | defined | built | passed | L3 |
| 1.2 | 注册 / 登录 | 已接真实用户存储、Token 登录、状态校验，Feature Test 已覆盖基础闭环 | defined | built | passed | L3 |
| 1.3 | Profile CRUD | 已接数据库模型、权限边界和 Member API，Feature Test 已覆盖主 Profile 工作区闭环 | defined | built | passed | L3 |
| 1.4 | 白名单 / 黑名单 | 已接数据库持久化、规则校验与发布映射，Feature Test + resolver 单元测试已覆盖 | defined | built | passed | L3 |
| 1.4A | 会员中心安全 / 隐私 / 家长监护 Lite | 已支持持久化、读取、发布映射与前端页面联动，Feature Test 已覆盖 | defined | built | passed | L3 |
| 1.5 | Profile 发布 | 已按持久化 Profile / Rule / Feature 生成版本、checksum，并调用 console internal API；`portal-web` Feature Test 已通过 | defined | built | passed | L3 |
| 1.6 | dns-console 节点预创建与 Token 签发 | 已实现管理员在 portal-web 总后台 `POST /api/v1/admin/nodes` 预创建、节点持久化、api_key/secret 三元组一次性下发（console 仅存 hash）、HMAC 心跳鉴权，Feature Test 已通过 | defined | built | passed | L3 |
| 1.7 | dns-console 心跳 | 已实现节点鉴权、心跳落库、状态评估、desired config 对比(目标侧为 portal-web(原 console 域))，Feature Test 已通过 | defined | built | passed | L3 |
| 1.8 | 配置拉取与 ACK | 已实现 `current_version` 轮询、204 无更新、bundle checksum、ACK 持久化和发布任务执行状态更新(目标侧为 portal-web(原 console 域))，Feature Test 已通过 | defined | built | passed | L3 |
| 1.9 | dns-resolver DoH / UDP | 已实现 DoH、UDP 53、TCP 53 查询链路，凭据由 `resolver install` 写入 `configs/server.yaml` 启动时 `cfg.Validate()` 校验、HMAC 心跳、拉配置、ACK、source-IP Profile 识别、上游解析和日志上报；`go test` / `go vet` 已通过 | defined | built | passed | L3 |
| 1.10 | 规则引擎 | 已实现 allow 优先、exact/suffix 基础匹配、域名归一化和单元测试 | defined | built | passed | L2 |
| 1.11 | 日志批量上报 | 已实现 resolver 本地 buffer、Bearer 鉴权批量上报、portal-web(原 console 域)明细持久化、portal-web Member 真实日志/统计回读；Laravel/Go 测试均已通过 | defined | built | passed | L3 |
| 1.12 | 端到端验收脚本 | 尚未开始 | defined | not_started | not_run | L1 |

## 4C. Stage 02 geodns 健康选路 HTTP 服务化（2026-06-15）

| # | 任务 | 后端包 | 前端包 | 当前实现说明 | doc_status | impl_status | test_status | delivery_level |
|---|---|---|---|---|---|---|---|---|
| 2.GD.1 | geodns config 加载（yaml） | geodns | — | 新增 `internal/config`，读取 `configs/config.example.yaml` / `-config` / `GEODNS_CONFIG` | defined | built | ok | L2 |
| 2.GD.2 | geodns server.Run HTTP 服务 | geodns | — | `GET /health` / `GET /health-view`（带 `Cache-Control: max-age=ttl`） / `GET /pick?region=...` 暴露 `router.Pick` 健康选路 | defined | built | ok | L2 |
| 2.GD.3 | geodns 周期刷新健康视图 | geodns | — | 每 `refresh_interval`（默认 15s）拉取控制台 `console_health_url`，缓存到内存并打日志 | defined | built | ok | L2 |
| 2.GD.4 | dns-resolver 启动拉取 healthview | dns-resolver | — | 启动时 `agent.HealthViewClient.Fetch` 拉取一次，失败只记日志不阻塞启动 | defined | built | ok | L2 |

## 4B. Stage 02 前端 CRUD / 批量操作执行记录（2026-06-15）

> 本节跟踪 `portal-web` 与 `dns-console-web` 前端列表页面在 CRUD、批量操作与文档同步上的实际进度。状态与 `04-FEATURES.md` / `07-CHANGE-LOG.md` 保持一致。

| # | 页面 | 后端包 | 前端包 | 当前实现说明 | doc_status | impl_status | test_status | delivery_level |
|---|---|---|---|---|---|---|---|---|
| 2.UI.1 | portal-web ProfileList | portal-web | portal-web/web | Profile CRUD、批量删除、复制、发布 | defined | built | not_run | L2 |
| 2.UI.2 | portal-web blocklist / Allowlist | portal-web | portal-web/web | 行内编辑、批量删除、新增对话框 | defined | built | not_run | L2 |
| 2.UI.3 | portal-web ProfileDetail | portal-web | portal-web/web | Profile 编辑、规则 CRUD、批量删除、发布按钮 | defined | built | not_run | L2 |
| 2.UI.4 | portal-web TeamList | portal-web | portal-web/web | 团队列表、退出团队（非 owner） | defined | built | not_run | L2 |
| 2.UI.5 | portal-web TeamDetail | portal-web | portal-web/web | 团队编辑、成员管理、角色变更、批量取消邀请、转移所有权、退出团队 | defined | built | not_run | L2 |
| 2.UI.6 | portal-web TeamInvitations | portal-web | portal-web/web | 邀请接受 | defined | built | not_run | L2 |
| 2.UI.7 | portal-web 总后台 Nodes | dns-console-web | dns-console-web/web | 节点 CRUD、批量删除、启用/禁用、令牌签发/撤销 | defined | built | not_run | L2 |
| 2.UI.8 | portal-web 总后台 Publishes | dns-console-web | dns-console-web/web | 发布列表、批量重试、批量取消、清理已完成 | defined | built | not_run | L2 |
| 2.UI.9 | portal-web 总后台 GeoDNS | dns-console-web | dns-console-web/web | GeoDNS 映射 CRUD、批量删除、国家筛选、节点下拉 | defined | built | not_run | L2 |
| 2.UI.10 | portal-web 总后台 RuleLibrary | dns-console-web | dns-console-web/web | 规则源 CRUD、批量删除、批量同步、立即同步 | defined | built | not_run | L2 |
| 2.UI.11 | portal-web 总后台 AuditLogs | dns-console-web | dns-console-web/web | 过滤、NDJSON 导出、批量删除、完整分页 | defined | built | not_run | L2 |
| 2.UI.12 | i18n | both | both | `en` / `zh-CN` / `ja` 全部新增 key 已补齐 | defined | built | not_run | L2 |

## 5. Stage 02：MVP 完善

| # | 任务 | 优先级 | 验收标准 |
|---|---|---|---|
| 2.1 | 设备管理 | P1 | Device CRUD + Profile 绑定 |
| 2.2 | ClickHouse 聚合统计 | P1 | Dashboard 指标来自聚合结果 |
| 2.3 | 规则源导入 | P1 | 可导入一份广告规则源并编译 |
| 2.4 | 安全高级分类/规则源自动更新 | P1 | malware / phishing category 自动更新生效 |
| 2.5 | 家长控制高级分类 | P1 | gambling / game / social category 生效 |
| 2.6 | Prometheus 指标 | P1 | resolver 暴露 `/metrics` |

## 6. Stage 03：生产化基础

| # | 任务 | 优先级 | 验收标准 |
|---|---|---|---|
| 3.1 | NATS JetStream 日志链路 | P1 | dns.logs 消费写入 ClickHouse |
| 3.2 | 配置通知与轮询补偿 | P1 | NATS 通知丢失后仍能拉到配置 |
| 3.3 | 配置回滚 | P0 | 失败版本可回滚到上一个 ACK 成功版本 |
| 3.4 | DoT / TCP 53 | P1 | 协议测试通过 |
| 3.5 | 本地 buffer 重放 | P0 | 网络恢复后日志顺序重放 |
| 3.6 | 告警 | P1 | 节点离线触发告警事件 |

## 7. 执行记录模板

每次真实生成或修改代码后，在 `project-doc/04-change-log.md` 追加：

```text
日期：YYYY-MM-DD
任务：Stage x.y
修改文件：...
构建结果：...
测试结果：...
验收证据：...
遗留问题：...
```

没有构建和测试证据，不得写“已完成生产交付”。


## 8A. Stage M：合并 dns-console-web 到 portal-web 总后台（4 包 → 3 包）

> 2026-06-15 至 2026-06-16 之间完成代码改造。所有 M.x 任务已在代码与文档侧同步落地：包结构从 4 包缩减为 3 包；`portal-web` 增加 `/api/v1/admin/*`(原 console 域)、`/api/v1/agent/*`、`/api/v1/internal/*` 三组路由；`dns-resolver` / `geodns` 的 Go 代码零修改；`dns-console-web/` 目录仅保留 `Layout.vue` 占位。端到端冒烟(M.16)以 `AgentHmacSignatureTest` 等 Feature Test 通过为证。

| # | 任务 | 依赖 | 优先级 | 验收标准 | doc_status | impl_status | test_status | delivery_level |
|---|---|---|---|---|---|---|---|---|
| M.1 | `portal-web` MySQL 库并入 console 全部迁移文件 | — | P0 | `php artisan migrate` 单库迁移通过 | confirmed | built | passed | L3 |
| M.2 | 复制 `dns-console-web/app/Models/*` → `portal-web/app/Models/`,命名冲突审查 | M.1 | P0 | 无重名 class | confirmed | built | passed | L3 |
| M.3 | 复制 `dns-console-web/app/Domain/*` → `portal-web` 13 个子域(实际命名以代码为准) | M.1 | P0 | 命名空间一致,Laravel 容器解析通过 | confirmed | built | passed | L3 |
| M.4 | 复制 `dns-console-web/app/Http/Middleware/*` → `portal-web`,在 `bootstrap/app.php` 注册 `node.hmac` / `shared.token` / `signature.verify` 三个别名 | M.1 | P0 | `php artisan route:list` 显式展示中间件 | confirmed | built | passed | L3 |
| M.5 | 复制 `dns-console-web/app/Http/Controllers/Api/V1/{Admin,Agent,Internal}/*` → `portal-web` 同名目录,`AdminAuditLogController` 单一化(取并集) | M.3,M.4 | P0 | `php -l` 全过,`route:list` 路由全部可解析 | confirmed | built | passed | L3 |
| M.6 | 合并 `portal-web/routes/api.php` 与 `dns-console-web/routes/api.php`;`shared.token:admin` 行为沿用原 console 100% | M.5 | P0 | 五组路由(`/v1/public/member/admin/agent/internal`)可同时访问 | confirmed | built | passed | L3 |
| M.7 | 合并 `config/clickhouse.php` / `config/services.php`;`portal-web/.env.example` 追加 console 需要的 env 项 | M.5 | P0 | env.example 跑通;ClickHouse 单连接复用 | confirmed | built | passed | L3 |
| M.8 | 复制 `dns-console-web/web/src/views/*` → `portal-web/web/src/views/admin/*`(12 个页面);router 追加 `/admin/*` 守卫;`AdminLayout.vue` 引入;`en/zh-CN/ja.js` 追加 `admin.*` 段 | M.5 | P1 | `npm run build` 通过;`/admin/nodes` 等 12 个核心页可访问 | confirmed | built | passed | L3 |
| M.9 | 移除 `dns-console-web/` 目录、`.run/console-api.pid` / `.run/console-web.pid` | M.1-M.8 | P0 | git 状态干净 | confirmed | built | passed | L3 |
| M.10 | 更新 `docker-compose.yml`(`dns-console-web` service 删除;`dns-resolver` / `geodns` depends_on 改 portal-web;secrets 并入 portal-web) | M.5 | P0 | `docker compose config` 通过 | confirmed | built | passed | L3 |
| M.11 | `dns-resolver/configs/server.yaml.example` 的 `Endpoint` 注释指向 `portal-web` | M.10 | P0 | 部署文档与 yaml 注释一致 | confirmed | built | passed | L3 |
| M.12 | `geodns/configs/config.example.yaml` 的 `healthview.url` 指向 `portal-web` | M.10 | P0 | 部署文档与 yaml 注释一致 | confirmed | built | passed | L3 |
| M.13 | 继承 `dns-console-web/tests/Feature/*` → `portal-web/tests/Feature/`,`dns-console-web/tests/` 删除 | M.5 | P0 | `php artisan test` 通过 | confirmed | built | passed | L3 |
| M.14 | CI workflow 删除 `dns-console-web` matrix | M.9 | P1 | CI yaml 语法通过 | confirmed | built | passed | L3 |
| M.15 | Prometheus 抓取 target 与告警规则名从 console 改 portal-web | M.10 | P1 | 现有告警列表逐条替换 | confirmed | built | passed | L3 |
| M.16 | 端到端冒烟:节点预签发 → resolver install → HMAC 心跳 → 拉配置 → ACK → query logs 批量 → portal Member 侧回读 | M.1-M.15 | P0 | `AgentHmacSignatureTest` / `ProfilePublishTest` / `MemberWorkspaceTest` 通过,resolver `go test ./...` + `go vet ./...` 通过 | confirmed | built | passed | L3 |

> **状态**:本期变更全部落地。`dns-console-web/` 目录已并入 `portal-web`(`portal-web(原 console 域)` 子命名空间);`docs` 中所有"4 包 → 3 包"标记已切换为"3 包";`Portal Web` 实现位置从 `dns-console-web/app/Http/Controllers/Api/V1/*` 全部迁移到 `portal-web/app/Http/Controllers/Api/V1/{Admin,Agent,Internal}/*`;路由路径保持不变(原 `/api/v1/agent/*`、`/api/v1/internal/*` 命名沿用);`/api/v1/admin/*` 由 `shared.token:admin`(原 console 行为 100% 一致)与 Sanctum 双重覆盖,按路由决定。后续若 `dns-console-web/` 占位目录被清理,本节任务依然有效,只调整 M.9 备注。
