# portal-web API 规格

> `portal-web` 是统一门户，包含官网、会员控制台和后台管理。它管理用户业务和 Profile 配置，但不直接管理 resolver 节点进程，不参与 DNS 实时查询。

## 1. API 分组

| 分组 | 前缀 | 鉴权 | 用途 |
|---|---|---|---|
| Public API | `/api/v1/public/*` | 无 / 登录前 | 官网、套餐展示、注册、登录、Admin 登录 |
| Member API | `/api/v1/member/*` | 用户登录 | Profile、规则、设备、日志、统计、账单、团队 |
| Admin API(Sanctum) | `/api/v1/admin/*`(子组) | Sanctum + `permission:admin.access` | 用户、设备、告警、团队、审计日志(总后台查看类) |
| Admin API(Shared Token) | `/api/v1/admin/*`(子组) | `shared.token:admin` | 节点预签发 / 凭据、发布任务、GeoDNS 映射、规则库、系统配置、计费、查询日志后台检索(原 console 域运维面) |
| Agent API | `/api/v1/agent/*` | `node.hmac`(Bearer + HMAC) | resolver 心跳、拉配置、ACK、查询日志批量 |
| Internal API | `/api/v1/internal/*` | `shared.token:internal` | 跨进程调用:发布配置、健康视图、查询日志/统计回读 |
| Member 域 → 原 console 域 | 进程内 Service 调用 | 同 Laravel 容器 | ProfilePublishService / BillingUsageService / NodeHealthViewService 等 |

## 当前实现映射（2026-06-12）

- 当前开发目录：`ocer-dns/portal-web`
- 已生成并扩展 `routes/api.php` 中的 Public / Member 路由草案
- 已实现的领域草案：
  - `app/Domain/Auth/AuthService.php`
  - `app/Domain/Profile/DomainNormalizer.php`
  - `app/Domain/Profile/MemberCenterService.php`
  - `app/Domain/Profile/ProfileConfigBuilder.php`
  - `app/Domain/Profile/ProfilePublishService.php`
  - `app/Domain/Profile/ProfileService.php`
  - `app/Domain/Rule/ProfileRuleService.php`
  - `app/Domain/Rule/RuleService.php`
- 已接入服务层的控制器草案：
  - `AuthController`
  - `ProfileController`
  - `ProfileRuleController`
  - `ProfilePublishController`
  - `MemberCenterController`
- 当前仍缺：
  - 真实 Laravel 请求校验
  - 真实认证与 token 持久化
  - Repository / Eloquent / 数据库存取
  - Internal API HMAC 调用

## 2. 通用响应

成功响应：

```json
{
  "data": {},
  "meta": {
    "trace_id": "trace_01H..."
  }
}
```

分页响应：

```json
{
  "data": [],
  "meta": {
    "page": 1,
    "per_page": 20,
    "total": 100,
    "trace_id": "trace_01H..."
  }
}
```

错误响应：

```json
{
  "error": {
    "code": "VALIDATION_FAILED",
    "message": "The given data was invalid.",
    "details": {}
  },
  "trace_id": "trace_01H..."
}
```

## 3. Public API

### 3.1 注册

```http
POST /api/v1/public/auth/register
```

请求：

```json
{
  "name": "Alice",
  "email": "alice@example.com",
  "password": "secret-password",
  "password_confirmation": "secret-password",
  "timezone": "Asia/Seoul",
  "locale": "zh-CN"
}
```

响应：

```json
{
  "data": {
    "user": {
      "id": "usr_01H...",
      "name": "Alice",
      "email": "alice@example.com"
    },
    "token": "plain_text_token_only_once"
  }
}
```

错误：`409 EMAIL_ALREADY_EXISTS`、`422 VALIDATION_FAILED`。

### 3.2 登录

```http
POST /api/v1/public/auth/login
```

请求：

```json
{
  "email": "alice@example.com",
  "password": "secret-password",
  "device_name": "Chrome on macOS"
}
```

