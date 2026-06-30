# 变更日志（Bug / Feature Log）

> 记录每次功能增减、Bug 修复、文档变更。没有构建、测试、部署证据时，状态只能写"文档已定义"或"代码草案"。

## 2026-06-30 — 前端 UI i18n 完善（frontend-ui.md P1 修复）

| 日期 | 类型 | 描述 | 涉及文件 | 状态 |
|---|---|---|---|---|
| 2026-06-30 | code | P1-5: GeoDNS.vue 状态 tag（已安装/待安装/从未心跳）走 i18n | portal-web/web/src/views/admin/GeoDNS.vue | ok |
| 2026-06-30 | code | P1-5: 新增 admin.geoDns.installed/pending/neverHeartbeat i18n key（zh-CN/en/ko） | portal-web/web/src/locales/{zh-CN,en,ko}.json | ok |
| 2026-06-30 | code | P1-6: BasicConfig.vue DNS 域名 label 走 i18n | portal-web/web/src/views/admin/BasicConfig.vue | ok |
| 2026-06-30 | code | P1-6: 新增 admin.basicConfig.dnsDomain/dnsDomainDesc i18n key（zh-CN/en/ko） | portal-web/web/src/locales/{zh-CN,en,ko}.json | ok |
| 2026-06-30 | code | P1-7: Layout.vue 抹除 Profile name fallback 'Default' | portal-web/web/src/components/Layout.vue | ok |
| 2026-06-30 | code | P1-7: Home.vue 抹除 logoutSuccess fallback 'Logged out' | portal-web/web/src/views/Home.vue | ok |
| 2026-06-30 | code | P1-8: Login.vue eyebrow 硬编码改为 :eyebrow="$t('auth.eyebrowMember')" | portal-web/web/src/views/Login.vue | ok |
| 2026-06-30 | code | P1-8: Login.vue highlights 硬编码改为 t() i18n 调用 | portal-web/web/src/views/Login.vue | ok |
| 2026-06-30 | code | P1-8: 新增 auth.eyebrowMember/eyebrowAdmin/highlightDoh/highlightAvailability/highlightAudit i18n key（zh-CN/en/ko） | portal-web/web/src/locales/{zh-CN,en,ko}.json | ok |
| 2026-06-30 | code | P1-8: Dashboard.vue dimensionStats 中文 label 和 fallback 移除，改用 t() | portal-web/web/src/views/admin/Dashboard.vue | ok |
| 2026-06-30 | code | P1-8: 新增 admin.dashboard.dimensionGafam/RootDomain/EncryptedDns/DnssecValid i18n key（zh-CN/en/ko） | portal-web/web/src/locales/{zh-CN,en,ko}.json | ok |

## 2026-06-30 — Resolver 2 日志缓冲 read-only 修复 + 401 鉴权修复

| 日期 | 类型 | 描述 | 涉及文件 | 状态 |
|---|---|---|---|---|
| 2026-06-30 | code | install.go: systemd unit 模板 ReadWritePaths 追加 `/var/lib/ocer-dns/log-buffer`，修复 ProtectSystem=strict 沙箱下日志缓冲无法落盘 Bug | dns-resolver/cmd/dns-resolver/install.go | ok |
| 2026-06-30 | code | 现场运维: 103.86.44.209 systemd unit daemon-reload + restart，验证 read-only 消失，日志正常落盘 | 服务器 /etc/systemd/system/dns-resolver.service | ok |
| 2026-06-30 | ops | 现场运维: Resolver 2 (103.86.44.209) portal-web DB resolver_nodes.api_key 为 NULL → 导致所有 API 401；根因：node_code=ipsckxkyoo DB api_key 为空，读取 Resolver 侧 api_key 文件 hash 写入 DB 后 401 消失，所有接口恢复 200 | portal-web DB resolver_nodes(id=3) | ok |

## 2026-06-26 — 财务栏目链路完善 + Model 表名前缀修复

| 日期 | 类型 | 描述 | 涉及文件 | 状态 |
|---|---|---|---|---|
| 2026-06-26 | code | AdminFinanceController: orders()+orderDetail() 查询 dns_orders 表 | portal-web/app/Http/Controllers/Api/V1/Admin/AdminFinanceController.php | ok |
| 2026-06-26 | code | AdminFinanceController: subscriptions()+subscriptionDetail() 查询 dns_subscriptions 表 | portal-web/app/Http/Controllers/Api/V1/Admin/AdminFinanceController.php | ok |
| 2026-06-26 | code | admin.php: 新增 finance/orders, finance/orders/{id}, finance/subscriptions, finance/subscriptions/{id} 路由 | portal-web/routes/v1/admin.php | ok |
| 2026-06-26 | code | 新建 Order.vue 订单列表页面（分页/筛选/导出/详情） | portal-web/web/src/views/admin/Order.vue | ok |
| 2026-06-26 | code | 新建 Subscriptions.vue 订阅管理页面（分页/状态/配额/自动续费筛选） | portal-web/web/src/views/admin/Subscriptions.vue | ok |
| 2026-06-26 | code | router/index.js: 导入 AdminOrder/AdminSubscriptions + 新增 /admin/order, /admin/subscriptions 路由 | portal-web/web/src/router/index.js | ok |
| 2026-06-26 | code | i18n: 订单/订阅相关 36 个 key（zh-CN/en/ko） | portal-web/web/src/locales/{zh-CN,en,ko}.json | ok |
| 2026-06-26 | code | billing.desc 修正：套餐用量→交易流水描述（Billing.vue 实际功能） | portal-web/web/src/locales/{zh-CN,en,ko}.json | ok |
| 2026-06-26 | code | dns_admin_menu_rule: 重构菜单结构 — 统一 label 命名、调整 sort_order、配置文件发布归入系统设置、新增 wallet-flows/subscriptions 菜单项 | portal-web/database (runtime) | ok |
| 2026-06-26 | code | admin.php: 新增 finance/wallet-flows 和 /wallet-flows/export 路由 | portal-web/routes/v1/admin.php | ok |
| 2026-06-26 | code | router/index.js: 退款路由改回 refund-records（路径不变） | portal-web/web/src/router/index.js | ok |
| 2026-06-26 | code | 新建 WalletFlows.vue 钱包流水页面 | portal-web/web/src/views/admin/WalletFlows.vue | ok |
| 2026-06-26 | code | 新建 BaseModel.php，Eloquent Model 通过 DB::getTablePrefix() 动态获取表名前缀 | portal-web/app/Models/BaseModel.php | ok |
| 2026-06-26 | code | Wallet/Order/Plan/PlanPrice/PlanFeature/BillingPeriod/BillingItem 改为继承 BaseModel | portal-web/app/Models/Wallet.php 等 7 个文件 | ok |
| 2026-06-26 | code | AdminMemberCatalogController::rules(): list_type 值映射 block→blocklist/allow→allowlist | portal-web/app/Http/Controllers/Api/V1/Admin/AdminMemberCatalogController.php | ok |
| 2026-06-26 | code | ProfileRule/Profile 改为继承 BaseModel，修复黑名单查询 dns_profile_rules 表名 | portal-web/app/Models/ProfileRule.php, Profile.php | ok |
| 2026-06-26 | code | AdminPolicyController::indexPlans(): 去除嵌套 users 全量加载，改为 COUNT 统计 user_count | portal-web/app/Http/Controllers/Api/V1/Admin/AdminPolicyController.php | ok |
| 2026-06-26 | code | UserPolicyServices.vue: 去掉表格嵌套（type=expand），改为独立「查看用户」按钮 | portal-web/web/src/views/admin/UserPolicyServices.vue | ok |
| 2026-06-26 | docs | 同步本变更日志 | project-doc/07-CHANGE-LOG.md | ok |

