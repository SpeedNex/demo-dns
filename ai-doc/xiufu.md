## Code Review Report — 认证/安全优先版

⚠️ **Needs Verification**\
压缩包内未找到 `rules/checklist.md`、`rules/coding.md`、`project-doc/adr/*`，也没有本次 PR/MR Diff。以下报告基于当前上传的 `portal-web(3)` + `dns-resolver(5)` 全量源码审查。

***

## 🔴 MUST FIX

文件

位置

问题

影响

修复建议

`portal-web/routes/v1/public.php`

`GET /api/v1/build/{path}`

公共文件下载接口直接 `base_path('public/build/' . $path)`，没有校验真实路径是否仍在 `public/build` 内。

存在路径穿越风险，可能读取 `.env`、配置文件、源码。

使用 `realpath()`，并强制最终路径必须以 `realpath(public_path('build'))` 开头；禁止 `..`；最好改成白名单文件名。

`portal-web/.env`

根目录

上传包包含真实 `.env`，含 `APP_KEY`、DB 密码、shared token、`STRIPE_FAKE=true`。

密钥泄露；如果被部署到生产，支付可能进入 fake 模式。

删除 `.env`，只保留 `.env.example`；轮换 APP\_KEY、DB 密码、shared token；生产启动时禁止 `STRIPE_FAKE=true`。

`dns-resolver/configs/server*.yaml`

`configs/server.yaml`、`server-test.yaml`、`server-node2.yaml`

Resolver 配置文件里包含明文 `api_key` / 旧 token。

节点凭据泄露后，可伪造 Resolver 上报心跳、拉配置、上报日志。

配置文件不得提交真实凭据；删除这些文件或改为 example；轮换已暴露节点 token/api\_key。

`portal-web/app/Domain/Auth/NodeTokenService.php`

`resolveByToken()`

Node token 鉴权只检查 `token_hash` 和 `revoked_at`，没有检查 `expires_at` / `status`。

过期 token 仍可注册节点、访问 `geodns/health-view` 等 token 鉴权接口。

`resolveByToken()` 必须增加：`status='active'`、`expires_at is null or > now()`。

`portal-web/app/Http/Controllers/Api/V1/Node/QueryLogController.php`

`batch()`

`batch_id` 只校验不去重，`event_id` 每次服务端随机生成。

Resolver 重试会重复写 ClickHouse，导致日志、用量、计费重复。

使用 Redis `SETNX querylog:batch:{batch_id}` 或 ClickHouse 幂等键；`event_id` 应由 resolver 基于 batch\_id + index 生成稳定值。

`dns-resolver/go.mod`

`go 1.25.0`

当前 Go 版本要求过高，构建会尝试下载 Go 1.25 toolchain。

CI/CD、离线生产环境、受限服务器可能构建失败。

改成实际支持版本，如 `go 1.22` / `go 1.24`；或者固定构建镜像并明确要求。

***

## 🟠 SHOULD FIX

文件

位置

问题

影响

修复建议

`portal-web/app/Models/Node.php`

`isOnline()`

Redis 正常但 key 不存在时直接返回离线，不再看 MySQL。这个逻辑适合实时状态，但会让短暂 Redis key 丢失直接判离线。

Redis 短暂抖动或 TTL 配置错误会导致节点在线状态误判。

可以接受，但建议把逻辑移到 `NodeStatusService`，并支持“strict realtime”和“fallback tolerant”两种模式。

`portal-web/app/Http/Controllers/Api/V1/Node/HeartbeatController.php`

`nodes:online`

Redis Set 成员没有独立 TTL，只给整个 Set 设置 TTL。只要有任意节点持续心跳，旧成员可能残留。

在线节点统计偏高，离线节点可能仍被统计为在线。

不要用 Set 作为在线真相；用 `node:{id}:heartbeat` TTL 判断，或用 Sorted Set 存 timestamp 并按时间清理。

`portal-web/app/Http/Controllers/Api/V1/Node/TokenVerifyController.php`

`verify()`

公共接口输入 token 后会返回 `api_key` 和 `secret`。虽然需要知道 token，但这个接口相当于 token introspection。

token 一旦泄露，攻击者可重复换取节点凭据；secret 也会被重复返回。

如果已采用 register 签发 api\_key，建议废弃该接口；或仅允许一次性换取，成功后立即吊销安装 token。

`portal-web/app/Http/Controllers/Api/V1/Node/GeoDnsRegisterController.php`

`register()`

GeoDNS 的 `api_key` 复用 node token 明文。

安装 token 和业务 api\_key 变成同一凭据，吊销、轮换、审计边界不清晰。

GeoDNS register 应像 resolver register 一样签发独立 `ak_` api\_key。

`portal-web/app/Http/Controllers/Api/V1/Node/QueryLogController.php`

`resolveDevice()`

自动设备创建只写 `ip_hash`，不写 `source_ip`，但迁移说明和配置构建逻辑依赖 `source_ip`。

依赖 IP 识别设备/Profile 的链路可能不生效。

明确是否允许保存明文 IP；如果允许，写入 `source_ip`；如果不允许，配置构建也应改为 hash 匹配。

`portal-web/app/Infrastructure/ClickHouse/ClickHouseClient.php`

`sendRaw()`

ClickHouse 连接固定使用 HTTP。

内网可以接受；跨公网部署时会泄露查询数据和 Basic Auth。