响应：

```json
{
  "data": {
    "token": "plain_text_token_only_once",
    "user": {
      "id": "usr_01H...",
      "email": "alice@example.com",
      "role": "member"
    }
  }
}
```

错误：`401 INVALID_CREDENTIALS`、`403 USER_DISABLED`、`429 TOO_MANY_ATTEMPTS`。

### 3.3 套餐展示

```http
GET /api/v1/public/plans
```

响应字段：`id`、`code`、`name`、`billing_model`、`query_limit_monthly`、`profile_limit`、`device_limit`、`log_retention_days`、`prices`、`features`。

V1 套餐语义：Free `query_limit_monthly=300000`；Pro/Business/Education `query_limit_monthly=null`（不受限额约束）。设备和配置/Profile 默认 unlimited，用 null 表示。Free 超额后 resolver 在 DNS 协议层硬拒绝返回 SERVFAIL，不存在 classic_dns 降级模式。

> 财务字段不得返回或保存浮点价格。`unit_amount_minor=199,currency=USD` 表示 USD 1.99；显示层负责按币种 `minor_unit` 格式化。价格事实以 `plan_prices` 为准。

## 4. Member API

### 4.1 当前用户

```http
GET /api/v1/member/me
```

响应：

```json
{
  "data": {
    "id": "usr_01H...",
    "name": "Alice",
    "email": "alice@example.com",
    "timezone": "Asia/Seoul",
    "locale": "zh-CN",
    "plan_code": "free"
  }
}
```



### 4.1A 会员中心总览与导航

```http
GET /api/v1/member/overview
```

响应必须聚合当前用户的会员中心状态，供前端首页和左侧导航使用：

```json
{
  "data": {
    "navigation": ["security", "privacy", "parental", "denylist", "allowlist", "analytics", "logs", "settings", "membership"],
    "current_profile_id": "prf_01H...",
    "plan_code": "free",
    "monthly_query_limit": 300000,
    "used_query_count": 12345,
    "quota_status": "normal",
    "security_enabled": true,
    "privacy_enabled": true,
    "parental_enabled": false
  }
}
```

V1 前端必须显示以下入口：安全、隐私、家长监护、黑名单、白名单、统计、日志、设置、会员中心。

### 4.1B Dashboard / 统计首页

```http
GET /api/v1/member/dashboard?profile_id=prf_01H...&range=24h
```

响应字段：`query_count`、`blocked_count`、`block_rate`、`top_domains`、`top_blocked_domains`、`quota`、`active_devices`。统计数据来自 ClickHouse 和 `usage_counters`，不得从 MySQL 高频日志表读取。

### 4.2 Profile 列表

```http
GET /api/v1/member/profiles?page=1&per_page=20
```

响应 item：

```json
{
  "id": "prf_01H...",
  "name": "Home",
  "status": "active",
  "current_version": 3,
  "last_published_at": "2026-06-12T00:00:00Z",
  "device_count": 2,
  "today_query_count": 1234,
  "today_blocked_count": 120,
  "created_at": "2026-06-12T00:00:00Z"
}
```

### 4.3 创建 Profile

```http
POST /api/v1/member/profiles
```

请求：

```json
{
  "name": "Home",
  "description": "Family profile",
  "default_action": "allow",
  "block_response": "nxdomain",
  "timezone": "Asia/Seoul"
}
```

响应：`201 Created`，返回 Profile。

错误：`422 VALIDATION_FAILED`、`409 PROFILE_LIMIT_EXCEEDED`。

### 4.4 查看 Profile

```http
GET /api/v1/member/profiles/{profile_id}
```

必须校验 Profile 属于当前用户或当前团队。

### 4.5 更新 Profile

```http
PUT /api/v1/member/profiles/{profile_id}
```

