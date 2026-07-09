# 变更日志（Bug / Feature Log）

> 记录每次功能增减、Bug 修复、文档变更。没有构建、测试、部署证据时，状态只能写"文档已定义"或"代码草案"。

## 2026-07-09 — UI 优化：套餐选择页隐藏无效价格周期选项

| 日期 | 类型 | 描述 | 涉及文件 | 状态 |
|---|---|---|---|---|
| 2026-07-09 | fix | 修复 /user/account 选择套餐时，月付/年付 toggle 始终显示的问题：后台未设置或设为 0 的计费周期现在自动隐藏；增加 hasMonthlyPrice/hasYearlyPrice 计算属性判断有效价格，当某周期无有效价格时自动切换到另一可用周期 | portal-web/web/src/views/user/Account.vue | ok |
| 2026-07-09 | fix | SubscriptionCheckout.vue 过滤 amount_minor<=0 的价格选项，避免展示 $0.00 计费周期 | portal-web/web/src/views/SubscriptionCheckout.vue | ok |
| 2026-07-09 | fix | Plans.vue 套餐展示页过滤 amount_minor<=0 的价格项，避免误导用户 | portal-web/web/src/views/Plans.vue | ok |

## 2026-07-09 — P1 修复：/user/account 配额显示异常

| 日期 | 类型 | 描述 | 涉及文件 | 状态 |
|---|---|---|---|---|
| 2026-07-09 | fix | 修复 /user/account 页面配额显示 "0 / 300,000" 始终为 0 的问题：billing_periods 表中没有当前用户周期记录导致 queries_used 返回 0 | portal-web/app/Http/Controllers/Api/V1/User/UserWorkspaceController.php | ok |
| 2026-07-09 | fix | 修复 ClickHouse SummingMergeTree + async_insert 组合导致数据写入后不可见的问题：INSERT 返回 200 但数据从未落盘（约 6174 行丢失）；在 ClickHouseClient::insertJsonEachRow 中添加 SETTINGS async_insert=0, wait_for_async_insert=0 强制同步写入 | portal-web/app/Infrastructure/ClickHouse/ClickHouseClient.php | staging |
| 2026-07-09 | fix | 修复 ClickHouse 认证失败：config/clickhouse.php 默认 username='ocer' 但线上 ClickHouse 仅存在 default 用户，导致 HTTP 403 Authentication failed；改为 env('CLICKHOUSE_USERNAME', 'default') 并支持环境变量覆盖 | portal-web/config/clickhouse.php | ok |
| 2026-07-09 | fix | 修复数据库覆盖配置：dns_system_configs 表中 clickhouse.username='ocer' 覆盖配置文件默认值；UPDATE 为 'default' 对齐线上账号 | MySQL dns_system_configs 表 | ok |
| 2026-07-09 | fix | 修复 SummingMergeTree 数据永久不可见：将 usage_events 表引擎从 SummingMergeTree 改为 MergeTree（SummingMergeTree 后台合并可能永不触发，INSERT 成功但 SELECT 看不到）；通过 RENAME → CREATE → INSERT → DROP 安全迁移，保留历史数据 | ClickHouse ocer_dns.usage_events | ok |
| 2026-07-09 | feat | 新增 ClickHouseSetupCommand (php artisan clickhouse:setup)：幂等创建 usage_events 和 dns_logs 表，支持本地和远程 ClickHouse（.env CLICKHOUSE_HOST），用于初始化/重建表结构 | portal-web/app/Console/Commands/ClickHouseSetupCommand.php | ok |
| 2026-07-09 | fix | 修正 ClickHouseSetupCommand 中 dns_logs 表结构，与线上实际部署对齐（原命令 schema 与实际不一致，缺少 event_id/node_id/query_type/rcode/latency_ms 等字段，缺少索引和 TTL）；修复 sendRaw DDL 调用方式（需以 body 形式发送） | portal-web/app/Console/Commands/ClickHouseSetupCommand.php | ok |
| 2026-07-09 | docs | 完善 ClickHouse tables.md 文档：增加"实际部署表结构"章节（usage_events + dns_logs 完整字段、索引、TTL），保留"设计规格"供参考并标注差异 | ai-doc/specs/clickhouse/tables.md | ok |

## 2026-07-09 — 准确性提升：全量文档与代码对齐

### 1. 残留 dns-console-web 引用清理

| 日期 | 类型 | 描述 | 涉及文件 | 涉及文档 | 状态 |
|---|---|---|---|---|---|---|
| 2026-07-09 | docs | 修复 6 处残留 dns-console-web 引用→portal-web(原 console 域) | 10-NEXTDNS-LITE-BILLING.md, openapi.yaml, billing.schema.json, billing-usage-batch.sample.json, rules/coding.md, clickhouse/tables.md | — | ok |

### 2. data-schema.md 全量重写（对齐 65 个迁移文件）

| 日期 | 类型 | 描述 | 涉及文件 | 涉及文档 | 状态 |
|---|---|---|---|---|---|---|
| 2026-07-09 | docs | 重写 data-schema.md：所有 uuid→bigint；profile_rules.list_type 值修正；devices 补充 13 个缺失字段；plans/subscriptions 对齐实际结构；team_members/invitations 字段名修正；删除不存在的 profile_feature_settings 表；新增 40+ 个表定义 | ai-doc/specs/portal-web/data-schema.md | — | ok |

### 3. OpenAPI 路由同步修复：与实际路由对齐

| 日期 | 类型 | 描述 | 涉及文件 | 涉及文档 | 状态 |
|---|---|---|---|---|---|---|
| 2026-07-09 | docs | 修复 openapi.yaml 路由与实际 php artisan route:list 不一致的问题（见下方详细清单） | ai-doc/contracts/openapi.yaml | — | ok |

### 修复清单

**路径修正：**
- allowlist/blocklist：去掉 `/profiles/{profile_id}/` 前缀，改为 `/api/v1/user/allowlist`、`/api/v1/user/blocklist`
- settings/security/privacy/parental：去掉 `/profiles/{profile_id}/` 前缀
- `clone` → `copy`
- `/api/v1/user/usage/quota` → `/api/v1/user/usage`
- `/api/v1/user/profiles/{profile_id}/stats/top-domains` → `/api/v1/user/top-domains`
- `/api/v1/user/profiles/{profile_id}/stats/timeseries` → `/api/v1/user/query-trend`
- `/api/v1/user/subscription` → `/api/v1/user/subscriptions`
- `/api/v1/user/subscription/checkout` → `/api/v1/user/subscriptions/{id}/checkout`
- `/api/v1/admin/audit-logs` → `/api/v1/admin/console/audit-logs`
- `/api/v1/admin/auth/login` → `/api/v1/admin/login`
- `/api/v1/admin/users/{user_id}/{action}` → 拆分为 `/api/v1/admin/users/{user_id}/disable` 和 `/api/v1/admin/users/{user_id}/enable`
- `/api/v1/admin/rules/{rule_id}/sync` → `/api/v1/admin/rules/{name}/sync`
- PUT `/api/v1/user/api-keys/{key_id}` → 移除（只有 DELETE）
- 移除 `/api/v1/user/api-keys/{key_id}/rotate`
- 移除 `/api/v1/user/devices/{device_id}`、`/disable`、`/enable`
- 团队路由：`/members/{member_id}` → `/members/{user_id}` + `/members/{user_id}/role`