## 2026-06-26 — 前台账户页面优化 + i18n 修复

| 日期 | 类型 | 描述 | 涉及文件 | 状态 |
|---|---|---|---|---|
| 2026-06-26 | code | Account.vue: 删除订阅面板、支付弹窗、邮件面板，移除自动弹窗逻辑 | portal-web/web/src/views/user/Account.vue | ok |
| 2026-06-26 | code | Account.vue: 清理无用的 JS 代码和 CSS 样式 | portal-web/web/src/views/user/Account.vue | ok |
| 2026-06-26 | code | 修复 allowlist.matchSubdomainHint 翻译 key 缺失 | portal-web/web/src/locales/{en,zh-CN,ko}.json | ok |
| 2026-06-26 | code | MemberCatalogs rulesTitle: "全站黑白名单记录"→"配置列表" | portal-web/web/src/locales/{en,zh-CN,ko}.json | ok |
| 2026-06-26 | docs | 同步本变更日志 | project-doc/07-CHANGE-LOG.md | ok |

## 2026-06-25 — 端口配置修正

| 日期 | 类型 | 描述 | 涉及文件 | 状态 |
|---|---|---|---|---|
| 2026-06-25 | code | Nodes.vue: DoQ 端口 784→853，DoH 保持 443 | portal-web/web/src/views/admin/Nodes.vue | ok |
| 2026-06-25 | code | GeoDNS.vue: 改为正确描述（HTTP API 15354 / 权威 DNS 53） | portal-web/web/src/views/admin/GeoDNS.vue | ok |
| 2026-06-25 | docs | START.md: DoH 端口 8443→443，GeoDNS 端口 5354→15354 | ai-doc/START.md | ok |
| 2026-06-25 | code | config.go: DoH 默认端口 8443→443 | dns-resolver/internal/config/config.go | ok |
| 2026-06-25 | code | config.example.yaml: DoH 端口 8443→443 | dns-resolver/configs/config.example.yaml | ok |

## 2026-06-24 — UI.md 问题修复

| 日期 | 类型 | 描述 | 涉及文件 | 状态 |
|---|---|---|---|---|
| 2026-06-24 | code | 修复顶部导航"安全防护"下拉图标重叠：增加 padding (12→14px) 和 white-space: nowrap | portal-web/web/src/components/Layout.vue | ok |
| 2026-06-24 | code | 美化 Allowlist 页面操作按钮排版：添加 .action-buttons 样式增加按钮间距 | portal-web/web/src/views/Allowlist.vue | ok |
| 2026-06-24 | code | 删除 ProfileList 复制按钮及 handleCopy 函数 | portal-web/web/src/views/ProfileList.vue | ok |
| 2026-06-24 | docs | Settings 页面已清理（仅保留修改密码）；Analytics 数据调用正常；AuditLogs 多语言已有 actionsMap | portal-web/web/src/views/Settings.vue 等 | ok |
| 2026-06-24 | docs | 同步本变更日志 | project-doc/07-CHANGE-LOG.md | ok |

## 2026-06-22 — 前端验证（lint / build / i18n）