请求字段：`name`、`description`、`default_action`、`block_response`、`security_enabled`、`privacy_enabled`、`adblock_enabled`、`parental_enabled`、`safe_search_enabled`、`log_mode`。

更新后只改变草案，不自动发布到 resolver。

### 4.6 删除 Profile

```http
DELETE /api/v1/member/profiles/{profile_id}
```

要求：

- 软删除 Profile。
- 创建删除发布任务，通知 console 让 resolver 移除该 Profile。
- 删除后 Profile ID 不复用。



### 4.6A 安全设置

```http
GET /api/v1/member/security
PUT /api/v1/member/security
```

> 当前实现采用扁平路径(`/api/v1/member/security`)+ 当前用户默认 Profile 上下文(原 §4.6A 文档要求 `/profiles/{profile_id}/security` 嵌套路径);若后续需要多 Profile 切换,可加 `?profile_id=` query 参数。后续 V2 可扩展为嵌套路径。

PUT 请求：

```json
{
  "enabled": true,
  "block_malware": true,
  "block_phishing": true,
  "block_command_and_control": true,
  "block_cryptojacking": true
}
```

更新后只保存草案，必须发布 Profile 后才进入 resolver config。

### 4.6B 隐私设置

```http
GET /api/v1/member/privacy
PUT /api/v1/member/privacy
```

> 当前实现采用扁平路径(`/api/v1/member/privacy`)+ 当前用户默认 Profile 上下文,语义同 §4.6A。

PUT 请求：

```json
{
  "enabled": true,
  "block_trackers": true,
  "block_analytics": true,
  "block_telemetry": true,
  "anonymize_client_ip": true,
  "log_mode": "full"
}
```

`log_mode` 只允许 `full`、`blocked_only`、`disabled`。即使 `disabled`，系统仍可保留最小化聚合 query_count 以支持 Free 额度判断，但不得向用户展示详细域名日志。

### 4.6C 家长监护 Lite

```http
GET /api/v1/member/parental
PUT /api/v1/member/parental
```

> 当前实现采用扁平路径(`/api/v1/member/parental`)+ 当前用户默认 Profile 上下文,语义同 §4.6A。

PUT 请求：

```json
{
  "enabled": true,
  "block_adult_content": true,
  "safe_search": true,
  "youtube_restricted_mode": true,
  "block_gambling_basic": false
}
```

V1 不实现时间段上网控制、App 使用时长、孩子端 App 和家长审批流。

### 4.7 Profile 规则列表

```http
GET /api/v1/member/profiles/{profile_id}/rules?type=deny&keyword=ads&page=1
```

响应 item：

```json
{
  "id": "rule_01H...",
  "profile_id": "prf_01H...",
  "list_type": "deny",
  "match_type": "exact",
  "domain": "ads.example.com",
  "normalized_domain": "ads.example.com",
  "action": "block",
  "enabled": true,
  "created_at": "2026-06-12T00:00:00Z"
}
```

### 4.8 创建规则

```http
POST /api/v1/member/profiles/{profile_id}/rules
```

请求：

```json
{
  "list_type": "deny",
  "match_type": "exact",
  "domain": "ads.example.com",
  "action": "block",
  "category": "custom",
  "note": "block ads"
}
```

规则校验：

| 字段 | 约束 |
|---|---|
| `list_type` | `allow` / `deny` |
| `match_type` | `exact` / `suffix` / `wildcard` |
| `domain` | 必须是可归一化域名，不允许协议头和路径 |
| `action` | allow list 固定 `allow`，deny list 固定 `block` |

错误：`409 RULE_ALREADY_EXISTS`、`422 INVALID_DOMAIN`。

### 4.9 删除规则

```http
DELETE /api/v1/member/profiles/{profile_id}/rules/{rule_id}
```

删除规则后需要标记 Profile 有未发布变更。



### 4.9A 白名单快捷接口

白名单快捷接口是 `profile_rules` 的语义别名，必须写入同一张表。