增加 `scheme=https` 配置，生产环境强制 HTTPS 或仅允许内网地址。

`portal-web/app/Domain/Auth/AuthService.php`

`register()`

创建用户、默认 Profile、Free Subscription 不在同一事务中；订阅创建失败只记录 warning。

用户可能注册成功但无订阅，后续 quota/billing 逻辑异常。

注册核心资源建议放事务；至少用户 + 默认 Profile + Free Subscription 要么全部成功，要么返回明确错误。

`portal-web/app/Domain/Team/TeamService.php`

`invite()` / `acceptInvite()`

邀请 token 使用 `Hash::make`，只能遍历当前邮箱所有邀请并逐个 `Hash::check`。

邀请数量上来后性能差；并发接受可能重复加入。

使用 `sha256` token\_hash 精确查询；接受邀请时加事务锁或唯一约束兜底。

`dns-resolver/internal/logging/buffer.go`

`sendBatch()` 日志

失败日志会输出请求体片段，包含域名、client\_ip、profile\_id、device\_id。

生产日志泄露用户 DNS 查询隐私。

只记录 batch\_id、数量、状态码；不要打印查询明细。

***

## 🟡 CONSIDER

文件

位置

建议

收益

`portal-web/app/Models/Node.php`

`isOnline()`

把 Redis/MySQL 在线判断移到 `Domain/Node/NodeStatusService`。

避免 Model 依赖 Redis，后续健康分、降级、GeoDNS 调度更好扩展。

`portal-web/routes/v1/node.php`

node token / api\_key

统一术语：安装 token 只用于首次注册，业务接口只用 api\_key。

降低认证模型混乱。

`portal-web/app/Http/Controllers/Api/V1/Node/HeartbeatController.php`

MySQL 心跳历史

MySQL 只保存 `last_heartbeat_at`，历史心跳后续放 ClickHouse/时序库。

避免节点规模增长后 MySQL 写压力扩大。

`portal-web/app/Domain/Auth/PermissionService.php`

User 权限

目前会员侧 `hasPermission(User)` 永远 true，依赖业务 Service 自行做归属校验。

可以接受，但团队/工作区权限建议后续统一到 Workspace Permission Service。

`portal-web/routes/v1/public.php`

build 下载

安装包下载建议独立到 `DownloadController`，带版本、文件白名单、hash 校验。

更适合生产发布和审计。

***

## ⚪ NIT

文件

位置

问题

`dns-resolver/internal/agent/agent.go`

注释

注释仍写 APIKey/Secret，但代码已经是 Bearer token。

`portal-web/README.md`

数据流描述

仍有旧表/旧流程描述，和当前 ClickHouse 直写不完全一致。

`portal-web/app/Http/Controllers/Api/V1/Node/HeartbeatController.php`

`$device`

变量赋值后未使用。

`portal-web/app/Http/Middleware/VerifyRequestSignature.php`

文件名

名字像 HMAC 签名校验，但实际只是 Bearer token。建议改名避免误导。

***

## 认证检查结果

### 用户认证

检查项

结果

用户接口是否需要登录

✅ `routes/v1/user.php` 使用 `auth:api` + `user.only`

管理员接口是否隔离

✅ `routes/v1/admin.php` 使用 `auth:sanctum` + `admin.only`

用户/管理员 Token 是否隔离

✅ `UserOnly` 和 `AdminOnly` 做了二次模型校验

登录限流

✅ 用户登录、管理员登录都有 `throttle:10,1`

用户资源归属

✅ Profile / Subscription / PaymentTransaction 主要接口有 user\_id 校验

发现明显 IDOR

未发现明显用户侧 Profile / Subscription IDOR

### 节点认证

检查项

结果

业务接口是否需要 api\_key

✅ `node.api_key` 中间件保护 heartbeat/config/query-logs

api\_key 是否 hash 存储

✅ 使用 `sha256` hash 查找

安装 token 是否可过期

❌ 中间件 `resolveByToken()` 未检查 `expires_at/status`

token / api\_key 边界是否清晰

⚠️ Resolver register 会签发新 `ak_`，但 GeoDNS 复用安装 token

TokenVerify 是否安全

⚠️ 可重复换取 token 对应 secret/api\_key，建议废弃或一次性

***

## ✅ 已检查且未发现明显问题

- 用户登录密码使用 `Hash::check`。
- 用户注册密码使用 Laravel `Hash::make`。
- 用户和管理员路由有基本隔离。
- 管理后台有 `admin.access` 总入口权限。
- 多数用户资源读取/修改按 `user_id` 过滤。
- Stripe Webhook 在 production 环境要求签名。
- Resolver 运行时从 `api_key_path` 读取凭据，避免继续依赖 yaml 中旧字段。
- Node API 日志只记录 token prefix，没有完整打印 Authorization。

***

## 📊 Review Summary

MUST FIX: 6\
SHOULD FIX: 9\
CONSIDER: 5\
NIT: 4

***

## Merge Decision

❌ **REJECT**

当前版本不建议合并到生产分支。核心阻断原因：

1. 公共 build 下载接口存在路径穿越风险。
2. `.env` 和 resolver 配置中存在明文密钥/凭据。
3. Node token 认证未检查过期时间。
4. 查询日志上报没有幂等，会导致用量/计费重复。
5. Go 版本要求可能导致 CI/生产构建失败。

修完 **MUST FIX** 后，可以进入下一轮联调审查。