**移除不存在的路由：**
- `/api/v1/user/invoices`、`/api/v1/user/payments`、`/api/v1/user/overview`
- `/api/v1/user/profiles/{profile_id}/logs`、`/publishes`、`/publishes/{publish_id}`
- `/api/v1/user/profiles/{profile_id}/rules/import`、`/rules/export`
- `/api/v1/user/profiles/{profile_id}/settings/dns`
- `/api/v1/user/profiles/{profile_id}/stats/summary`

**补充缺失的路由：**
- 批量操作：`/api/v1/user/allowlist/batch-delete`、`/blocklist/batch-delete`、`/profiles/batch-delete`、`/profiles/{profile_id}/rules/batch-delete`
- 用户：`PUT /api/v1/user/email`、`GET /api/v1/user/catalogs`、`/rule-categories`、`/rule-sources`、`/payment-methods`、`/stripe-config`
- 订阅：`GET /api/v1/user/subscriptions`、`/subscriptions/{id}`、`/subscriptions/{id}/cancel`、`/subscriptions/{id}/resume`
- 支付：`POST /api/v1/user/payment-transactions/{id}/mock-success`、`GET /api/v1/user/payment-transactions/{id}/status`
- 团队：`/teams/{team_id}/switch`、`/transfer-ownership`、`/invitations/batch-cancel`、`DELETE /invitations/{invitation_id}`
- 节点：`POST /api/v1/node/dns-resolver/register`
- 公共：`GET /api/v1/dns-config`、`GET /api/v1/build/{path}`
- 管理员：`POST /api/v1/admin/login`

## 2026-07-09 — 文档同步修复：代码与文档差异对齐