```http
GET    /api/v1/member/profiles/{profile_id}/allowlist
POST   /api/v1/member/profiles/{profile_id}/allowlist
DELETE /api/v1/member/profiles/{profile_id}/allowlist/{rule_id}
```

POST 请求固定语义：`list_type=allow`、`action=allow`。

### 4.9B 黑名单快捷接口

黑名单快捷接口是 `profile_rules` 的语义别名，必须写入同一张表。

```http
GET    /api/v1/member/profiles/{profile_id}/denylist
POST   /api/v1/member/profiles/{profile_id}/denylist
PUT    /api/v1/member/profiles/{profile_id}/denylist/{rule_id}
DELETE /api/v1/member/profiles/{profile_id}/denylist/{rule_id}
```

POST 请求固定语义：`list_type=deny`、`action=block`。

PUT 请求用于更新规则的 `domain`、`match_type` 或 `note` 字段。

优先级必须为：白名单 > 黑名单 > 安全 > 隐私 > 家长监护 > 默认动作。

### 4.9C 规则导入导出

```http
POST /api/v1/member/profiles/{profile_id}/rules/import
GET  /api/v1/member/profiles/{profile_id}/rules/export
```

POST 请求（multipart/form-data）：
- `file`: 规则文件（txt/json/csv）
- `list_type`: `allowlist` / `denylist`
- `duplicate_action`: `skip` / `overwrite` / `add`（默认 skip）

GET 请求参数：
- `list_type`: `allowlist` / `denylist` / `all`（默认 all）
- `format`: `txt` / `json` / `csv`（默认 txt）

### 4.9D Profile 设置（Profile 级）

```http
GET    /api/v1/member/profiles/{profile_id}/settings
PUT    /api/v1/member/profiles/{profile_id}/settings
GET    /api/v1/member/profiles/{profile_id}/settings/security
PUT    /api/v1/member/profiles/{profile_id}/settings/security
GET    /api/v1/member/profiles/{profile_id}/settings/privacy
PUT    /api/v1/member/profiles/{profile_id}/settings/privacy
GET    /api/v1/member/profiles/{profile_id}/settings/parental
PUT    /api/v1/member/profiles/{profile_id}/settings/parental
```

GET `/settings` 响应：
```json
{
  "data": {
    "security": {},
    "privacy": {},
    "parental": {}
  }
}
```

### 4.10 发布 Profile

```http
POST /api/v1/member/profiles/{profile_id}/publish
```

请求：

```json
{
  "message": "Update deny list",
  "force": false
}
```

处理流程：

1. 校验 Profile 属于当前用户。
2. 读取 Profile、规则、设备、套餐配额。
3. 生成 `profile_versions` 不可变快照。
4. Member 域调用 `portal-web(原 console 域)` 进程内 `ProfilePublishService::publish()`（原 `POST /api/v1/internal/profile-configs/publish`）。
5. 保存 `publish_id` 和状态。

响应：

```json
{
  "data": {
    "profile_id": "prf_01H...",
    "version": 4,
    "checksum": "sha256:...",
    "publish_id": "pub_01H...",
    "status": "queued"
  }
}
```

错误：`409 NO_CHANGES_TO_PUBLISH`、`424 DNS_CONSOLE_UNAVAILABLE`。

### 4.11 发布状态

```http
GET /api/v1/member/profiles/{profile_id}/publishes/{publish_id}
```

响应字段：`status`、`target_node_count`、`applied_node_count`、`failed_node_count`、`created_at`、`completed_at`。

### 4.12 设备列表

```http
GET /api/v1/member/profiles/{profile_id}/devices
```

### 4.13 创建设备

```http
POST /api/v1/member/profiles/{profile_id}/devices
```

请求：

```json
{
  "name": "Alice iPhone",
  "device_type": "ios",
  "source_ip": null,
  "device_key": "optional-client-generated-id"
}
```

响应包含：`device_id`、`doh_url`、`dot_hostname`、`udp_endpoint`。

