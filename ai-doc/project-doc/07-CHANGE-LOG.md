# 变更日志（Bug / Feature Log）

> 记录每次功能增减、Bug 修复、文档变更。没有构建、测试、部署证据时，状态只能写"文档已定义"或"代码草案"。

## 2026-07-09 — 全量自审修复（P0/P1/P2/P3）

| 日期 | 类型 | 优先级 | 描述 | 涉及文件 | 状态 |
|---|---|---|---|---|---|
| 2026-07-09 | fix | P1-6 | 修复 loadSigningSecret 未定义导致的编译错误：在 main.go 添加 loadSigningSecret 函数，从 api_key_path 同目录读取 signing_secret 文件 | dns-resolver/cmd/dns-resolver/main.go | ok |
| 2026-07-09 | fix | P1-7 | 修复 DoH 403 用户枚举：profile 验证失败时返回 NXDOMAIN 而非 403，防止攻击者通过响应差异枚举有效 profile ID | dns-resolver/internal/doh/server.go | ok |
| 2026-07-09 | fix | P2-9 | 修复 SingleFlight 缺少超时：DoOnce 改用 DoChan + 10s context timeout，防止回源阻塞导致所有等待 goroutine 永久挂起 | dns-resolver/internal/cache/profile_cache.go | ok |
| 2026-07-09 | fix | P2-10 | 修复日志循环引用/溢出：Append 方法添加字段截断（domain≤255, reason/category≤128, deviceUID≤64, deviceType≤32） | dns-resolver/internal/logging/buffer.go | ok |
| 2026-07-09 | fix | P3-12 | ClickHouse reason 字段添加索引：dns_logs 表新增 idx_reason set(100) 索引，加速按拦截原因查询 | portal-web/app/Console/Commands/ClickHouseSetupCommand.php | ok |
| 2026-07-09 | feat | P3-13 | 添加基础监控指标：log_flush_failed（日志刷新失败计数）和 quota_block_hits（配额 blocklist 命中计数），通过 Prometheus /metrics 端点暴露 | dns-resolver/internal/metrics/metrics.go, dns-resolver/internal/logging/buffer.go, dns-resolver/internal/doh/server.go, dns-resolver/internal/resolver/handler.go, dns-resolver/cmd/dns-resolver/main.go | ok |

## 2026-07-09 — UI 修复：刷新时 profile 闪烁 + Logs 页无 loading

| 日期 | 类型 | 描述 | 涉及文件 | 状态 |
|---|---|---|---|---|
| 2026-07-09 | fix | 修复 F5 刷新时右上角 profile 切换器短暂显示"默认配置"的问题：将 currentProfileName 缓存到 localStorage，computed 优先读缓存回退；loadProfiles / switchProfile / handleCreateProfile 三处同步写缓存 | portal-web/web/src/components/Layout.vue | ok |
| 2026-07-09 | fix | 修复 /user/e6ac2c/logs 页切换筛选/分页时数据直接替换无过渡的问题：el-card 加 v-loading 指令，fetchLogs 请求周期内显示 loading 遮罩 | portal-web/web/src/views/Logs.vue | ok |

## 2026-07-09 — /admin/users 密码最小长度从 8 位调整为 6 位

| 日期 | 类型 | 描述 | 涉及文件 | 状态 |
|---|---|---|---|---|
| 2026-07-09 | fix | 后台用户管理 /admin/users 密码最小长度从 8 位调整为 6 位，支持 6 位纯数字密码：AdminUserController store/update 验证规则改为 min:6 | portal-web/app/Http/Controllers/Api/V1/Admin/AdminUserController.php | ok |
| 2026-07-09 | fix | 后台用户管理 /admin/users 前端密码验证同步调整为 min:6 | portal-web/web/src/views/admin/Users.vue | ok |
| 2026-07-09 | docs | i18n 三语（zh-CN/en/ko）passwordMinLength 提示文案从 8 位更新为 6 位 | portal-web/web/src/locales/zh-CN.json, en.json, ko.json | ok |

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
|---|---|---|---|---|---|
| 2026-07-09 | docs | 修复 6 处残留 dns-console-web 引用→portal-web(原 console 域) | 10-NEXTDNS-LITE-BILLING.md, openapi.yaml, billing.schema.json, billing-usage-batch.sample.json, rules/coding.md, clickhouse/tables.md | — | ok |

### 2. data-schema.md 全量重写（对齐 65 个迁移文件）

| 日期 | 类型 | 描述 | 涉及文件 | 状态 |
|---|---|---|---|---|
| 2026-07-09 | docs | 重写 data-schema.md：对齐 65 个迁移文件实际结构，补充 37 个缺失字段/索引/外键 | ai-doc/specs/data-schema.md | ok |

## 2026-07-09 — 前端 UI 全量审查

| 日期 | 类型 | 描述 | 涉及文件 | 状态 |
|---|---|---|---|---|
| 2026-07-09 | review | 前端 UI 全量审查：扫描 portal-web/web/src/ 全部 Vue 文件，发现 79 个问题（P0:17, P1:41, P2:21），包括多语言硬编码 30 处、权限控制缺失、异常处理不完整、UI 不一致等 | ai-doc/project-doc/12-FRONTEND-UI-REVIEW.md | ok |