| 日期 | 类型 | 描述 | 涉及文件 | 状态 |
|---|---|---|---|---|
| 2026-06-22 | code | 修复 GeoDNS.vue 缺少 3 个 icon 组件导入（`<Search>` / `<Plus>` / `<Aim>`） | portal-web/web/src/views/admin/GeoDNS.vue | ok |
| 2026-06-22 | code | 三语补齐 `blocklist.matchSubdomainHint`（blocklist.vue 引用） | portal-web/web/src/locales/{en,zh-CN,ko}.json | ok |
| 2026-06-22 | code | 三语补齐 `admin.nodes.redeploy`（Nodes.vue 引用） | portal-web/web/src/locales/{en,zh-CN,ko}.json | ok |
| 2026-06-22 | code | 修复 pre-existing Billing.vue `<style>` 块未闭合（缺 `}` 与 `</style>`），恢复 build 通过 | portal-web/web/src/views/admin/Billing.vue | ok |
| 2026-06-22 | test | `npm run lint`：0 errors / 43 warnings（全部 pre-existing） | — | ok |
| 2026-06-22 | test | `npm run build`：✓ 1776 modules transformed / built in 3.69s | portal-web/public/dist/ | ok |
| 2026-06-22 | test | 三语 i18n 一致性：en=1594 / zh=1594 / ko=1597，缺失 key=0 | portal-web/web/src/locales/*.json | ok |
| 2026-06-22 | docs | 同步本变更日志 | project-doc/07-CHANGE-LOG.md | ok |

## 2026-06-22 — 修复 tests/Feature/ApiTest.php 诊断（25 → 0）

| 日期 | 类型 | 描述 | 涉及文件 | 状态 |
|---|---|---|---|---|
| 2026-06-22 | code | 删除 4 个未使用 import（`AuditLog` / `Role` / `Permission` / `Alert`） | portal-web/tests/Feature/ApiTest.php | ok |
| 2026-06-22 | code | 15 个属性加 `?string` / `?int` 类型提示（消除 intelephense Info） | portal-web/tests/Feature/ApiTest.php | ok |
| 2026-06-22 | code | 4 个辅助方法（`callMemberApi` / `callAdminApi` / `callInternalApi` / `callApiWithToken`）参数加 `string` / `?string` / `array` / `int` 类型 | portal-web/tests/Feature/ApiTest.php | ok |
| 2026-06-22 | code | 删除未使用的 `$invitation` 局部变量（原 line 780），保留 `TeamInvitation::create()` 调用 | portal-web/tests/Feature/ApiTest.php | ok |
| 2026-06-22 | docs | 同步本变更日志 | project-doc/07-CHANGE-LOG.md | ok |

## 2026-06-22 — 导航菜单收敛（方案策略 / 方案列表）

| 日期 | 类型 | 描述 | 涉及文件 | 状态 |
|---|---|---|---|---|
| 2026-06-22 | code | 收敛后台导航：`/admin/member-catalogs` 标题改为「方案策略」；新增 `/admin/user-policy-services` 标题「方案列表」；删除独立的「会员策略」(`member-policies`) 与「黑白名单」(`blacklist-whitelist`) 导航 | portal-web/dns_admin_menu_rule 表 | ok |
| 2026-06-22 | docs | 同步本变更日志 | project-doc/07-CHANGE-LOG.md | ok |

## 2026-06-22 — 多语言与闪烁问题修复

| 日期 | 类型 | 描述 | 涉及文件 | 状态 |
|---|---|---|---|---|
| 2026-06-22 | code | 修复 `/user/{profile}/privacy` 默认拦截列表闪烁：删除 `form.blocklists` 硬编码默认值，新增 `blocklistLoaded` 守卫；切换 profile 时重置 loaded 状态 | portal-web/web/src/views/Privacy.vue | ok |
| 2026-06-22 | code | 补齐 `common.loading` 三语 key（zh-CN/en/ko） | portal-web/web/src/locales/{zh-CN,en,ko}.json | ok |
| 2026-06-22 | code | 补齐 `admin.auditLogs.batchDeleteConfirm / batchDeleted / batchDeleteFailed` ko 翻译 | portal-web/web/src/locales/ko.json | ok |
| 2026-06-22 | code | 补齐三语 locale 缺失的 100+ key（含 admin.usersPage / admin.systemConfig / admin.alertsPage / membership.plans / nav / privacy.blocklists / devices / logs / apiKeys / team / billing / settings / security / parental / common 等段） | portal-web/web/src/locales/{zh-CN,en,ko}.json | ok |
| 2026-06-22 | code | i18n 配置加固：`fallbackLocale: ['en','zh-CN','ko']` 链式回退；开启 `silentTranslationWarn` / `silentFallbackWarn`；missing 回调降为 warn | portal-web/web/src/locales/index.js | ok |
| 2026-06-22 | docs | 同步本变更日志 | project-doc/07-CHANGE-LOG.md | ok |

## 2026-06-17 — 修复 AdminLayout.vue 语言选择器硬编码

| 日期 | 类型 | 描述 | 涉及文件 | 状态 |
|---|---|---|---|---|
| 2026-06-17 | code | 修复 AdminLayout.vue 中 currentLocale 计算属性硬编码 '中文' 和 '한국어'，改用 i18n.global.t('settings.lang.*') 实现国际化 | portal-web/web/src/components/AdminLayout.vue | ok |
| 2026-06-17 | code | 添加 i18n 导入以支持在 script 中使用全局翻译函数 | portal-web/web/src/components/AdminLayout.vue | ok |

## 2026-06-17 — 修复 admin/query-logs 页面多语言实现

| 日期 | 类型 | 描述 | 涉及文件 | 状态 |
|---|---|---|---|---|
| 2026-06-17 | code | 修复 QueryLogs.vue 组件 i18n key 使用错误，将 `admin.queryLogs.xxx` 改为 `admin.queryLogsPage.xxx` | portal-web/web/src/views/admin/QueryLogs.vue | ok |
| 2026-06-17 | code | 补充 en.js 中 `admin.queryLogsPage` 子键（title/desc/searchDomain/action 等 12 个 key） | portal-web/web/src/locales/en.js | ok |



> 本次为**文档计划阶段**,代码改造待用户审批 `01-ARCHITECTURE.md` / `02-MODULES.md` / `03-DATA-FLOW.md` / `04-FEATURES.md` / `05-PLANS.md` / `06-MVP-SCOPE.md` 的合并方案后再启动。`START.md` / `README.md` / `00-GOAL.md` 当前仍按四包记录,待审批通过后统一切换为三包表述。

### 计划背景

- 用户决定将"独立的节点控制台"功能合并到 `portal-web` 的「总后台」中,删除独立 `dns-console-web` 端,项目从 4 端改成 3 端(`portal-web` / `dns-resolver` / `geodns`)。
- 用户指明:文档计划载体为 `ai-doc-v1/START.md` 启动链中涉及到的文件(`project-doc/*`、`specs/*`、`contracts/*`、`migrations/*`、`deploy/*`),不得使用 `.trae/documents/` 等旁路目录。

### 计划范围(代码改造阶段执行,本期仅同步文档)

| # | 范围 | 文档落点 |
|---|---|---|
| D1 | 项目从 4 包缩减为 3 包;`portal-web` 内新增"原 console 域"子命名空间 | `01-ARCHITECTURE.md` §3, §13;`02-MODULES.md` §1, §2(整段替换) |
| D2 | DNS 节点控制台功能(节点管理 / 心跳 / 配置版本 / 发布 / 查询日志接入 / 健康视图 / 规则库 / 系统配置 / 节点侧审计 / GeoDNS 映射)并入 `portal-web` 总后台;`portal-web` 后台新增对应 Admin SPA | `04-FEATURES.md` §1, §4;`05-PLANS.md` §8A 新增 Stage M |
| D3 | `dns-resolver` / `geodns` 与控制面交互从 `dns-console-web` 改指向 `portal-web`;`dns-resolver` / `geodns` 的 Go 代码**零修改**,只改部署配置 | `03-DATA-FLOW.md` §3-§6;`specs/dns-resolver/protocol.md` §1 Endpoint 注释 |
| D4 | 数据库合并:`nodes` / `node_tokens` / `node_heartbeats` / `config_versions` / `publish_tasks` / `task_executions` / `query_log_ingest_batches` / `geo_dns_mappings` / `rule_sources` / `system_config` / `admin_audit_logs` 全部并入 `portal-web` 现有 PostgreSQL `ocer_dns` 库 | `02-MODULES.md` §1.4 数据所有权矩阵;`migrations/postgresql/002_dns_console_web_mvp.sql` 迁移并入 `001_portal_web_mvp.sql` 同库 |
| D5 | 路由:portal-web 的 `routes/api.php` 合并三套(`/api/v1/admin/*` 全部 + `/api/v1/agent/*` + `/api/v1/internal/*`);沿用原 console 路径不变 | `specs/portal-web/api.md` 追加 §A, §B, §C;`specs/dns-console-web/api.md` 标注"已并入 portal-web 总后台" |
| D6 | `audit_logs`(portal 写用户/计费审计)与 `admin_audit_logs`(原 console 写节点/发布审计)**仍为两张独立表**,不合并字段 | `02-MODULES.md` §1.4;`migrations/postgresql/001_portal_web_mvp.sql` |
| D7 | 节点凭据由 `portal-web` 总后台的 `Admin/Node` 预签发,响应一次性返回,`portal-web` 仅存 hash;`/api/v1/admin/nodes` 鉴权沿用原 console shared token(`shared.token:admin`),**不**改用 Sanctum,行为 100% 一致 | `01-ARCHITECTURE.md` §6.2;`03-DATA-FLOW.md` §3 节点凭据来源段 |
| D8 | `dns-resolver` / `geodns` 部署时:`OCER_RESOLVER_CONFIG.Endpoint` 与 `GEODNS_HEALTHVIEW_URL` 改指向 `portal-web`;`dns-resolver` 的 `depends_on` 改 portal-web | `deploy/docker-compose.yml`;`deploy/local-dev.md` |
| D9 | 删除 `dns-console-web/` 目录、`.run/console-api.pid` / `.run/console-web.pid`、CI 中 `dns-console-web` matrix、`ops/prometheus/*` 中 console 抓取/告警 | `DEPLOYMENT.md`;`ops/DR-RUNBOOK.md` |
| D10 | 验证用例继承:`ConsoleAgentFlowTest` / `AgentHmacSignatureTest` / `HealthCheckServiceTest` 整体迁移到 `portal-web/tests/Feature/`,原 `dns-console-web/tests/` 删除 | `08-DELIVERY-CRITERIA.md` §验收证据 |

### 设计原则(强约束,违反即不通过)

1. 不改 `dns-resolver` / `geodns` 的 Go 代码,只改它们的部署配置。
2. 不破坏现有 `portal-web` 公共 API:`/api/v1/public/*`、`/api/v1/member/*` 行为完全不变。
3. 不破坏 resolver 端凭据直驱:api_key/secret/canonical 签名校验逻辑整段迁移到 portal-web,行为不变。
4. 不引入降级/兜底:任何"如果新路径走不通就回退到旧流程"的代码禁止出现。
5. 数据所有权仍分两张审计表:`audit_logs` 与 `admin_audit_logs` 不合并。
6. 不删除任何已有的 timeout / retry / 资源释放 / SSL 校验逻辑,整段搬迁。
7. 节点凭据:`api_key` / `secret` / `node_id` 任何字段缺失 → resolver 端 `cfg.Validate()` 拒绝启动(行为不变)。

### 验收证据要求(代码改造完成后)

- `php artisan test`(portal-web 继承 console 测试用例)
- `go test ./...` + `go vet ./...`(`dns-resolver` / `geodns`,行为不变)
- `docker compose config`
- `database migration dry-run`(`portal-web` 单库迁移通过)
- `npm run build`(`portal-web` 现有 SPA + 新增 admin 视图)
- 端到端冒烟脚本(从 `ConsoleAgentFlowTest` 演化):节点预签发 → resolver install → HMAC 心跳 → 拉配置 → ACK → query logs 批量 → portal 侧回读日志

### 风险与回退

- 路由冲突:实施前用 `php artisan route:list` 双包扫描;冲突时以原 console 路径为准(与生产对外契约保持一致)。
- 节点凭据改由 `portal-web` 总后台签发,`dns-resolver` / `geodns` 的部署 `server.yaml` / `config.yaml` 需同步切换指向,**不允许**双写兜底。
- Prometheus 告警规则命名变更须在变更前导出当前告警列表,逐条替换。
- 回退:本期全部变更提交在一个 feature 分支,异常时 `git revert` 一次性回退。

### 涉及文件清单(本期文档同步)

| 文件 | 变更 |
|---|---|
| `START.md` | 启动链描述保留"四包"现状;新增"待审批合并计划"指针 → `07-CHANGE-LOG.md` 本节 |
| `README.md` | 当前目标架构表先保留四包,新增"合并待审批"指针 |
| `project-doc/00-GOAL.md` | §2 边界表先保留四包,新增"合并待审批"指针 |
| `project-doc/01-ARCHITECTURE.md` | §3 四个包职责表新增"合并后(3 包)职责表"作为附表;§6.2 节点生命周期流程图更新;§12 Internal Service API 表来源侧统一为 portal-web;§13 边界表更新 |
| `project-doc/02-MODULES.md` | §1 portal-web 职责追加原 console 域功能清单;§2 dns-console-web 章节**整段替换**为"已并入 portal-web";§1.4 数据所有权矩阵把"主写入方=dns-console-web"的项改为"主写入方=portal-web(原 console 域)";§6 边界检查清单对应更新 |
| `project-doc/03-DATA-FLOW.md` | §1 / §3 / §4 / §5 / §6 中"dns-console-web"替换为"portal-web(原 console 域)";保留路由路径 `/api/v1/agent/*` / `/api/v1/internal/*` 不变 |
| `project-doc/04-FEATURES.md` | §1 MVP 功能表中 `dns-console-web` 行重命名为"portal-web 总后台(原 console 域)",保留所有功能项;§4 管理后台功能表中"节点管理/发布任务/健康视图"三行 `dns-console-web` 改为 `portal-web` |
| `project-doc/05-PLANS.md` | §8A 新增 Stage M(合并执行) |
| `project-doc/06-MVP-SCOPE.md` | §1.2 dns-console-web 段**整段替换**为"§1.2 portal-web 总后台(原 console 域)",功能清单逐项并入 |
| `project-doc/08-DELIVERY-CRITERIA.md` | 验收证据章节追加"合并后端到端证据"小节 |
| `specs/dns-console-web/api.md` | 顶部加 "**已并入 portal-web 总后台**,当前文件保留作为行为契约的历史记录;实际实现以 `specs/portal-web/api.md` 附录 A/B/C 为准" |
| `specs/dns-console-web/data-model.md` | 顶部加同样提示;表结构保留作为迁移基线 |
| `specs/portal-web/api.md` | 追加 §A Agent API、§B Internal API、§C Admin(节点/发布/规则库/系统配置/审计) |
| `migrations/postgresql/002_dns_console_web_mvp.sql` | 顶部加 "**并入 001_portal_web_mvp.sql 同库**";`003_billing_finance.sql` 不变 |
| `deploy/docker-compose.yml` | 移除 `dns-console-web` service;`dns-resolver` / `geodns` 改依赖 portal-web |
| `deploy/local-dev.md` | 更新启动顺序与端口 |
| `ocer-dns/README.md` | 顶部描述切换为 3 包;`ocer-dns/dns-console-web/` 目录在代码改造阶段删除 |
| `ocer-dns/DEPLOYMENT.md` | 移除 `dns-console-web` 一行;Runtime dependencies 表删除 `dns-console-web` |
| `ocer-dns/shared/docs/ARCHITECTURE_NOTES.md` | Package mapping 改为 3 包 |
| `ocer-dns/dns-console-web/README.md` | 顶部加 "**本包已并入 portal-web 总后台**,本文件仅作历史记录" |

### 文档计划阶段交付(本次提交)

- 本节(`07-CHANGE-LOG.md` 2026-06-15 合并条目)
- `01-ARCHITECTURE.md` 更新(§3 / §6.2 / §12 / §13)
- `02-MODULES.md` 更新(§1 / §2 整段 / §1.4 / §6)
- `03-DATA-FLOW.md` 更新(§1 / §3-§6)
- `04-FEATURES.md` 更新(§1 / §4)
- `05-PLANS.md` 新增 §8A Stage M
- `06-MVP-SCOPE.md` 更新(§1.2 整段)
- `08-DELIVERY-CRITERIA.md` 新增小节
- `specs/portal-web/api.md` 新增附录 A/B/C
- `specs/dns-console-web/api.md` / `specs/dns-console-web/data-model.md` 顶部加迁移提示
- `migrations/postgresql/002_dns_console_web_mvp.sql` 顶部加合并提示
- `START.md` / `README.md` / `00-GOAL.md` 暂保留"四包 + 合并待审批"双描述

### 状态

| 字段 | 值 |
|---|---|
| `doc_status` | defined(等用户审批) |
| `impl_status` | not_started |
| `test_status` | not_run |
| `delivery_level` | L1(文档计划) |

## 2026-06-15 — geodns HTTP 健康视图服务 + dns-resolver 启动拉取

| 类型 | 描述 | 涉及文件 | 状态 |
|---|---|---|---|
| code | `geodns` 启动时读取 `configs/config.example.yaml`（或 `-config`/`GEODNS_CONFIG` 指定），基于 `console_health_url` 周期拉取健康视图 | ocer-dns/geodns/cmd/geodns/main.go, ocer-dns/geodns/internal/config/config.go, ocer-dns/geodns/go.mod | 代码草案 |
| code | `geodns server.Run` 真实启动 HTTP 服务：`GET /health`、`GET /health-view`（带 `Cache-Control: max-age=ttl`）、`GET /pick?region=...` 暴露基于 `router.Pick` 的健康选路 | ocer-dns/geodns/internal/server/server.go | 代码草案 |
| code | `dns-resolver` 启动时使用 `agent.HealthViewClient` 拉取一次 `http://<geodns>/health-view`，拉取失败只记日志不中断启动 | ocer-dns/dns-resolver/cmd/dns-resolver/main.go, ocer-dns/dns-resolver/internal/agent/healthview.go, ocer-dns/dns-resolver/internal/config/config.go, ocer-dns/dns-resolver/configs/server.yaml | 代码草案 |
| docs | `04-FEATURES.md` / `07-CHANGE-LOG.md` 同步反映 geodns HTTP 服务与健康选路落地 | project-doc/04-FEATURES.md, project-doc/07-CHANGE-LOG.md | 已同步 |
| test | `geodns` `go test ./...` 与 `dns-resolver` `go test ./...` 通过 | — | ok |

## 2026-06-15 — Stage 02 前端 CRUD 与批量操作补齐

| 类型 | 描述 | 涉及文件 | 状态 |
|---|---|---|---|
| code | `portal-web` 团队详情页增加成员角色变更（owner→admin/member）、批量取消邀请、退出团队、转移所有权对话框 | ocer-dns/portal-web/web/src/views/TeamDetail.vue, ocer-dns/portal-web/web/src/locales/{en,zh-CN,ja}.js | 代码草案 |
| code | `portal-web` 团队列表页增加退出团队按钮（非 owner 角色可见） | ocer-dns/portal-web/web/src/views/TeamList.vue | 代码草案 |
| code | `portal-web` 后端 TeamService 增加 `updateMemberRole`、`batchCancelInvitations`、`leaveTeam`、`transferOwnership` 四个服务方法 | ocer-dns/portal-web/app/Domain/Team/TeamService.php | 代码草案 |
| code | `portal-web` 后端 TeamController 增加 `updateMemberRole`、`leaveTeam`、`transferOwnership`、`batchCancelInvitations` 四个端点，并注册到 `routes/api.php` | ocer-dns/portal-web/app/Http/Controllers/Api/V1/Member/TeamController.php, ocer-dns/portal-web/routes/api.php | 代码草案 |
| code | `dns-console-web` Nodes 页增加批量删除、节点编辑、令牌签发对话框（前端 UI 在前序变更已完成） | ocer-dns/dns-console-web/web/src/views/Nodes.vue | 代码草案 |
| code | `dns-console-web` Publishes 页增加批量重试、批量取消、清理已完成任务 | ocer-dns/dns-console-web/web/src/views/Publishes.vue | 代码草案 |
| code | `dns-console-web` AuditLogs 页增加导出 NDJSON、批量删除、actor 过滤、完整分页器 | ocer-dns/dns-console-web/web/src/views/AuditLogs.vue | 代码草案 |
| code | `dns-console-web` RuleLibrary 页由只读卡片改为完整列表 + 新增/编辑/删除/批量删除/批量同步对话框 | ocer-dns/dns-console-web/web/src/views/RuleLibrary.vue | 代码草案 |
| code | `dns-console-web` GeoDNS 页增加新增/编辑/删除/批量删除对话框 + 国家筛选 + 节点下拉 | ocer-dns/dns-console-web/web/src/views/GeoDNS.vue | 代码草案 |
| i18n | `dns-console-web` 三语 locale 补齐 Nodes / GeoDNS / RuleLibrary / AuditLogs / Publishes / Common 段所有新增 key | ocer-dns/dns-console-web/web/src/locales/{en,zh-CN,ja}.js | 已同步 |
| i18n | `portal-web` 三语 locale 补齐 TeamDetail / TeamList 段所有新增 key（含 changeRole / transfer / batchCancel / leaveTeam 等） | ocer-dns/portal-web/web/src/locales/{en,zh-CN,ja}.js | 已同步 |
| docs | 同步 `04-FEATURES.md`、`07-CHANGE-LOG.md` 反映前端 CRUD 与批量操作落地情况 | project-doc/04-FEATURES.md, project-doc/07-CHANGE-LOG.md | 已同步 |

## 2026-06-12 — 文档工程化改进

| 类型 | 描述 | 涉及文件 | 状态 |
|---|---|---|---|
| docs | 收敛四包目标架构：`portal-web`、`dns-console-web`、`dns-resolver`、`geodns` | README.md, START.md, project-doc/00-GOAL.md, project-doc/01-ARCHITECTURE.md | 文档已定义 |
| docs | 将历史命名 `admin-web`、`dns-control-web`、`control-plane` 移入归档，避免生成时误用 | archive/historical-specs/* | 文档已归档 |
| docs | 修正 GeoDNS 定位：只做入口调度，不作为每次 DNS 查询代理 | project-doc/01-ARCHITECTURE.md, project-doc/03-DATA-FLOW.md | 文档已定义 |
| docs | 明确心跳与查询日志上报区别 | START.md, project-doc/03-DATA-FLOW.md, specs/dns-console-web/api.md | 文档已定义 |
| docs | 补齐 portal-web API 与 PostgreSQL 数据模型 | specs/portal-web/api.md, specs/portal-web/data-schema.md | 文档已定义 |
| docs | 补齐 dns-console-web Agent / Internal / Console API 与数据模型 | specs/dns-console-web/api.md, specs/dns-console-web/data-model.md | 文档已定义 |
| docs | 补齐 dns-resolver 运行时模型、协议细节、配置结构 | specs/dns-resolver/data-model.md, specs/dns-resolver/protocol.md | 文档已定义 |
| docs | 补齐 geodns 调度 API 与数据模型 | specs/geodns/api.md, specs/geodns/data-model.md | 文档已定义 |
| docs | 新增 OpenAPI 与 JSON Schema 契约 | contracts/* | 文档已定义 |
| docs | 新增 PostgreSQL / ClickHouse migration 草案和部署样例 | migrations/*, deploy/* | 文档已定义 |
| docs | 保留原始压缩包和原始正文文档快照 | _original_source/* | 已保留 |

## 2026-06-12 — Stage 01 按 START.md 启动开发与文档同步

| 类型 | 描述 | 涉及文件 | 状态 |
|---|---|---|---|
| code | 在 `ocer-dns` 下生成四包工作区，只使用 `portal-web`、`dns-console-web`、`dns-resolver`、`geodns` 当前命名 | ocer-dns/* | 代码草案 |
| code | 为 `portal-web` 增加注册 / 登录服务草案，并补充 `personal_access_tokens` 迁移草案 | ocer-dns/portal-web/app/Domain/Auth/AuthService.php, ocer-dns/portal-web/app/Http/Controllers/Api/V1/Public/AuthController.php, ocer-dns/portal-web/database/migrations/001_portal_web_mvp.sql | 代码草案 |
| code | 为 `portal-web` 增加域名归一化、规则去重、Profile 配置构建和发布 payload 生成服务 | ocer-dns/portal-web/app/Domain/*, ocer-dns/portal-web/app/Infrastructure/DnsConsole/DnsConsoleClient.php | 代码草案 |
| code | 为 `portal-web` 增加 Profile CRUD、规则列表/创建/删除、会员中心总览控制器与服务层草案 | ocer-dns/portal-web/app/Domain/Profile/*, ocer-dns/portal-web/app/Domain/Rule/ProfileRuleService.php, ocer-dns/portal-web/app/Http/Controllers/Api/V1/Member/*, ocer-dns/portal-web/routes/api.php | 代码草案 |
| code | 为 `dns-console-web` 增加 config checksum、bundle 构建、心跳状态计算、GeoDNS 健康视图生成、query log batch 接收服务 | ocer-dns/dns-console-web/app/Domain/* | 代码草案 |
| code | 将 `dns-console-web` Agent / Internal 控制器接到服务层，返回结构对齐文档契约；修正 GeoDNS 健康视图路径为 `/api/v1/internal/geodns/health-view` | ocer-dns/dns-console-web/app/Http/Controllers/Api/V1/*, ocer-dns/dns-console-web/routes/api.php, ocer-dns/geodns/configs/config.example.yaml | 代码草案 |
| code | 为 `dns-resolver` 增加运行时配置结构、域名归一化、allow 优先规则引擎、本地配置原子替换 | ocer-dns/dns-resolver/internal/* | 部分测试通过 |
| code | 为 `dns-resolver` 增加 DoH path / UDP source IP 的 Profile 识别草案与单元测试 | ocer-dns/dns-resolver/internal/profile/resolver.go, ocer-dns/dns-resolver/tests/profile_resolver_test.go | 部分测试通过 |
| code | 为 `geodns` 增加健康视图 client 模型和按区域/负载降权的路由逻辑 | ocer-dns/geodns/internal/* | 部分测试通过 |
| code | 新增 `portal-web` 和 `dns-console-web` PostgreSQL migration 草案 | ocer-dns/*/database/migrations/*.sql | 代码草案 |
| docs | 同步 Stage 01 当前实际进度，补充文档与实现状态，不把代码草案误写为生产完成 | project-doc/05-PLANS.md, project-doc/07-CHANGE-LOG.md | 已同步 |

## 2026-06-14 — Stage 01 控制面协议重构与文档同步

| 类型 | 描述 | 涉及文件 | 状态 |
|---|---|---|---|
| code | `dns-console-web` 增加文档一致的 `Authorization: Internal/Admin ...` 共享 token 解析（仅 `internal` / `admin` 两种 scheme；不存在 `bootstrap` scheme），并让 resolver config 拉取支持 `current_version` 与 `204 No Content` | ocer-dns/dns-console-web/app/Http/Middleware/RequireSharedToken.php, ocer-dns/dns-console-web/app/Http/Controllers/Api/V1/Agent/ConfigPullController.php, ocer-dns/dns-console-web/tests/Feature/ConsoleAgentFlowTest.php | 已测试 |
| code | `dns-resolver` Agent 重构为新协议：`resolver install` 一次性写入 `configs/server.yaml`（含 `api_key` / `secret` / `node_id` 三元组），启动走 `cfg.Validate()`，HMAC 心跳鉴权、全量 bundle 拉取、canonical checksum 校验、ACK 回传；**已删除** bootstrap 注册端点、`identity.json` 持久化、Bearer node token 心跳 | ocer-dns/dns-resolver/internal/agent/agent.go, ocer-dns/dns-resolver/internal/config/config.go, ocer-dns/dns-resolver/configs/server.yaml | 已测试 |
| code | `dns-resolver` 查询日志上报切换到 `/api/v1/agent/query-logs/batch`，增加本地 buffer 回放与上传失败落盘测试 | ocer-dns/dns-resolver/internal/logging/buffer.go, ocer-dns/dns-resolver/internal/logging/buffer_test.go, ocer-dns/dns-resolver/cmd/dns-resolver/main.go | 已测试 |
| code | 打通 resolver UDP/TCP 53 查询链路、source-IP Profile 识别、portal 真实日志/统计回读和设备映射发布，形成配置发布到查询日志展示的代码闭环 | ocer-dns/dns-resolver/internal/dnsserver/server.go, ocer-dns/dns-resolver/cmd/dns-resolver/main.go, ocer-dns/dns-console-web/app/Http/Controllers/Api/V1/Agent/QueryLogController.php, ocer-dns/dns-console-web/app/Http/Controllers/Api/V1/Internal/QueryLogReadController.php, ocer-dns/portal-web/app/Infrastructure/DnsConsole/DnsConsoleClient.php, ocer-dns/portal-web/app/Domain/Profile/MemberWorkspaceService.php, ocer-dns/portal-web/app/Domain/Profile/ProfileConfigBuilder.php | 已测试 |
| test | 新增 Agent 协议与 checksum mismatch 自动化测试；补充 console agent flow 契约测试 | ocer-dns/dns-resolver/internal/agent/agent_test.go, ocer-dns/dns-console-web/tests/Feature/ConsoleAgentFlowTest.php | 已通过 |
| test | 补充 portal 真实日志/统计 internal API 回读测试，并重新验证 `go test ./...`、`go vet ./...`、`php artisan test` | ocer-dns/portal-web/tests/Feature/MemberWorkspaceTest.php, ocer-dns/dns-resolver/*, ocer-dns/dns-console-web/tests/Feature/ConsoleAgentFlowTest.php | 已通过 |
| docs | 同步 `05-PLANS.md`、`specs/dns-console-web/api.md`、`specs/dns-resolver/protocol.md` 当前实现状态，标记真实剩余缺口 | project-doc/05-PLANS.md, specs/dns-console-web/api.md, specs/dns-resolver/protocol.md, project-doc/07-CHANGE-LOG.md | 已同步 |

## 历史说明

原始文档中存在大量“✅”状态，它们表示文档拆解状态，不代表真实代码、测试、部署已经完成。改进版使用：

```text
doc_status
impl_status
test_status
delivery_level
```

来区分文档、实现和验收状态。

## 2026-06-20 — 浏览器测试 P0 修复（Schema 对齐）

| 日期 | 类型 | 描述 | 涉及文件 | 状态 |
|---|---|---|---|---|
| 2026-06-20 | code | 浏览器测试发现并修复 P0 数据库 Schema 缺陷：dns_query_log_ingest_batches 补充 item_count、dns_geo_dns_mappings 补充 priority、dns_admin_roles 补充 is_system/status、dns_team_invitations 补充 declined_at、dns_subscriptions 补充 plan_code 且 plan_id 改可空、dns_policy_snapshots 补充 status、dns_publish_tasks.profile_id 改 VARCHAR(64)、dns_task_executions.id 改 VARCHAR(64)、新建 dns_invoices 表 | ocer-dns/portal-web/database/migrations/2026_06_20_000062_browser_test_p0_fixes.php | 已通过 |
| 2026-06-20 | code | 修复 AdminFinanceController / AdminRbacController 表名前缀错误（带 dns_ 前缀导致查询失败），改用无前缀别名 u/w/r/p/rp | ocer-dns/portal-web/app/Http/Controllers/Api/V1/Admin/AdminFinanceController.php, ocer-dns/portal-web/app/Http/Controllers/Api/V1/Admin/AdminRbacController.php | 已通过 |
| 2026-06-20 | code | 修复 ResolverNode 模型表名指向 resolver_nodes_view（实际视图名） | ocer-dns/portal-web/app/Models/ResolverNode.php | 已通过 |
| 2026-06-20 | code | 修复 OrderService::create() 在未传 idempotency_key 时自动生成基于 userId/planCode/amount/time 的幂等键 | ocer-dns/portal-web/app/Domain/Billing/OrderService.php | 已通过 |
| 2026-06-20 | test | `php artisan migrate:fresh --seed --force` 全部 53+ 个 migration + 4 seeder 执行成功 | ocer-dns/portal-web/database | 已通过 |
| 2026-06-20 | test | 浏览器 API 联调：注册/登录/profile/修改密码/邮箱/钱包充值/团队创建/管理员角色/财务/节点/审计/告警/查询日志/订阅 等 12+ 接口 200/201 | ocer-dns/portal-web (curl smoke) | 已通过 |

## 2026-06-20 — 浏览器测试 P1 字段名核对

| 日期 | 类型 | 描述 | 涉及文件 | 状态 |
|---|---|---|---|---|
| 2026-06-20 | docs | 核对前端与后端字段命名（password/email/teams/recharge），确认当前实现已对齐：注册/登录用 email+password；修改密码 PUT /user/password 用 current_password+new_password；修改邮箱 PUT /user/email 用 email+password；创建团队 POST /user/teams 用 name+slug+description；钱包充值 POST /user/wallet/recharge 用 amount | ocer-dns/portal-web/app/Http/Controllers/Api/V1/User/UserWorkspaceController.php, ocer-dns/portal-web/app/Http/Controllers/Api/V1/User/TeamController.php, ocer-dns/portal-web/web/src/views/user/Account.vue, ocer-dns/portal-web/web/src/views/TeamCreate.vue, ocer-dns/portal-web/web/src/api/client.js | 无需改动 |

## 2026-06-20 — 修复 PUT /user/privacy 报 log_mode 列不存在

| 日期 | 类型 | 描述 | 涉及文件 | 状态 |
|---|---|---|---|---|
| 2026-06-20 | code | 浏览器测试 PUT /user/privacy 触发 `SQLSTATE[42S22] Unknown column 'log_mode'`，根因：`UserWorkspaceService::updatePrivacy` 第 162 行把 `log_mode` 写入顶级列，但 dns_profiles 表只有 `log_retention_days`，`log_mode` 实际是 `privacy_settings` JSON 内部字段。修复：删除该行写入，同时从 `Profile::$fillable` / `$casts` 中移除 `log_mode`，避免 Eloquent 误将其当作独立列 | ocer-dns/portal-web/app/Domain/Profile/UserWorkspaceService.php, ocer-dns/portal-web/app/Models/Profile.php | 已通过 |
| 2026-06-20 | test | API 联调验证：PUT /user/privacy、PUT /user/security、PUT /user/parental 三个设置保存接口均 200 OK | ocer-dns/portal-web (curl smoke) | 已通过 |

## 2026-06-20 — 会员策略目录改为 Tab 切换

| 日期 | 类型 | 描述 | 涉及文件 | 状态 |
|---|---|---|---|---|
| 2026-06-20 | code | `/admin/member-catalogs` 页面把滚动锚点 + 4 区块同屏改为 `el-tabs` 切换：黑名单（block rules）、设备型号、隐私拦截列表、家长（预设+分类）4 个 tab；移除 IntersectionObserver 与滚动逻辑 | ocer-dns/portal-web/web/src/views/admin/MemberCatalogs.vue | 已通过 |
| 2026-06-20 | i18n | 新增 4 个 tab 标签的 i18n key：tabblockList / tabDeviceModels / tabBlocklists / tabParental（zh-CN / en / ko） | ocer-dns/portal-web/web/src/locales/zh-CN.js, ocer-dns/portal-web/web/src/locales/en.js, ocer-dns/portal-web/web/src/locales/ko.js | 已通过 |
| 2026-06-20 | test | `npm run build` 通过，1766 modules transformed，dist gzip 121.96 kB | ocer-dns/portal-web/web | 已通过 |

## 2026-06-20 — Profile 详情页面美化

| 日期 | 类型 | 描述 | 涉及文件 | 状态 |
|---|---|---|---|---|
| 2026-06-20 | code | `/user/profiles/:id` 页面重构：原单卡片堆叠 → 三段式布局（Hero 头部 / 4 张统计卡 / Meta 描述 / 规则列表），统一圆角、阴影、间距、过渡动画 | ocer-dns/portal-web/web/src/views/ProfileDetail.vue | 已通过 |
| 2026-06-20 | i18n | 新增 i18n key：addFirstRule / enabledRules / totalRules / default / publishedAt / metaTitle / blockResponse / version / createdAt / updatedAt / rulesTitle（zh-CN / en / ko） | ocer-dns/portal-web/web/src/locales/zh-CN.js, ocer-dns/portal-web/web/src/locales/en.js, ocer-dns/portal-web/web/src/locales/ko.js | 已通过 |
| 2026-06-20 | test | `npm run build` 通过，1767 modules transformed | ocer-dns/portal-web/web | 已通过 |

## 2026-06-20 — 会员中心端点展示（）

| 日期 | 类型 | 描述 | 涉及文件 | 状态 |
|---|---|---|---|---|
| 2026-06-20 | code | 后端 `/user/dns-endpoints` 接口扩展返回字段：`profile_id`（端点 ID）、`ipv4` 列表（来自 `dns_nodes` status=online 的 public_ipv4，最多 4 个）；`ipv6` 统一为数组格式 | ocer-dns/portal-web/app/Domain/Profile/UserWorkspaceService.php | 已通过 |
| 2026-06-20 | code | Dashboard 右侧 Quick Access 卡片改造为 端点展示：ID / DoT/QUIC / DoH / IPv6 / IPv4 (Bound IP) 五个分组，每行可复制 | ocer-dns/portal-web/web/src/views/Dashboard.vue | 已通过 |
| 2026-06-20 | i18n | 新增 6 个端点标签 i18n key：endpointsTitle / endpointsTag / endpointId / endpointDot / endpointDoh / endpointIpv6 / endpointIpv4 / endpointIpv4Hint（zh-CN / en / ko） | ocer-dns/portal-web/web/src/locales/zh-CN.js, ocer-dns/portal-web/web/src/locales/en.js, ocer-dns/portal-web/web/src/locales/ko.js | 已通过 |
| 2026-06-20 | test | `php artisan serve` + `npm run build` 通过；接口 `GET /user/dns-endpoints?profile_id=b2d137` 返回 `{"profile_id":"b2d137","doh":"https://dns.ocerlink.com/b2d137/dns-query","dot":"b2d137.dns.ocerlink.com","ipv6":["2606:b2:d137::53"],"ipv4":[]}` | ocer-dns/portal-web (curl smoke) | 已通过 |

## 2026-06-20 — System Config 重构：dns_domain 归位 + 运行时配置

| 日期 | 类型 | 描述 | 涉及文件 | 状态 |
|---|---|---|---|---|
| 2026-06-20 | code | SystemConfig 前端页面：把 `dns_domain` 字段从 basic tab 移到 dns tab 顶部，添加 `form-hint` 提示；defaultConfig 与合并逻辑同步更新，自动从 `basic.dns_domain` 旧字段迁移到 `dns.dns_domain` | ocer-dns/portal-web/web/src/views/admin/SystemConfig.vue | 已通过 |
| 2026-06-20 | code | 新增 `App\Support\SystemConfigValue` helper：带 60s 缓存优先从 `system_configs` 表读取，提供 `get($key, $default)` / `field($key, $field)` / `redis()` / `clickhouse()` 便捷方法；缺失值回退到 `config()` | ocer-dns/portal-web/app/Support/SystemConfigValue.php | 已通过 |
| 2026-06-20 | code | `ClickHouseClient::__construct` 改用 `SystemConfigValue::clickhouse()`，host/port/database/credentials 全部从后台运行时配置读取 | ocer-dns/portal-web/app/Infrastructure/ClickHouse/ClickHouseClient.php | 已通过 |
| 2026-06-20 | code | `AppServiceProvider::boot` 增加 `applyRuntimeSystemConfig()`：把 `system_configs.redis` 的 host/port/password/database 合并到 `database.redis.default` 和 `database.redis.cache`，保证 Redis 连接走运行时配置 | ocer-dns/portal-web/app/Providers/AppServiceProvider.php | 已通过 |
| 2026-06-20 | code | `AdminSystemConfigController::update` 保存后调用 `SystemConfigValue::flush()` 清缓存；新增 `migrateLegacyDnsDomain()` 一次性把 `basic.dns_domain` 迁移到 `dns.dns_domain` | ocer-dns/portal-web/app/Http/Controllers/Api/V1/Admin/AdminSystemConfigController.php | 已通过 |
| 2026-06-20 | code | `UserWorkspaceService::getDnsDomain()` 改读 `dns.dns_domain`（带回退 `basic.dns_domain`），保证会员中心端点域名随后台配置实时更新 | ocer-dns/portal-web/app/Domain/Profile/UserWorkspaceService.php | 已通过 |
| 2026-06-20 | i18n | 新增 `admin.systemConfig.dnsDomainHint` i18n key（zh-CN / en / ko） | ocer-dns/portal-web/web/src/locales/zh-CN.js, ocer-dns/portal-web/web/src/locales/en.js, ocer-dns/portal-web/web/src/locales/ko.js | 已通过 |
| 2026-06-20 | test | `php -l` 4 个文件全绿；`npm run build` 通过（dist gzip 568.61 kB）；PUT `/admin/system-config` 写入 `dns.dns_domain=dns.test.example.com` 后 GET `/user/dns-endpoints` 立即返回 `doh: https://dns.test.example.com/b2d137/dns-query` | ocer-dns/portal-web (curl smoke) | 已通过 |

## 2026-06-20 — QueryLogs 动作列多语言显示

| 日期 | 类型 | 描述 | 涉及文件 | 状态 |
|---|---|---|---|---|
| 2026-06-20 | code | `/admin/query-logs` 页面"动作"列从显示原始 `row.action`（allow/block/allowed/blocked）改为通过 `actionLabel()` 映射到 i18n 文案；tag 类型同步改用 `isAllowAction()` 判断（兼容 allow/allowed 两种值） | ocer-dns/portal-web/web/src/views/admin/QueryLogs.vue | 已通过 |
| 2026-06-20 | i18n | 新增 ko.js `admin.queryLogsPage` i18n 块（zh-CN / en 已存在，ko 缺失，补全） | ocer-dns/portal-web/web/src/locales/ko.js | 已通过 |
| 2026-06-20 | test | `npm run build` 通过，1767 modules，dist gzip 612.72 kB | ocer-dns/portal-web/web | 已通过 |

## 2026-06-24 — 会员中心页面/API 与 Stripe 支付闭环

| 日期 | 类型 | 描述 | 涉及文件 | 状态 |
|---|---|---|---|---|
| 2026-06-24 | code | 修复用户端安全防护/隐私保护/家长控制初始化误触发自动保存；家长控制补齐 `enabled` 总开关并兼容多语言目录项保存 | portal-web/web/src/views/Security.vue, portal-web/web/src/views/Privacy.vue, portal-web/web/src/views/ParentalControl.vue, portal-web/app/Http/Controllers/Api/V1/User/UserWorkspaceController.php | 已通过 |
| 2026-06-24 | code | 修复 Profile 规则 allow/block 与 allowlist/blocklist 存储值不一致、允许规则默认 action 写成 block、配置发布未携带完整安全/隐私/家长设置的问题；配置方案列表和详情页补齐发布按钮 | portal-web/app/Domain/Rule/ProfileRuleService.php, portal-web/app/Application/Member/ProfilePublishApplicationService.php, portal-web/app/Domain/Profile/ProfileService.php, portal-web/web/src/views/ProfileList.vue, portal-web/web/src/views/ProfileDetail.vue | 已通过 |
| 2026-06-24 | code | 补齐 `profile_versions` 表和模型；修复全数字 profile 短 ID 被误当作自增 id 导致 config_versions 外键失败；ClickHouse 统计服务改为可通过容器替换客户端，便于测试与运行时注入 | portal-web/database/migrations/2026_06_24_000001_create_profile_versions_table.php, portal-web/app/Models/ProfileVersion.php, portal-web/app/Domain/Publish/PublishService.php, portal-web/app/Domain/Ingest/QueryLogReadService.php, portal-web/app/Infrastructure/ClickHouse/UserAnalyticsService.php | 已通过 |
| 2026-06-24 | code | 订单管理页补齐刷新、查看详情和 Stripe 支付动作；账户页普通订单/订阅续费统一走 Stripe Checkout，余额仅保留充值入口；Stripe secret/webhook secret 改读后台 System Config | portal-web/web/src/views/Membership.vue, portal-web/web/src/views/user/Account.vue, portal-web/app/Domain/Billing/PaymentService.php, portal-web/app/Http/Controllers/Api/V1/User/OrderController.php, portal-web/app/Http/Controllers/Api/V1/StripeWebhookController.php, portal-web/app/Http/Controllers/Api/V1/Admin/AdminSystemConfigController.php | 已通过 |
| 2026-06-24 | code | 修复用户/管理端查询日志与统计的 ClickHouse 参数、action 值兼容、profile_id 筛选和 CSV 导出链路 | portal-web/app/Infrastructure/ClickHouse/ClickHouseClient.php, portal-web/app/Infrastructure/ClickHouse/UserAnalyticsService.php, portal-web/app/Domain/Ingest/QueryLogReadService.php, portal-web/app/Http/Controllers/Api/V1/Admin/AdminQueryLogController.php, portal-web/web/src/views/admin/QueryLogs.vue | 已通过 |
| 2026-06-24 | test | `php -l` 覆盖本次修改的 PHP 文件通过；`npm run build` 通过（1778 modules transformed） | portal-web/app, portal-web/web | 已通过 |

## 2026-06-25 — 多 Profile / 威胁情报 / 多设备 / 时区修复

| 日期 | 类型 | 描述 | 涉及文件 | 状态 |
|---|---|---|---|---|
| 2026-06-25 | code | dns-resolver DoH 协议日志补齐 DeviceUID + DeviceType 字段 | dns-resolver/internal/doh/server.go | ok |
| 2026-06-25 | code | resolver.Handler 签名扩展 deviceType 参数；appendLog 同步补字段 | dns-resolver/internal/resolver/handler.go | ok |
| 2026-06-25 | code | LogEntry 增加 DeviceType 字段(omitempty) | dns-resolver/internal/logging/buffer.go | ok |
| 2026-06-25 | code | portal-web QueryLogController 提取并保存 device_type 到 dns_devices | portal-web/app/Http/Controllers/Api/V1/Node/QueryLogController.php | ok |
| 2026-06-25 | code | 修复 dns_logs.event_time 时区不一致（PHP 输出 UTC 字符串被 ClickHouse CST 服务端解析产生 8h 漂移），统一按 Asia/Shanghai 格式化 | portal-web/app/Http/Controllers/Api/V1/Node/QueryLogController.php | ok |
| 2026-06-25 | test | 全链路最终回归 P0~P3 共 28 用例全部通过（97.2s） | /tmp/regression_final.py | ok |
| 2026-06-25 | docs | 同步本变更日志 | project-doc/07-CHANGE-LOG.md | ok |