### 4.14 查询日志

```http
GET /api/v1/member/profiles/{profile_id}/logs?from=2026-06-12T00:00:00Z&to=2026-06-12T23:59:59Z&domain=example.com&action=blocked&page=1&per_page=50
```

响应 item：

```json
{
  "timestamp": "2026-06-12T10:00:00Z",
  "profile_id": "prf_01H...",
  "device_id": "dev_01H...",
  "query_name": "ads.example.com",
  "query_type": "A",
  "action": "blocked",
  "reason": "denylist",
  "category": "custom",
  "latency_ms": 7,
  "node_id": "node_01H...",
  "rcode": "NXDOMAIN"
}
```

权限：用户只能查看自己 Profile 的日志。

### 4.15 Profile 统计

```http
GET /api/v1/member/profiles/{profile_id}/stats/summary?range=24h
GET /api/v1/member/profiles/{profile_id}/stats/top-domains?range=24h&action=blocked
GET /api/v1/member/profiles/{profile_id}/stats/timeseries?range=7d&interval=1h
```



### 4.16 会员中心设置

```http
GET /api/v1/member/settings
PUT /api/v1/member/settings
```

PUT 请求字段：`name`、`timezone`、`locale`、`default_profile_id`、`ui_theme`、`email_notifications_enabled`。

### 4.17 Profile DNS 接入设置

```http
GET /api/v1/member/profiles/{profile_id}/settings/dns
```

响应字段：`doh_url`、`dot_hostname`、`udp_endpoint`、`ipv6_endpoint`、`profile_id`、`setup_guides`。该接口只展示接入信息，不修改 resolver 节点配置。

### 4.18 会员中心 / 套餐

```http
GET /api/v1/member/membership
```

响应字段：`plan_code`、`subscription_status`、`monthly_query_limit`、`used_query_count`、`quota_status`、`current_period_start`、`current_period_end`、`upgrade_options`、`latest_invoice`。

### 4.19 我的团队列表

```http
GET /api/v1/member/teams
```

响应：

```json
{
  "data": [
    {
      "id": "team_01H...",
      "name": "My Team",
      "slug": "my-team",
      "role": "owner",
      "member_count": 5,
      "max_members": 10,
      "created_at": "2026-06-12T00:00:00Z"
    }
  ]
}
```

### 4.20 创建团队

```http
POST /api/v1/member/teams
```

请求：

```json
{
  "name": "My Team",
  "slug": "my-team",
  "description": "Team description"
}
```

响应：`201 Created`

错误：`409 SLUG_ALREADY_EXISTS`、`422 VALIDATION_FAILED`、`409 TEAM_LIMIT_EXCEEDED`。

### 4.21 团队详情

```http
GET /api/v1/member/teams/{team_id}
```

校验：当前用户必须是团队成员。

### 4.22 更新团队

```http
PUT /api/v1/member/teams/{team_id}
```

请求字段：`name`、`description`。

权限：仅 `owner` / `admin`。

### 4.23 删除团队

```http
DELETE /api/v1/member/teams/{team_id}
```

权限：仅 `owner`。软删除团队及其关联数据。

### 4.24 团队成员列表

```http
GET /api/v1/member/teams/{team_id}/members
```

响应：

```json
{
  "data": [
    {
      "user_id": "usr_01H...",
      "name": "Alice",
      "email": "alice@example.com",
      "role": "admin",
      "joined_at": "2026-06-12T00:00:00Z"
    }
  ]
}
```

### 4.25 邀请成员

```http
POST /api/v1/member/teams/{team_id}/invitations
```

请求：

```json
{
  "email": "bob@example.com",
  "role": "member"
}
```

权限：仅 `owner` / `admin`。

### 4.26 邀请列表

```http
GET /api/v1/member/teams/{team_id}/invitations
```

### 4.27 撤销邀请