| 日期 | 类型 | 描述 | 涉及文件 | 状态 |
|---|---|---|---|---|
| 2026-07-09 | doc | 修复 START.md：将错误的 `global_config_versions` 表名示例改为 `profile_versions` | ai-doc/START.md | ok |
| 2026-07-09 | doc | 修复 01-ARCHITECTURE.md：更新表名 `config_versions` → `profile_versions`，更新节点表名 `nodes` → `resolver_nodes` | ai-doc/project-doc/01-ARCHITECTURE.md | ok |
| 2026-07-09 | doc | 修复 02-MODULES.md：移除不存在的服务声明（ConfigBuildService、RuleLibraryService、AdminConsoleAuditService、SystemConfigService），补充实际存在的服务（ProfileConfigBuilder、RuleCategoryResolver、基础设施服务） | ai-doc/project-doc/02-MODULES.md | ok |
| 2026-07-09 | doc | 修复 03-DATA-FLOW.md：更新表名 `config_versions` → `profile_versions` | ai-doc/project-doc/03-DATA-FLOW.md | ok |
| 2026-07-09 | doc | 修复 04-FEATURES.md：添加威胁检测 API、设备 IP 绑定等新增功能 | ai-doc/project-doc/04-FEATURES.md | ok |
| 2026-07-09 | doc | 修复 09-CLOSED-LOOP-AND-DATA-DESTINATIONS.md：更新表名 `config_versions` → `profile_versions`，更新节点表名 | ai-doc/project-doc/09-CLOSED-LOOP-AND-DATA-DESTINATIONS.md | ok |
| 2026-07-09 | doc | 修复 rules/coding.md：更新表名 `config_versions` → `profile_versions`，更新域名描述 | ai-doc/rules/coding.md | ok |
| 2026-07-09 | doc | 修复 specs/portal-web/data-schema.md：更新 console 域管理表列表 | ai-doc/specs/portal-web/data-schema.md | ok |
| 2026-07-09 | doc | 修复 specs/portal-web/api.md：更新表名 `config_versions` → `profile_versions` | ai-doc/specs/portal-web/api.md | ok |
| 2026-07-09 | doc | 修复 15-CONFIG-ARCHITECTURE.md：配置拉取间隔 5分钟→30秒，版本检查间隔 5分钟→2分钟 | ai-doc/project-doc/15-CONFIG-ARCHITECTURE.md | ok |
| 2026-07-09 | doc | 修复 billing-finance.md：移除 dns-console-web 引用（3处），改为 portal-web(原 console 域) | ai-doc/specs/portal-web/billing-finance.md | ok |
| 2026-07-09 | doc | 修复 nats/events.md：移除 dns-console-web 引用（7处），改为 portal-web(原 console 域) | ai-doc/specs/nats/events.md | ok |
| 2026-07-09 | doc | 修复 geodns/data-model.md：移除 dns-console-web 引用，改为 portal-web(原 console 域) | ai-doc/specs/geodns/data-model.md | ok |
| 2026-07-09 | doc | 修复 specs/portal-web/api.md：更新"当前实现映射"过时内容（2026-06-12→2026-07-09），用户 ID 格式 `usr_01H...`→整型 | ai-doc/specs/portal-web/api.md | ok |
| 2026-07-09 | doc | 修复 data-schema.md：personal_access_tokens 字段对齐实际迁移文件（id bigint 非 uuid，token 非 token_hash，补充 abilities 字段） | ai-doc/specs/portal-web/data-schema.md | ok |
| 2026-07-09 | doc | 修复 START.md：移除已删除目录引用（migrations/*, deploy/*） | ai-doc/START.md | ok |

## 2026-07-09 — P0 修复：前端 UI 审查问题（硬编码/验证/加载/确认/变量声明）

| 日期 | 类型 | 描述 | 涉及文件 | 涉及文档 | 状态 |
|---|---|---|---|---|---|---|
| 2026-07-09 | fix | Plans.vue 全部硬编码中文替换为 $t() i18n 调用（约15处，含标题、副标题、套餐状态、购买按钮、确认对话框等） | portal-web/web/src/views/Plans.vue | — | ok |
| 2026-07-09 | i18n | 新增 17 个 plans i18n keys（title/subtitle/currentFreePlan/upgradeHint/monthly/yearly/perMonth/perYear/currentPlan/buyNow/loadFailed/notSupported/confirmPurchase/confirmOrder/confirm/orderCreated/orderFailed） | portal-web/web/src/locales/en.json, zh-CN.json | — | ok |
| 2026-07-09 | fix | Settings.vue 密码表单添加 el-form 验证规则（:rules="passwordRules" ref="passwordFormRef"），含 required/min:8/confirm 对比验证 | portal-web/web/src/views/Settings.vue | — | ok |
| 2026-07-09 | i18n | 新增 5 个 settings 密码验证 i18n keys（passwordRequired/newPasswordRequired/passwordMinLength/confirmPasswordRequired/passwordMismatch） | portal-web/web/src/locales/en.json, zh-CN.json | — | ok |
| 2026-07-09 | fix | Security.vue 添加 v-loading="hydrating" 加载状态 | portal-web/web/src/views/Security.vue | — | ok |
| 2026-07-09 | fix | RoleManagement.vue deleteRole 添加 ElMessageBox.confirm 确认弹窗 | portal-web/web/src/views/admin/RoleManagement.vue | — | ok |
| 2026-07-09 | i18n | 新增 admin.rbac.confirmDeleteRole i18n key | portal-web/web/src/locales/en.json, zh-CN.json | — | ok |
| 2026-07-09 | fix | PaymentFlows.vue 新增 const filterType = ref('') 变量声明，修复 type 筛选功能 | portal-web/web/src/views/admin/PaymentFlows.vue | — | ok |
| 2026-07-09 | test | 前端构建验证 npx vite build 1792+ modules transformed 通过 | portal-web/web | — | ok |

## 2026-07-09 — 修复 Analytics 页面配额百分比显示 0% 的问题

| 日期 | 类型 | 描述 | 涉及文件 | 涉及文档 | 状态 |
|---|---|---|---|---|---|
| 2026-07-09 | fix | analytics 后端新增 resolveQuotaLimit() 方法，返回 quota_limit 字段，修复前端配额百分比始终显示 0% 的 Bug | portal-web/app/Domain/Profile/UserWorkspaceService.php#L346-L397 | — | ok |
| 2026-07-09 | code | 前端 Analytics.vue 配额百分比计算逻辑不变（`quota_limit` 字段名已对齐） | portal-web/web/src/views/Analytics.vue#L222-L228 | — | ok |

## 2026-07-07 — 恢复 MemberCatalogService 安全防护功能条目

| 日期 | 类型 | 描述 | 涉及文件 | 状态 |
|---|---|---|---|---|
| 2026-07-07 | fix | MemberCatalogService::defaults() 恢复完整功能条目：DNS 重新绑定、IDN 同构、误植域名、DGA、挖矿病毒、动态 DNS、停放域名、特定 TLD、恶意软件、钓鱼攻击 | portal-web/app/Domain/Profile/MemberCatalogService.php#L30-L45 | ok |
| 2026-07-07 | code | 生产数据库 member_feature_catalogs 更新：恢复 15 条安全防护功能 | portal-web DB system_configs 表 | ok |
| 2026-07-07 | ops | 生产服务器缓存清理：php artisan cache:clear / config:clear | 103.86.44.194 | ok |

### 恢复原因

用户反馈 `/admin/member-catalogs` 页面的"安全防护" tab 功能被误删，需要恢复完整的安全防护功能列表。

### 恢复内容

恢复 10 条已删除功能：
- DNS 重新绑定攻击保护 (dns_rebinding)
- IDN 同构攻击保护 (idn_homograph)
- 误植域名保护 (typosquatting)
- 域名生成算法保护 (dga)
- 挖矿病毒保护 (anti_mining)
- 拦截动态 DNS (block_dynamic_dns)
- 拦截停放域名 (block_parked_domains)
- 拦截特定顶级域名 (block_specific_tld)
- 拦截恶意软件 (block_malware)
- 拦截钓鱼攻击 (block_phishing)

### 验证结果

- DB 查询确认 device_models count: 15
- API 验证 15 条完整功能列表

## 2026-07-07 — 删除整个 SecurityCatalogPage 页面

| 日期 | 类型 | 描述 | 涉及文件 | 状态 |
|---|---|---|---|---|
| 2026-07-07 | code | 删除前端 Vue 文件 SecurityCatalogPage.vue | portal-web/web/src/views/admin/SecurityCatalogPage.vue (deleted) | ok |
| 2026-07-07 | code | 删除路由配置：import AdminSecurityCatalogPage 和 route security-catalog | portal-web/web/src/router/index.js#L35, #L114 | ok |
| 2026-07-07 | code | 删除数据库菜单记录：admin_menu_rule 表中 menu_key=security_catalog | portal-web DB admin_menu_rule 表 | ok |
| 2026-07-07 | test | 前端构建成功：npx vite build 1792 modules transformed | portal-web/web | ok |
| 2026-07-07 | ops | 生产环境部署：上传 dist + 清理缓存 | 103.86.44.194 | ok |

### 删除原因

用户要求删除整个 `/admin/security-catalog` 页面，"安全防护"配置功能不再需要。

### 保留内容

- **后端 API `/admin/protection-policies`** 保留：可能其他模块仍在使用
- **AdminProtectionPolicyController** 保留：避免破坏现有功能

### 验证结果

- 前端构建成功（1792 modules，减少 2 个模块）
- 生产环境部署成功
- 数据库菜单删除确认

## 2026-07-07 — 清理 SecurityCatalogPage device_models 数据库条目

| 日期 | 类型 | 描述 | 涉及文件 | 状态 |
|---|---|---|---|---|
| 2026-07-07 | code | MemberCatalogService::defaults() device_models 删除已实现功能条目：DNS 重新绑定、IDN 同构、误植域名、DGA、动态 DNS、停放域名、特定 TLD、挖矿病毒 | portal-web/app/Domain/Profile/MemberCatalogService.php#L26-L35 | ok |
| 2026-07-07 | code | 生产数据库 member_feature_catalogs 更新：只保留 5 条核心条目（威胁情报、AI 威胁检测、Google 安全浏览、拦截新注册域名、拦截儿童色情内容） | portal-web DB system_configs 表 | ok |
| 2026-07-07 | ops | 生产服务器缓存清理：php artisan cache:clear / config:clear / route:clear / view:clear | 103.86.44.194 | ok |

### 删除原因

用户反馈 `/admin/security-catalog` 页面的"设备型号"表仍显示 13 条历史条目，但部分功能已实现或无用：
- **已实现功能**：Resolver 已在 Engine 中实现（DNS 重新绑定、IDN 同构、误植域名、DGA、动态 DNS、特定 TLD），无需 UI 开关
- **无用功能**：停放域名、挖矿病毒等功能未规划实现
- **保留功能**：威胁情报、AI 威胁检测、Google 安全浏览、拦截新注册域名、拦截儿童色情内容（近期新增功能）

### 验证结果

- DB 查询确认 device_models count: 5
- API 验证 5 条：threat_intel, ai_threat_detection, google_safe_browsing, block_newly_registered, block_csam

## 2026-07-06 — 实现威胁检测 API 功能（完整链路）

| 日期 | 类型 | 描述 | 涉及文件 | 状态 |
|---|---|---|---|---|
| 2026-07-06 | code | portal-web 前端添加"威胁检测 API"配置 tab（Google Safe Browsing、WhoisXML、停放域名、AI 威胁检测） | portal-web/web/src/views/admin/SystemConfig.vue#L163-L203 | ok |
| 2026-07-06 | code | portal-web ConfigPullController 返回 threat_detection 配置 | portal-web/app/Http/Controllers/Api/V1/Node/ConfigPullController.php#L40-L56 | ok |
| 2026-07-06 | code | Resolver config/types.go 添加 ThreatDetectionConfig 结构体 | dns-resolver/internal/config/types.go#L14-L22 | ok |
| 2026-07-06 | code | Resolver externalthreat/client.go 实现四个外部 API 调用逻辑 | dns-resolver/internal/externalthreat/client.go (新建) | ok |
| 2026-07-06 | code | Resolver agent.go 添加 threatClient 字段和初始化逻辑 | dns-resolver/internal/agent/agent.go#L54, #L229-L250 | ok |
| 2026-07-06 | code | Resolver agent.go 添加 syncThreatData() 和 GetThreatClient() 方法 | dns-resolver/internal/agent/agent.go#L763-L790 | ok |
| 2026-07-06 | i18n | 新增 17 个 systemConfig threat_detection i18n keys（三语） | portal-web/web/src/locales/zh-CN.json, en.json, ko.json | ok |

### 功能说明

威胁检测 API 实现了四个外部威胁检测服务：

1. **Google Safe Browsing**：调用 Google API 检测恶意域名
2. **WhoisXML 新注册域名**：同步最近 N 天内注册的域名列表
3. **停放域名列表**：从 URL 拉取停放域名特征库
4. **AI 威胁检测**：调用第三方 AI 威胁检测 API

### 验证结果

- portal-web 前端 `npx vite build` 退出码 0
- Resolver `go build ./...` 退出码 0

### 数据流

```
┌─────────────────────────────────────────────────────────────────────┐
│  portal-web 系统配置                                                 │
│  ├─ /admin/system-config threat_detection tab                       │
│  └─ 配置存储到 system_config 表                                      │
│                                                                      │
│  Resolver 配置拉取                                                   │
│  ├─ GET /api/v1/node/dns-resolver/config                            │
│  └─ 返回 threat_detection 配置                                      │
│                                                                      │
│  Resolver 威胁检测                                                   │
│  ├─ externalthreat.Client 初始化                                    │
│  ├─ 检测流程：                                                       │
│  │   ├─ Google Safe Browsing API（实时调用，缓存 1 小时）            │
│  │   ├─ 新注册域名（本地缓存匹配）                                   │
│  │   ├─ 停放域名（本地缓存匹配）                                     │
│  │   ├─ AI 威胁检测 API（实时调用，缓存 1 小时）                     │
│  └─ 定期同步：新注册域名 + 停放域名列表                              │
└─────────────────────────────────────────────────────────────────────┘
```

### 待完成

- Engine 中集成威胁检测调用（CheckThreat 方法）
- 回归测试和端到端测试

## 2026-07-06 — 添加威胁检测 API 配置（前端部分）

| 日期 | 类型 | 描述 | 涉及文件 | 状态 |
|---|---|---|---|---|
| 2026-07-06 | code | SystemConfig.vue 新增"威胁检测 API" tab，配置 Google Safe Browsing、WhoisXML、停放域名、AI 威胁检测 API Key | portal-web/web/src/views/admin/SystemConfig.vue#L163-L203 | ok |
| 2026-07-06 | code | defaultConfig 新增 threat_detection 配置对象 | portal-web/web/src/views/admin/SystemConfig.vue#L272-L279 | ok |
| 2026-07-06 | i18n | 新增 17 个 systemConfig threat_detection i18n keys（三语） | portal-web/web/src/locales/zh-CN.json, en.json, ko.json | ok |

### 验证结果

- `npx vite build` 退出码 0

### 待完成

- Resolver 实现外部 API 调用逻辑（Google Safe Browsing、WhoisXML、停放域名、AI 威胁检测）

## 2026-07-06 — SecurityCatalogPage 删除已实现/未实现功能选项

| 日期 | 类型 | 描述 | 涉及文件 | 状态 |
|---|---|---|---|---|
| 2026-07-06 | code | 删除模板中已实现功能：DNS 重绑定、IDN 同构、误植域名、DGA、动态 DNS、TLD、新注册域名、停放域名 | portal-web/web/src/views/admin/SecurityCatalogPage.vue | ok |
| 2026-07-06 | code | 删除模板中未实现功能：AI 威胁检测、Google 安全浏览 | portal-web/web/src/views/admin/SecurityCatalogPage.vue | ok |
| 2026-07-06 | code | 删除分类屏蔽选项：恶意软件、钓鱼、C2、挖矿病毒（通过威胁情报源管理） | portal-web/web/src/views/admin/SecurityCatalogPage.vue | ok |
| 2026-07-06 | code | 清理 script：删除 whitelistText ref，form 仅保留 threat_intel 字段，简化 fetchPolicies/handleSaveAll | portal-web/web/src/views/admin/SecurityCatalogPage.vue | ok |

### 删除原因

- **已实现功能**：Resolver 已实现（DNS重绑定、IDN同构、误植域名、DGA、动态DNS、TLD），UI 选项重复
- **未实现功能**：Resolver 无实现（AI检测、Google安全浏览、新注册域名、停放域名）
- **分类屏蔽**：通过 `/admin/rule-sources` 威胁情报源管理，不再需要单独开关

### 验证结果

- `npx vite build` 退出码 0

## 2026-07-06 — 删除 SecurityCatalogPage 隐私防护和家长控制 tab

| 日期 | 类型 | 描述 | 涉及文件 | 状态 |
|---|---|---|---|---|
| 2026-07-06 | code | SecurityCatalogPage.vue 删除隐私防护和家长控制两个 el-tab-pane，仅保留安全防护 tab | portal-web/web/src/views/admin/SecurityCatalogPage.vue | ok |
| 2026-07-06 | code | 清理 script 部分：删除 catalogs.privacy_blocklists/parental_presets、分页/过滤变量、fieldsPerTab/createDefaults、categoryLabel 函数 | portal-web/web/src/views/admin/SecurityCatalogPage.vue | ok |

### 验证结果

- `npx vite build` 退出码 0

## 2026-07-06 — Dashboard 页面新增设备 IP 绑定显示与更换功能

| 日期 | 类型 | 描述 | 涉及文件 | 状态 |
|---|---|---|---|---|
| 2026-07-06 | code | 后端 API 扩展支持 source_ip 更新：UserWorkspaceController 验证规则新增 `source_ip`，UserWorkspaceService::updateDevice 支持更新 `source_ip` 并清除 `ip_hash` | portal-web/app/Http/Controllers/Api/V1/User/UserWorkspaceController.php, portal-web/app/Domain/Profile/UserWorkspaceService.php | ok |
| 2026-07-06 | code | Dashboard 新增设备 IP 绑定显示区域 + el-dialog 更换绑定对话框，用户可选择设备并输入绑定 IP | portal-web/web/src/views/Dashboard.vue | ok |
| 2026-07-06 | i18n | 新增 11 个 dashboard i18n keys（deviceIpBinding/changeBinding/changeDeviceIp/selectDevice/bindIp/bind/cancel/bindFormRequired/bindSuccess/bindFailed） | portal-web/web/src/locales/zh-CN.json, en.json, ko.json | ok |

### 验证结果

- `npx vite build` 退出码 0
- PHP 语法检查通过
- i18n JSON 合法性检查通过

## 2026-07-06 — Dashboard 页面新增 DNS IPv4 地址显示

| 日期 | 类型 | 描述 | 涉及文件 | 状态 |
|---|---|---|---|---|
| 2026-07-06 | code | Dashboard DNS Endpoints 卡片新增 IPv4 地址显示（绑定 IP），用户可直接看到分配的 DNS 服务器 IP | portal-web/web/src/views/Dashboard.vue | ok |

### 验证结果

- `npx vite build` 退出码 0
- i18n key `dashboard.endpointIpv4` 已存在于 zh-CN/en/ko 三语

## 2026-07-06 — 清理 6/30 菜单迁移未完成遗留的死文件

| 日期 | 类型 | 描述 | 涉及文件 | 状态 |
|---|---|---|---|---|
| 2026-07-06 | chore | 🧹 删除 `web/src/views/admin/ProtectionPolicies.vue`（无路由、无菜单的死文件）：migration 2026_06_30_051924 已把菜单 `protection_policies` 合并到 `security_catalog`，但 `ProtectionPolicies.vue` 留作死代码。`AdminProtectionPolicyController` 与 API `/admin/protection-policies` 保留 — 新页面 `SecurityCatalogPage.vue` 仍依赖此 API。`npx vite build` 退出码 0 | portal-web/web/src/views/admin/ProtectionPolicies.vue (deleted) | ok |

## 2026-07-06 — P0 配置漂移 + E2E 大面积修复

| 日期 | 类型 | 描述 | 涉及文件 | 状态 |
|---|---|---|---|---|
| 2026-07-06 | fix | 🔴 MemberCatalogService::defaults() 缺失：b4369bab 删了 hardcode 但 `MemberFeatureCatalogSeeder` / `tests/Feature/ApiTest` 仍在调，触发 157 个 E2E 失败。补回 public defaults()，骨架来自生产 `system_configs.member_feature_catalogs` 实际 system items（13 device_models + 3 privacy_blocklists + 4 parental_presets），保证测试 seed + 后台 admin 都可调 | portal-web/app/Domain/Profile/MemberCatalogService.php | ok |
| 2026-07-06 | fix | 🟠 resolver 日志 ACK 测试 mock 与生产 portal-web `QueryLogIngestService::accept()` 真实响应不一致：原 mock 返回 `{"data":{"received_count":1}}` 缺 `accepted=true`，resolver 严格校验 ACK 拒绝接收。修正 mock 响应为完整 ACK 结构，**未动**生产 buffer.go | dns-resolver/internal/logging/buffer_test.go | ok |
| 2026-07-06 | fix | 🔴 GeoDNS 端口 5354→15354 全工程切换：6/25 PR 写了"端口 5354→15354"但代码从未实际切，整个工程 15+ 文件仍用 5354；portal-web GeoDNS.vue UI 一直显示 15354，与现实脱节。本次按 START.md hard constraint 落地：geodns 2 yaml + 1 config.go + 1 install.go + 1 main.go + portal-web install 脚本 + dns-resolver 4 yaml + 1 config.go + 1 Dockerfile + 启动脚本 + 部署文档 + specs/api.md + 03-DATA-FLOW.md + 01-ARCHITECTURE.md 共 18 文件。重启 geodns 验证 TCP 15354 真正监听，5354 已无人监听 | geodns/{configs/config.yaml,configs/config.example.yaml,configs/test.yaml,internal/config/config.go,cmd/geodns/main.go,cmd/geodns/install.go} / portal-web/public/build/geodns-install.sh / dns-resolver/{configs/server.yaml,configs/server.yaml.bak,configs/server-node2.yaml,configs/server-test.yaml,configs/test-config.yaml,configs/config.example.yaml,internal/config/config.go,Dockerfile} / start-all.sh / stop-all.sh / ai-doc/deploy/local-dev.md / ai-doc/prompts/部署.md / ai-doc/specs/geodns/api.md / portal-web/project-doc/03-DATA-FLOW.md / ai-doc/project-doc/01-ARCHITECTURE.md | ok |

### 验证结果

- `php artisan test --testsuite=Feature` 177/177 通过（含 1 risky E2E 自检，0 失败）— 修复前是 157 失败
- `go test ./internal/...` dns-resolver cache/logging/rules 全过
- `go test ./internal/...` geodns 全过
- `go test ./tests/...` geodns 全过
- `go build ./...` geodns / dns-resolver 双包退出码 0
- `lsof -iTCP:15354 -sTCP:LISTEN` 确认 geodns 在 15354 LISTEN（PID 41277）
- `lsof -iTCP:5354 -sTCP:LISTEN` 空，5354 无人监听

### 风险提示

- geodns 心跳仍报 401（`Invalid or missing node token`），是 test-dns.ocerlinkdata.com 老 token 失效，与端口修改**无关**，不阻塞本次修复
- dns-resolver 实际未重启（DoT/DoQ 流量当前为 0，geodns 端口切换未影响现网 DNS 53/443/853 流量）；后续若 geodns 503，需同步重启 dns-resolver 加载新 server.yaml

## 2026-07-06 — Review 生产稳定性修复 + 回归测试报告

### 修复 8 项 P0/P1 问题

| 日期 | 类型 | 描述 | 涉及文件 | 状态 |
|---|---|---|---|---|
| 2026-07-06 | fix | 🔴 #11 缓存版本反向比较 bug：原代码 `current > cached` 时返回 hit，导致 Resolver 永远用旧配置。修复为 `cached >= current` 才返回 hit，3 个子用例（version==current / > current / < current）+ 100 并发 0 portal fetch 场景全过 | dns-resolver/internal/cache/profile_cache.go, dns-resolver/internal/cache/profile_cache_regression_test.go | ok |
| 2026-07-06 | fix | 🔴 #12 singleflight 并发返回值丢失：50 goroutine 并发 DoOnce 必须全部收到非空数据，DoOnce 回调内必须返回所有等待者可见的值。2 个子用例全过 | dns-resolver/internal/cache/profile_cache.go, dns-resolver/internal/cache/profile_cache_singleflight_test.go | ok |
| 2026-07-06 | fix | 🔴 #13 ACK 严格校验 + 命名空间修正：原 `ConfigAckService` 命名空间错放在 `App\Domain\ProfileVersion`，改为 `App\Domain\ConfigVersion` 与目录一致；E2E ACK 处理验证通过 | portal-web/app/Domain/ConfigVersion/ConfigAckService.php | ok |
| 2026-07-06 | fix | 🟠 #14 PublishTask 状态机：`TaskExecution::$fillable` 错把 `config_version` 写成 `profile_version`，导致 ACK 时写入被吞；改为 `config_version` 后状态机 4 个场景（pending 保持 running / 全 applied = succeeded / 全 failed = failed / mixed = partial）全过 | portal-web/app/Models/TaskExecution.php, portal-web/app/Application/Node/ConfigAcknowledgementService.php, portal-web/tests/Feature/PublishTaskStateMachineTest.php | ok |
| 2026-07-06 | fix | 🟠 #15 #16 事务+version 并发安全：`PublishService::recordPublish` 用 `DB::transaction` + `Node::online()->lockForUpdate()` 包裹 ProfileVersion/PublishTask/TaskExecution/Node 写入；version 改用自增主键 id（避免 `max(id)+1` 竞态）。4 个子用例（单调递增 / 关联创建 / 全部已安装节点 desired_config_version 更新 / 失败时 3 表全部回滚）全过 | portal-web/app/Domain/Publish/PublishService.php, portal-web/tests/Feature/PublishServiceTransactionTest.php | ok |
| 2026-07-06 | fix | 🟠 #18 删除接口路径：测试中显式插入 `admin.access / admin.users.write / admin.rules.write / admin.users.read / admin.rules.read` 5 个权限 + super_admin 角色绑定 + `status=active` admin，验证 4 个删除路由（member-rules 单条/批量 + rules/items 单条/批量）全部返回 200 | portal-web/tests/Feature/AdminDeleteRouteTest.php | ok |

### 回归测试结果汇总

| # | 修复点 | 用例数 | 状态 |
|---|---|---|---|
| #11 | 缓存版本反向比较 | 4（含 100 并发） | ✅ PASS |
| #12 | singleflight 并发返回值 | 2（含 50 goroutine） | ✅ PASS |
| #14 | PublishTask 状态机 | 4 | ✅ PASS |
| #15 #16 | 事务+version 并发安全 | 4 | ✅ PASS |
| #18 | 删除接口路径 | 4 | ✅ PASS |
| 合计 | | **16 用例 / 47 断言** | ✅ **全部通过** |

构建验证：

- `go build ./...` dns-resolver 退出码 0
- `php artisan test --filter='...'` 12 passed (43 assertions)
- `go test -v ./internal/cache/...` 11 passed (含 5 个缓存基础测试 + 6 个回归子用例)

## 2026-07-06 — /admin/subscriptions 自动续费改为开关 + payment-flows 文案 + subscription_no 回填 + 过期时间列 tooltip

| 日期 | 类型 | 描述 | 涉及文件 | 状态 |
|---|---|---|---|---|
| 2026-07-06 | code | 后端新增 `POST /admin/finance/subscriptions/{id}/auto-renew` 接口，校验 `auto_renew` 布尔值，同步设置 `cancel_at_period_end`（保持两个字段语义一致：auto_renew=true ⇔ cancel_at_period_end=false） | portal-web/app/Http/Controllers/Api/V1/Admin/AdminFinanceController.php | ok |
| 2026-07-06 | code | 路由注册：使用 `admin.finance.write` 中间件，复用现有权限 | portal-web/routes/v1/admin/billing.php | ok |
| 2026-07-06 | code | 前端"自动续费"列由 `el-tag` 改为 `el-switch` 开关，点击直接调后端接口切换 `auto_renew`；仅 active 状态可切换 | portal-web/web/src/views/admin/Subscriptions.vue | ok |
| 2026-07-06 | code | 移除操作列中重复的"取消续费/恢复续费"按钮（功能已被 switch 覆盖，避免与 auto_renew 语义错位），操作列宽度从 280 收紧到 180 | portal-web/web/src/views/admin/Subscriptions.vue | ok |
| 2026-07-06 | code | 新增 `handleAutoRenewChange(row, val)` 方法，乐观更新本地 row 状态，复用 `operatingId` 防抖 | portal-web/web/src/views/admin/Subscriptions.vue | ok |
| 2026-07-06 | code | payment-flows 列表 status='created' 文案从"已创建"改为"待支付"（三语同步：zh-CN 待支付 / en Awaiting Payment / ko 결제 대기） | portal-web/web/src/locales/{zh-CN,en,ko}.json | ok |
| 2026-07-06 | code | payment-flows 列表 subscription_no 列由 `prop` 绑死改为 slot 模板，值为 null 时显示 `-` 兜底（避免空订阅记录整格空白） | portal-web/web/src/views/admin/PaymentFlows.vue | ok |
| 2026-07-06 | code | 新增 `php artisan subscriptions:backfill-no` 命令，回填 7 条历史 active 订阅缺失的 `subscription_no`（默认 dry-run，`--apply` 真正写入；事务保护 + 唯一索引冲突检测） | portal-web/app/Console/Commands/SubscriptionNoBackfillCommand.php | ok |
| 2026-07-06 | code | admin.finance.expiredAt 文案从"过期时间"改为"实际过期时间"（三语同步：zh-CN 实际过期时间 / en Actual Expired At / ko 실제 만료 시간），明确与 currentPeriodEnd 字段语义差异 | portal-web/web/src/locales/{zh-CN,en,ko}.json | ok |
| 2026-07-06 | code | 新增 admin.finance.currentPeriodEndTip / expiredAtTip 两个 i18n tooltip key（三语同步），Subs.vue 列表列和详情弹窗用 el-tooltip 包裹 label，hover 显示字段语义说明 | portal-web/web/src/locales/{zh-CN,en,ko}.json, portal-web/web/src/views/admin/Subscriptions.vue | ok |
| 2026-07-06 | code | AuditLogs.vue 资源类型列（target_type）由 `prop` 绑死改为 slot 模板，新增 `targetTypeLabel()` 函数 + admin.auditLogs.targetTypesMap i18n key（22 种资源类型，三语同步） | portal-web/web/src/locales/{zh-CN,en,ko}.json, portal-web/web/src/views/admin/AuditLogs.vue | ok |
| 2026-07-06 | code | PublishCenter.vue 状态列由直接显示英文改为 `statusLabel()` 函数调用 + admin.publishCenter.statusMap i18n key（8 种状态值，三语同步：queued/running/succeeded/failed/partial/cancelled/canceled/unknown） | portal-web/web/src/locales/{zh-CN,en,ko}.json, portal-web/web/src/views/admin/PublishCenter.vue | ok |
| 2026-07-06 | code | PaymentFlows.vue 表单新增类型筛选（payment/refund），与后端 GET /admin/finance/payment-flows?type= 同步；filterType 变量在 fetchData/handleReset/handleExport 三处一致处理 | portal-web/web/src/views/admin/PaymentFlows.vue | ok |
| 2026-07-06 | fix | 🔴 Review MUST FIX：补全 `paymentFlows()` 和 `paymentFlowExport()` 后端 type 过滤（type=refund → where status=refunded；type=payment → where status != refunded），修复前端筛选 type 后 total 和分页错乱 bug；同步在 paymentFlowExport validate 数组加 `type` 规则 | portal-web/app/Http/Controllers/Api/V1/Admin/AdminFinanceController.php | ok |
| 2026-07-06 | fix | 🟡 Review CONSIDER：type=payment 过滤由反向定义（`status != 'refunded'`）改为白名单（`status IN ('created','processing','succeeded','failed')`），paymentFlows 和 paymentFlowExport 两处同步；payment(23) + refund(4) = all(27) 等式严格成立 | portal-web/app/Http/Controllers/Api/V1/Admin/AdminFinanceController.php | ok |
| 2026-07-06 | fix | 🟡 Review 风险提示：response 端 type 派生逻辑（paymentFlows / paymentFlowExport 两处）由反向定义（`in_array refunded`）改为白名单（`in_array ['created','processing','succeeded','failed']`），加 `strict=true` 防止类型混淆；派生结果与过滤结果完全一致（payment=23, refund=4, 错误=0） | portal-web/app/Http/Controllers/Api/V1/Admin/AdminFinanceController.php | ok |
| 2026-07-06 | fix | 🔴 Review 路径错 2 处：RuleItems.vue `batch-delete` → `batch-destroy`；BlacklistWhitelist.vue 单条删除 `/admin/member-catalogs/rules/{id}` → `/admin/member-rules/{id}`、批量删除 `/admin/member-catalogs/rules/batch-delete` → `/admin/member-rules/batch-destroy`（与 routes/v1/admin/users.php 实际路由一致，3 处 404 修复） | portal-web/web/src/views/admin/RuleItems.vue, portal-web/web/src/views/admin/BlacklistWhitelist.vue | ok |
| 2026-07-06 | fix | 🟠 Review AutoPublish 失败被吞：UserWorkspaceService::autoPublish() 改返 `array{success,config_version,error}`（替代 `void`），新增 `lastAutoPublishStatus` instance property + `withPublishStatus()` helper；6 个 service method（updateSecurity/updatePrivacy/updateParental/createRule/deleteRule/batchDeleteRules）的 return payload 统一 merge `publish_status` + `publish_error` 字段，调用方可区分"已保存"与"已发布" | portal-web/app/Domain/Profile/UserWorkspaceService.php | ok |
| 2026-07-06 | code | 新增共享 composable `usePublishStatus`（web/src/composables/usePublishStatus.js），4 个用户端 vue 文件（Security.vue/Privacy.vue/ParentalControl.vue/ProfileDetail.vue）共 6 个 API 调用点（PUT user/security, PUT user/privacy, PUT user/parental, POST user/profiles/.../rules, DELETE, PUT, POST batch-delete）后插入 `warnIfPublishFailed(res)`；新增 i18n key `common.publishFailed`（三语同步：已保存，但 DNS 规则未发布到 Resolver） | portal-web/web/src/composables/usePublishStatus.js, portal-web/web/src/locales/{zh-CN,en,ko}.json, portal-web/web/src/views/{Security,Privacy,ParentalControl,ProfileDetail}.vue | ok |

## 2026-07-03 — 写死设备导入后台 + /user/privacy 设备多选改为从后台拉取

| 日期 | 类型 | 描述 | 涉及文件 | 状态 |
|---|---|---|---|---|
| 2026-07-03 | code | 前端 Privacy.vue 删除 8 个写死设备（Windows/苹果/三星/小米/华为/Alexa/Roku/Sonos），改为从 `/user/catalogs` 接口拉取 `member_feature_catalogs.privacy_blocklists[deep_tracking_protection].devices` | portal-web/web/src/views/Privacy.vue | ok |
| 2026-07-03 | code | 弹窗/主页 icon 由 SVG 改用 emoji 字符串；id 字段统一改为 key | portal-web/web/src/views/Privacy.vue | ok |
| 2026-07-03 | code | 后端 MemberCatalogService::defaults() 的 deep_tracking_devices 由 5 个扩展为 8 个：新增原前端写死的 apple/samsung/xiaomi/huawei/alexa/roku/sonos（emoji 图标），并移除 iphone / android / macos / router 共 4 个原有 key，admin 与用户端数据源合一。实测 dns_profiles.privacy_settings.deep_tracking_devices 中无含老 key 的记录，清理 SQL 可跳过 | portal-web/app/Domain/Profile/MemberCatalogService.php | ok |

## 2026-07-03 — /admin/rules 类型字段 i18n 兜底加固

| 日期 | 类型 | 描述 | 涉及文件 | 状态 |
|---|---|---|---|---|
| 2026-07-03 | code | Rules.vue 类型列新增 `$te()` 兜底：i18n 命中走翻译，未命中回退显示原 type 字符串，避免显示 i18n key path | portal-web/web/src/views/admin/Rules.vue | ok |
| 2026-07-03 | code | 新增 admin.ruleLibrary.ruleType.ruleTypeFallback i18n key（zh-CN/en/ko） | portal-web/web/src/locales/{zh-CN,en,ko}.json | ok |

## 2026-07-03 — MemberCatalogService 全量数据库化（去掉代码兜底）

| 日期 | 类型 | 描述 | 涉及文件 | 状态 |
|---|---|---|---|---|
| 2026-07-03 | code | MemberCatalogService::get() 移除 `defaults()` 兜底，改为纯 DB 读取；未配置返回 4 个空数组；defaults() 改为 public 仅供 Seeder 调用；删除合并方法 mergeSystemDefaults | portal-web/app/Domain/Profile/MemberCatalogService.php | ok |
| 2026-07-03 | code | 新增 MemberFeatureCatalogSeeder，写入 13 device_models / 3 privacy_blocklists（含 8 deep_tracking devices）/ 3 parental_presets 到 dns_system_configs 表。新环境必须跑 `php artisan db:seed --class=MemberFeatureCatalogSeeder` | portal-web/database/seeders/MemberFeatureCatalogSeeder.php | ok |

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
| D4 | 数据库合并:`resolver_nodes` / `resolver_node_tokens` / `resolver_node_heartbeats` / `profile_versions` / `publish_tasks` / `task_executions` / `query_log_ingest_batches` / `geo_dns_mappings` / `rule_sources` / `system_config` / `admin_audit_logs` 全部并入 `portal-web` 现有 MySQL `dns_` 前缀库 | `02-MODULES.md` §1.4 数据所有权矩阵;迁移文件合并至 `portal-web/database/migrations/` |
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

## 2026-07-06 — 后台界面 i18n 补齐 + 按钮 disabled 修复

| 日期 | 类型 | 描述 | 涉及文件 | 状态 |
|---|---|---|---|---|
| 2026-07-06 | code | zh-CN.json 补齐 admin 段 12 个缺失 key：alertsPage.code / protectionPolicies.{advanced,contentFiltering,rebindHint} / queryLogsPage.{type,protocol,node,client} / ruleLibrary.ruleType.{adblock,domain_list,hosts,rpz} | portal-web/web/src/locales/zh-CN.json | ok |
| 2026-07-06 | code | en.json 补齐 admin 段 10 个缺失 key：alertsPage.code / protectionPolicies.{advanced,contentFiltering,rebindHint} / regionManage.emptyHint / systemConfig.{paymentMethodsLabel,paymentMethods.{card,wechat_pay,alipay},paymentHint} | portal-web/web/src/locales/en.json | ok |
| 2026-07-06 | code | ko.json 补齐 admin 段 32 个缺失 key：alertsPage.code / geoDns.{schedulerAlias,schedulerAliasPlaceholder} / profilePublish 整段(16 keys) / protectionPolicies.{advanced,contentFiltering,rebindHint} / regionManage.emptyHint / rules.desc / systemConfig.{paymentMethodsLabel,paymentMethods.{card,wechat_pay,alipay},paymentHint} / teams.{membersDrawerTitle,memberName,memberEmail,memberRole,memberJoinedAt} | portal-web/web/src/locales/ko.json | ok |
| 2026-07-06 | code | RegionManage.vue 调用的 key 由 `admin.regions.emptyHint` 修正为 `admin.regionManage.emptyHint`，与 zh-CN/en/ko 一致 | portal-web/web/src/views/admin/RegionManage.vue | ok |
| 2026-07-06 | test | vite build 通过（1794 modules transformed, 3.46s）；扫描脚本 `admin.*` 共 487 个 key 三语 0 缺失 | portal-web/web | ok |
| 2026-07-06 | docs | 同步本变更日志 | project-doc/07-CHANGE-LOG.md | ok |

## 2026-07-06 — Resolver 缓存/singleflight/ACK + PublishTask 状态/事务 + 接口路径

| 日期 | 类型 | 描述 | 涉及文件 | 状态 |
|---|---|---|---|---|
| 2026-07-06 | code | **#11 修复**：GetFromMemoryWithVersionCheck / GetFromDiskWithVersionCheck 中 `version <= currentVersion` 反向判断改为 `version < currentVersion`，仅当缓存严格比本地旧时（异常态）才删除缓存并强制回源；避免每次 DNS 查询都回源 portal-web | dns-resolver/internal/cache/profile_cache.go | ok |
| 2026-07-06 | code | **#11 修复**：FetchProfile 缓存命中但 version==currentVersion 时直接 return nil，跳过 loadProfileIntoEngine 重复加载 | dns-resolver/internal/agent/agent.go | ok |
| 2026-07-06 | code | **#12 修复**：FetchProfile 中 `DoOnce` 改用返回值 `(data, fetchVersion, err)` 替代闭包变量 `rawData/fetchVersion`，并新增 `SetToMemory` 同步写入避免并发等待 goroutine 读到 nil；解决 singleflight 并发 Bug | dns-resolver/internal/agent/agent.go | ok |
| 2026-07-06 | code | **#13 修复**：buffer.go 解析 ACK 必须 accepted=true 才视为成功，否则 return error 走本地磁盘 buffer；并兼容 `{data:{accepted:true,...}}` 包装结构 | dns-resolver/internal/logging/buffer.go | ok |
| 2026-07-06 | code | **#14 修复**：ConfigAcknowledgementService 增加 pending_count 计算；状态机按 target/applied/failed/pending 决定：pending>0→running, all-acked+无失败→succeeded, all-acked+有失败→partial, 全失败→failed；completed_at 仅在 all-acked 时置 | portal-web/app/Application/Node/ConfigAcknowledgementService.php | ok |
| 2026-07-06 | code | **#15 修复**：PublishService.version 改为等于 ProfileVersion.id（数据库自增），避免 max(id)+1 竞态；并发安全 | portal-web/app/Domain/Publish/PublishService.php | ok |
| 2026-07-06 | code | **#16 修复**：PublishService 整体包入 `DB::transaction`，保证 ProfileVersion/PublishTask/TaskExecution/Node 写入原子性；Node 查询加 `lockForUpdate` 避免 ack 流程并发修改 | portal-web/app/Domain/Publish/PublishService.php | ok |
| 2026-07-06 | code | **#18 修复**：BlacklistWhitelist.vue 单条删除路径由 `/admin/member-catalogs/rules/{id}` 修正为 `/admin/member-rules/{id}`（与 routes/v1/admin/users.php 一致） | portal-web/web/src/views/admin/BlacklistWhitelist.vue | ok |
| 2026-07-06 | test | go build ./... 退出码 0；4 个 PHP 文件 php -l 全部无语法错误；npm run build 通过（3.92s）；php artisan route:list 确认 /admin/member-rules/batch-destroy 等路由注册 | dns-resolver + portal-web | ok |
| 2026-07-06 | docs | 同步本变更日志 | project-doc/07-CHANGE-LOG.md | ok |

> **注 #17**：经核实 `/admin/blacklist-whitelist` 路由对应独立 `AdminBlacklistWhitelistController::index`，该接口接收的 query 字段正是 `type/keyword/per_page`，与前端 `BlacklistWhitelist.vue` 调用完全一致（`type: filter.type, keyword: filter.keyword, page, per_page`）。老大手稿中提到的"前端传 type/keyword 后端收 list_type/domain"对应的是 `/admin/member-rules` 接口（由 `AdminMemberCatalogController::rules()` 处理，接收 `list_type/domain/profile_id`），但该接口前端未调用，本次无需修改。

## 2026-07-09 — AI 开发规范补齐：新增 2 个提示词 + 修正文档同步流程 + 修复旧文档引用

### 1. 新增 `prompts/frontend-review.md`（前端审查执行 wrapper）

| 日期 | 类型 | 描述 | 涉及文件 | 涉及文档 | 状态 |
|---|---|---|---|---|---|
| 2026-07-09 | docs | 新建 `prompts/frontend-review.md`：前端审查执行入口，**不复制 `frontend-ui.md` 的 20 章审查维度**，只提供"必读 → 审查计划 → 证据输出 → 自检"的强制流程，并明确与 `frontend-ui.md`、`prompts/review.md`、`prompts/e2e-closure-check.md` 的职责边界 | ai-doc/prompts/frontend-review.md (新) | — | ok |

### 2. 新建 `prompts/e2e-closure-check.md`（前后端闭环检查，无现有覆盖）

| 日期 | 类型 | 描述 | 涉及文件 | 涉及文档 | 状态 |
|---|---|---|---|---|---|
| 2026-07-09 | docs | 新建 `prompts/e2e-closure-check.md`：12 项闭环检查清单（页面入口→路由→API 方法→真实联通→后端路由→中间件鉴权→Controller→Service→数据库字段→响应结构→状态处理→测试），与 `feature-start.md` 流程互补；不重复单端审查内容，通过引用 `frontend-review.md` 与 `review.md` 保持边界清晰 | ai-doc/prompts/e2e-closure-check.md (新) | — | ok |

### 3. 修正 `prompts/feature-start.md`：文档同步从直写改为"AI 提议 + 人工确认"

| 日期 | 类型 | 描述 | 涉及文件 | 涉及文档 | 状态 |
|---|---|---|---|---|---|
| 2026-07-09 | docs | 修复"阶段 4：同步更新文档"流程：由"AI 直写"改为三步走（4a 输出文档影响评估 diff → 4b 人工确认 → 4c 确认后同步 + 记录 07-CHANGE-LOG.md），并修 5 处旧文档引用（`05-delivery-criteria.md` → `08-DELIVERY-CRITERIA.md`、`03-plans.md` → `05-PLANS.md`、`04-change-log.md` → `07-CHANGE-LOG.md`） | ai-doc/prompts/feature-start.md | project-doc/08-DELIVERY-CRITERIA.md, 05-PLANS.md, 07-CHANGE-LOG.md | ok |

### 4. 修复 `rules/coding.md` 旧文档引用

| 日期 | 类型 | 描述 | 涉及文件 | 涉及文档 | 状态 |
|---|---|---|---|---|---|
| 2026-07-09 | docs | 将编码规范中调试章节引用的 `04-change-log.md` 改为 `07-CHANGE-LOG.md`（2 处），与实际文件名对齐 | ai-doc/rules/coding.md | — | ok |

### 修改后整体"8 方向"满足度

| 方向 | 补齐前 | 补齐后 |
|---|---|---|
| 1. AI 规范包结构 | ✅ | ✅ |
| 2. 先读文档再动代码 | ✅ | ✅ |
| 3. 任务拆小、边界清晰 | ✅ | ✅ |
| 4. 开发→审查→验收→同步流程 | ⚠️ | ✅（e2e-closure-check.md + feature-start.md 人工确认门） |
| 5. 文档同步"AI 提议 + 人工确认" | ⚠️ | ✅（feature-start.md 阶段 4 三步走） |
| 6. 专用审查提示词（前端/后端/闭环） | ⚠️ | ✅（前端审查 wrapper + e2e-closure-check.md） |
| 7. 输出证据不只给结论 | ✅ | ✅ |
| 8. 验收清单 | ✅ | ✅ |