```http
DELETE /api/v1/member/teams/{team_id}/invitations/{invitation_id}
```

### 4.28 接受邀请

```http
POST /api/v1/member/teams/accept-invitation
```

请求：

```json
{
  "token": "invitation_token_string"
}
```

### 4.29 移除成员

```http
DELETE /api/v1/member/teams/{team_id}/members/{user_id}
```

权限：`owner` 可移除任意成员；`admin` 只能移除 `member` 角色。

### 4.30 切换当前团队

```http
POST /api/v1/member/teams/{team_id}/switch
```

切换后后续 API 请求使用该团队上下文。当前用户的 `current_team_id` 更新。

### 4.37 团队 Profile 列表

```http
GET /api/v1/member/teams/{team_id}/profiles
```

返回属于该团队的 Profile 列表，与个人 Profile 列表结构相同。

### 4.38 API Key 管理

```http
GET    /api/v1/member/api-keys
POST   /api/v1/member/api-keys
GET    /api/v1/member/api-keys/{key_id}
PUT    /api/v1/member/api-keys/{key_id}
DELETE /api/v1/member/api-keys/{key_id}
POST   /api/v1/member/api-keys/{key_id}/rotate
```

POST 请求：
```json
{
  "name": "My API Key",
  "description": "For CI/CD"
}
```

响应（创建时）：
```json
{
  "data": {
    "id": "key_01H...",
    "name": "My API Key",
    "description": "For CI/CD",
    "key": "sk_live_xxx",
    "created_at": "2026-06-12T00:00:00Z"
  }
}
```

POST `/rotate` 会生成新的 key 值，旧 key 立即失效。

### 4.39 设备管理（会员级）

```http
GET    /api/v1/member/devices
GET    /api/v1/member/devices/{device_id}
POST   /api/v1/member/devices/{device_id}/disable
POST   /api/v1/member/devices/{device_id}/enable
```

响应 item：
```json
{
  "id": "dev_01H...",
  "name": "Alice iPhone",
  "device_type": "ios",
  "status": "active",
  "profile_id": "prf_01H...",
  "last_seen_at": "2026-06-12T10:00:00Z",
  "created_at": "2026-06-12T00:00:00Z"
}
```

### 4.40 DNS 端点

```http
GET /api/v1/member/dns-endpoints
```

返回当前用户可用的 DNS 接入端点信息（DoH URL、DoT hostname、UDP endpoint 等）。

## 5. Admin API

### 5.1 用户列表

```http
GET /api/v1/admin/users?keyword=alice&status=active&page=1
```

### 5.2 用户详情

```http
GET /api/v1/admin/users/{user_id}
```

### 5.3 禁用 / 解禁用户

```http
POST /api/v1/admin/users/{user_id}/disable
POST /api/v1/admin/users/{user_id}/enable
```

必须写入 `audit_logs`。

### 5.4 套餐管理

```http
GET    /api/v1/admin/plans
POST   /api/v1/admin/plans
PUT    /api/v1/admin/plans/{plan_id}
DELETE /api/v1/admin/plans/{plan_id}
```

### 5.5 审计日志

```http
GET /api/v1/admin/audit-logs?actor_id=&action=&from=&to=
```

### 5.6 团队管理（管理员）

```http
GET    /api/v1/admin/teams?keyword=&status=&page=1
GET    /api/v1/admin/teams/{team_id}
GET    /api/v1/admin/teams/{team_id}/members
POST   /api/v1/admin/teams/{team_id}/disable
POST   /api/v1/admin/teams/{team_id}/enable
```

管理员可查看所有团队、管理团队状态。

## 6. portal-web Member 域调用 portal-web(原 console 域)

> 合并后 Member 域与原 console 域同进程；生产环境推荐走 Laravel 进程内服务(`ProfilePublishService` / `QuotaService` / `BillingUsageService` / `NodeHealthViewService` 等)，不走 HTTP。`/api/v1/internal/*` 路由仅在跨进程调试 / 单元测试中使用。

### 6.1 发布配置（生产走进程内服务；跨进程调试路径）

`portal-web` Member 域调用 `portal-web(原 console 域)` 进程内服务时直接注入 Service；如需跨进程模拟，HTTP 路径为：

```http
POST /api/v1/internal/profile-publishes
X-Internal-Timestamp: 2026-06-12T10:00:00Z
X-Internal-Signature: hmac-sha256(...)
```

`shared.token:internal` 中间件会校验上述头。

请求：

```json
{
  "profile_id": "prf_01H...",
  "user_id": "usr_01H...",
  "version": 4,
  "checksum": "sha256:...",
  "config": {
    "default_action": "allow",
    "rules": []
  },
  "quota": {
    "plan_code": "free",
    "monthly_query_limit": 300000,
    "used_query_count": 0,
    "quota_status": "normal",
    "log_retention_days": 90
  }
}
```

### 6.2 查询发布状态

发布状态由 Member 域直接读 `config_versions` / `publish_tasks`（同库同进程）；V1 **不**提供独立的 `GET /api/v1/internal/profile-configs/publishes/{publish_id}/status` 端点。Member 域可通过 `/api/v1/member/profiles/{profile_id}/publishes/{publish_id}` 读取状态；Admin 总后台可通过 `GET /api/v1/admin/publishes` 轮询 publish_tasks 状态。

## 7. 安全要求

- 所有 Member API 必须校验资源归属。
- Admin API 必须校验管理员角色和具体权限。
- Internal API 调用必须 HMAC / mTLS，禁止使用普通用户 token。
- 生产环境不得存在默认管理员密码。
- 所有写接口必须有 audit log 或 domain event。



## 8. 财务 / 计费 API

> 财务接口以 `specs/portal-web/billing-finance.md` 为准。所有写接口必须带 `Idempotency-Key`，所有金额都使用 `amount_minor`。

### 7.1 套餐与价格

```http
GET /api/v1/public/plans
GET /api/v1/admin/plans
POST /api/v1/admin/plans
PUT /api/v1/admin/plans/{plan_id}
POST /api/v1/admin/plans/{plan_id}/prices
POST /api/v1/admin/plan-prices/{price_id}/archive
```

`plan_prices.unit_amount_minor` 是价格事实字段；`plans.price_monthly_minor` 只作为兼容显示字段。

### 7.2 订阅

```http
GET  /api/v1/member/subscription
POST /api/v1/member/subscription/checkout
POST /api/v1/member/subscription/cancel
```

`checkout` 请求必须包含：

```json
{
  "plan_price_id": "price_01H...",
  "success_url": "https://portal.example.com/billing/success",
  "cancel_url": "https://portal.example.com/billing/cancel"
}
```

### 7.3 订单、发票、支付、退款

```http
GET  /api/v1/member/orders
GET  /api/v1/member/orders/{order_id}
GET  /api/v1/member/invoices
GET  /api/v1/member/invoices/{invoice_id}
GET  /api/v1/member/payments
GET  /api/v1/admin/orders
GET  /api/v1/admin/invoices
GET  /api/v1/admin/payments
POST /api/v1/admin/refunds
```

退款请求必须包含 `payment_id`、`amount_minor`、`currency`、`reason`，且累计成功退款不得超过原支付成功金额。

### 7.4 支付 Provider Webhook

```http
POST /api/v1/public/billing/webhooks/{provider}
```

要求：

- 必须校验 provider 签名。
- 必须按 `provider + provider_event_id` 幂等。
- 未通过签名不得写 payments、refunds、invoices、ledger。

### 7.5 portal-web(原 console 域) 派生 usage（合并后：进程内服务）

合并后 `portal-web(原 console 域)` 的 usage worker 不再以 HTTP push 形式回调 `portal-web` Member 域，而是直接调用 Member 域进程内服务 `BillingUsageService::recordUsage()`，由该服务幂等累加 `usage_records` / `usage_counters`。

```text
dns-resolver 批量上报 query logs
  → portal-web(原 console 域) POST /api/v1/agent/query-logs/batch（已落地）
  → QueryLogIngestService 写 query_log_ingest_batches（幂等 batch_id + content_sha256）
  → LogWorker 异步写 ClickHouse dns_logs
  → UsageWorker 进程内调用 BillingUsageService::recordUsage()（推荐，V1 默认）
  → 进度会同步触发 QuotaService::snapshot()，更新 quota_status
```

V1 中该用量只用于 Free 300,000 queries/月限制、统计展示和风控；不得生成 DNS 查询按量收费。`usage_records` / `usage_counters` 写接口必须支持 `Idempotency-Key`（按 `source + source_batch_id + profile_id` 幂等）。

> V2+ 评估替换为 push 模型；若启用 push，HTTP 端点为：
>
> ```http
> POST /api/v1/internal/usage/batches
> X-Internal-Timestamp: 2026-06-12T10:01:00Z
> X-Internal-Signature: hmac-sha256(...)
> Idempotency-Key: portal-web:batch_01H_usage_20260612_1000
> ```
>
> V1 **不**实现此 push 端点。

### 7.6 发布状态回调（V1 不实现）

V1 **不提供** `POST /api/v1/internal/publish-status/callback` 端点。`portal-web` Member 域与原 console 域同进程，发布状态通过同库 `publish_tasks` / `config_versions` 直接读取；Admin 总后台通过 `GET /api/v1/admin/publishes` 轮询。

V2+ 评估添加 push 回调。

## 9. NextDNS Lite V1 计费 API 补充

### 8.1 套餐展示响应示例

```json
{
  "data": [
    {
      "code": "free",
      "name": "Free",
      "billing_model": "free_quota",
      "query_limit_monthly": 300000,
      "profile_limit": null,
      "device_limit": null,
      "quota_status": "normal",
      "prices": [{"billing_interval":"month","unit_amount_minor":0,"currency":"USD","unit_label":"account"}]
    },
    {
      "code": "pro",
      "name": "Pro",
      "billing_model": "flat_subscription",
      "query_limit_monthly": null,
      "profile_limit": null,
      "device_limit": null,
      "quota_status": "unlimited",
      "prices": [
        {"billing_interval":"month","unit_amount_minor":199,"currency":"USD","unit_label":"account"},
        {"billing_interval":"year","unit_amount_minor":1990,"currency":"USD","unit_label":"account"}
      ]
    }
  ]
}
```

### 9.2 Checkout 请求

个人 Pro：

```json
{
  "plan_price_id": "price_pro_month_usd",
  "success_url": "https://portal.example.com/billing/success",
  "cancel_url": "https://portal.example.com/billing/cancel"
}
```

Business：

```json
{
  "plan_price_id": "price_business_month_usd",
  "seat_count": 51,
  "success_url": "https://portal.example.com/billing/success",
  "cancel_url": "https://portal.example.com/billing/cancel"
}
```

Business 计算结果必须为：

```text
block_size = 50
block_quantity = (51 + 50 - 1) // 50 = 2
amount_minor = 1990 * 2 = 3980
```

### 8.3 Free quota 状态接口

```http
GET /api/v1/member/usage/quota
```

响应：

```json
{
  "data": {
    "plan_code": "free",
    "monthly_query_limit": 300000,
    "used_query_count": 250000,
    "quota_status": "normal",
    "period_start": "2026-06-01T00:00:00Z",
    "period_end": "2026-07-01T00:00:00Z"
  }
}
```

### 8.4 V1 禁止项

```text
POST /api/v1/member/usage-overage/checkout     禁止
POST /api/v1/admin/query-usage-prices          禁止
invoice_lines.item_type = usage_overage        禁止
invoice_lines.item_type = query_usage_charge   禁止
```
