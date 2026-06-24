# portal-web 数据模型

> `portal-web` 使用 MySQL 作为业务主库。本文定义 MVP 必需表和后续扩展表。类型以 MySQL 为准。

## 1. 命名约定

- 主键推荐 `uuid` 或 ULID 字符串，示例用 `uuid`（MySQL 中为 `char(36)`）。
- 时间字段统一 `timestamp`。
- 可软删除业务表包含 `deleted_at timestamp null`。
- JSON 使用 `json`。
- 金额必须统一使用最小货币单位整数 `amount_minor bigint`，并同时保存 `currency char(3)`；禁止使用 float/double，禁止在财务主表中用 `decimal(12,2)` 作为金额事实字段。详见 `specs/portal-web/billing-finance.md`。

## 2. MVP 必需表

### 2.1 users

> `users` 是 portal-web 前后台会员/终端用户登录表，与 `portal-web(原 console 域)` 的 `dns_admins`（管理员登录表）**物理隔离**：两张表不共享行、不共享 password_hash 列、不得 union 也不得把管理员账号写进 users。管理员登录/session/审计走 dns_admins / dns_admin_personal_access_tokens / dns_admin_audit_logs，迁移在 002 与 004。

| 字段 | 类型 | 约束 | 说明 |
|---|---|---|---|
| id | uuid | pk | 用户 ID |
| name | varchar(100) | not null | 显示名 |
| email | varchar(255) | not null unique | 邮箱 |
| email_verified_at | timestamp | null | 邮箱验证时间 |
| password_hash | varchar(255) | not null | 密码 hash |
| role | varchar(30) | not null default 'member' | `member` / `admin` |
| status | varchar(30) | not null default 'active' | `active` / `disabled` / `deleted` |
| timezone | varchar(64) | not null default 'UTC' | 时区 |
| locale | varchar(20) | not null default 'en' | 语言 |
| current_plan_id | uuid | null fk plans.id | 当前套餐 |
| last_login_at | timestamp | null | 最后登录 |
| created_at | timestamp | not null | 创建时间 |
| updated_at | timestamp | not null | 更新时间 |
| deleted_at | timestamp | null | 软删除 |

索引：

```sql
CREATE UNIQUE INDEX uniq_users_email ON users (lower(email));
CREATE INDEX idx_users_status ON users (status);
```

### 2.1A personal_access_tokens

> V1 登录态建议使用 Laravel 风格 personal access token 或等价令牌表。当前 `ocer-dns` 工作区已补充该表的 migration 草案。

| 字段 | 类型 | 约束 | 说明 |
|---|---|---|---|
| id | uuid | pk | Token ID |
| tokenable_type | varchar(100) | not null | 资源类型，当前主要是 user |
| tokenable_id | uuid | not null | 用户 ID |
| name | varchar(255) | not null | 设备或用途名 |
| token_hash | varchar(255) | not null unique | token hash，不存明文 |
| last_used_at | timestamp | null | 最近使用时间 |
| expires_at | timestamp | null | 过期时间 |
| created_at | timestamp | not null | 创建时间 |
| updated_at | timestamp | not null | 更新时间 |

### 2.2 profiles

| 字段 | 类型 | 约束 | 说明 |
|---|---|---|---|
| id | uuid | pk | Profile ID |
| user_id | uuid | not null fk users.id | 所属用户 |
| team_id | uuid | null | 所属团队（个人 Profile 为 null） |
| name | varchar(100) | not null | 名称 |
| description | text | null | 说明 |
| status | varchar(30) | not null default 'active' | `active` / `disabled` / `deleted` |
| default_action | varchar(20) | not null default 'allow' | 默认动作 |
| block_response | varchar(30) | not null default 'nxdomain' | `nxdomain` / `zero_ip` / `refused` |
| security_enabled | boolean | not null default true | 安全防护 |
| adblock_enabled | boolean | not null default false | 广告拦截 |
| parental_enabled | boolean | not null default false | 家长控制 |
| privacy_enabled | boolean | not null default true | 隐私保护 |
| safe_search_enabled | boolean | not null default false | 安全搜索 |
| log_mode | varchar(30) | not null default 'full' | `full` / `blocked_only` / `disabled` |
| current_version | bigint | not null default 0 | 当前发布版本 |
| draft_version | bigint | not null default 0 | 草案版本 |
| last_published_at | timestamp | null | 最后发布时间 |
| created_at | timestamp | not null | 创建时间 |
| updated_at | timestamp | not null | 更新时间 |
| deleted_at | timestamp | null | 软删除 |

索引：

```sql
CREATE INDEX idx_profiles_user_id ON profiles (user_id);
CREATE INDEX idx_profiles_status ON profiles (status);
CREATE UNIQUE INDEX uniq_profiles_user_name_active ON profiles (user_id, lower(name)) WHERE deleted_at IS NULL;
```

### 2.3 profile_rules

| 字段 | 类型 | 约束 | 说明 |
|---|---|---|---|
| id | uuid | pk | 规则 ID |
| profile_id | uuid | not null fk profiles.id | Profile |
| list_type | varchar(20) | not null | `allow` / `deny` |
| match_type | varchar(20) | not null | `exact` / `suffix` / `wildcard` |
| domain | varchar(255) | not null | 用户输入域名 |
| normalized_domain | varchar(255) | not null | 归一化域名 |
| action | varchar(20) | not null | `allow` / `block` / `rewrite` |
| category | varchar(50) | null | 分类 |
| enabled | boolean | not null default true | 是否启用 |
| note | text | null | 备注 |
| created_by | uuid | not null fk users.id | 创建人 |
| created_at | timestamp | not null | 创建时间 |
| updated_at | timestamp | not null | 更新时间 |
| deleted_at | timestamp | null | 软删除 |

约束与索引：

```sql
ALTER TABLE profile_rules ADD CONSTRAINT chk_profile_rules_list_type CHECK (list_type IN ('allow','deny'));
ALTER TABLE profile_rules ADD CONSTRAINT chk_profile_rules_match_type CHECK (match_type IN ('exact','suffix','wildcard'));
CREATE INDEX idx_profile_rules_profile ON profile_rules (profile_id, list_type, enabled);
CREATE UNIQUE INDEX uniq_profile_rule_active ON profile_rules (profile_id, list_type, match_type, normalized_domain) WHERE deleted_at IS NULL;
```



### 2.3A profile_feature_settings

> 保存会员中心“安全 / 隐私 / 家长监护 / 设置”的结构化开关。常用查询字段保留在 `profiles`，详细配置保存在本表 JSONB，发布 Profile 时一并写入 `profile_versions.config_json`。

| 字段 | 类型 | 约束 | 说明 |
|---|---|---|---|
| id | uuid | pk | 设置 ID |
| profile_id | uuid | not null unique fk profiles.id | Profile |
| security | json | not null default '{}' | 恶意、钓鱼、C2 等安全开关 |
| privacy | json | not null default '{}' | 跟踪器、遥测、日志模式、IP 匿名化 |
| parental | json | not null default '{}' | 成人内容、安全搜索、YouTube 受限模式 |
| preferences | json | not null default '{}' | 其他 Profile 设置 |
| created_at | timestamp | not null | 创建时间 |
| updated_at | timestamp | not null | 更新时间 |

索引：

```sql
CREATE UNIQUE INDEX uniq_profile_feature_settings_profile ON profile_feature_settings (profile_id);
```

V1 默认值：

```json
{
  "security": {"enabled": true, "block_malware": true, "block_phishing": true, "block_command_and_control": true, "block_cryptojacking": true},
  "privacy": {"enabled": true, "block_trackers": true, "block_analytics": true, "block_telemetry": true, "anonymize_client_ip": true, "log_mode": "full"},
  "parental": {"enabled": false, "block_adult_content": false, "safe_search": false, "youtube_restricted_mode": false, "block_gambling_basic": false}
}
```

### 2.4 profile_versions

| 字段 | 类型 | 约束 | 说明 |
|---|---|---|---|
| id | uuid | pk | 版本 ID |
| profile_id | uuid | not null fk profiles.id | Profile |
| version | bigint | not null | 单 Profile 内递增 |
| status | varchar(30) | not null default 'draft' | `draft` / `published` / `superseded` / `rolled_back` |
| checksum | varchar(100) | not null | `sha256:...` |
| config_json | json | not null | 发布给 resolver 的配置快照 |
| rule_count | integer | not null default 0 | 规则数 |
| message | varchar(255) | null | 发布说明 |
| published_by | uuid | null fk users.id | 发布人 |
| external_publish_id | varchar(80) | null | portal-web(原 console 域) 发布 ID |
| published_at | timestamp | null | 发布时间 |
| created_at | timestamp | not null | 创建时间 |
| updated_at | timestamp | not null | 更新时间 |

索引：

```sql
CREATE UNIQUE INDEX uniq_profile_versions_profile_version ON profile_versions (profile_id, version);
CREATE INDEX idx_profile_versions_status ON profile_versions (status);
```

### 2.5 devices

| 字段 | 类型 | 约束 | 说明 |
|---|---|---|---|
| id | uuid | pk | 设备 ID |
| profile_id | uuid | not null fk profiles.id | Profile |
| user_id | uuid | not null fk users.id | 用户 |
| name | varchar(100) | not null | 设备名称 |
| device_type | varchar(50) | null | `ios` / `android` / `router` / `desktop` |
| device_key_hash | varchar(128) | null | 设备 key hash |
| source_ip | varchar(45) | null | 可选来源 IP 绑定，谨慎使用 |
| status | varchar(30) | not null default 'active' | 状态 |
| last_seen_at | timestamp | null | 最近查询时间 |
| created_at | timestamp | not null | 创建时间 |
| updated_at | timestamp | not null | 更新时间 |
| deleted_at | timestamp | null | 软删除 |

索引：

```sql
CREATE INDEX idx_devices_profile_id ON devices (profile_id);
CREATE INDEX idx_devices_user_id ON devices (user_id);
CREATE INDEX idx_devices_last_seen ON devices (last_seen_at);
```

### 2.6 audit_logs

| 字段 | 类型 | 约束 | 说明 |
|---|---|---|---|
| id | bigint auto_increment | pk | 审计 ID |
| actor_id | uuid | null fk users.id | 操作人 |
| actor_type | varchar(30) | not null default 'user' | user / system / node |
| action | varchar(100) | not null | 操作 |
| resource_type | varchar(100) | null | 资源类型 |
| resource_id | varchar(100) | null | 资源 ID |
| ip_hash | varchar(128) | null | IP hash |
| user_agent | text | null | UA |
| before_json | json | null | 修改前 |
| after_json | json | null | 修改后 |
| created_at | timestamp | not null | 创建时间 |

索引：

```sql
CREATE INDEX idx_audit_logs_actor ON audit_logs (actor_id, created_at DESC);
CREATE INDEX idx_audit_logs_resource ON audit_logs (resource_type, resource_id);
CREATE INDEX idx_audit_logs_action ON audit_logs (action);
```

## 3. 套餐与计费表（财务主规格摘要）

### 3.1 plans

| 字段 | 类型 | 约束 | 说明 |
|---|---|---|---|
| id | uuid | pk | 套餐 ID |
| code | varchar(50) | not null unique | `free` / `pro` / `business` / `education` / `enterprise` |
| name | varchar(100) | not null | 名称 |
| billing_model | varchar(40) | not null | `free_quota` / `flat_subscription` / `employee_block` / `student_block` / `custom_contract` |
| price_monthly_minor | bigint | not null default 0 | 兼容显示字段；完整价格以 `plan_prices` 为准 |
| billing_currency | char(3) | not null default 'USD' | 默认计费币种 |
| profile_limit | integer | null | null 表示 unlimited |
| device_limit | integer | null | null 表示 unlimited |
| query_limit_monthly | bigint | null | Free=300000；付费 unlimited 为 null |
| block_size | integer | null | Business=50，Education=250 |
| unit_label | varchar(40) | not null default 'account' | account / employee_block_50 / student_block_250 |
| log_retention_days | integer | not null | 日志保留天数 |
| features | json | not null default '{}' | 功能开关 |
| status | varchar(30) | not null default 'active' | 状态 |
| created_at | timestamp | not null | 创建时间 |
| updated_at | timestamp | not null | 更新时间 |

### 3.2 subscriptions

| 字段 | 类型 | 约束 | 说明 |
|---|---|---|---|
| id | uuid | pk | 订阅 ID |
| user_id | uuid | not null fk users.id | 用户 |
| plan_id | uuid | not null fk plans.id | 套餐 |
| status | varchar(30) | not null | `trialing` / `active` / `past_due` / `canceled` |
| current_period_start | timestamp | not null | 当前周期开始 |
| current_period_end | timestamp | not null | 当前周期结束 |
| cancel_at | timestamp | null | 计划取消时间 |
| canceled_at | timestamp | null | 已取消时间 |
| provider | varchar(50) | null | 支付渠道 |
| provider_subscription_id | varchar(255) | null | 渠道订阅 ID |
| created_at | timestamp | not null | 创建时间 |
| updated_at | timestamp | not null | 更新时间 |

### 3.3 orders / invoices / payments / refunds / ledger

财务主规格以 `specs/portal-web/billing-finance.md` 和 `migrations/` 中 Laravel PHP 迁移为准。

即使 MVP 暂时使用 sandbox/stub 支付，也必须先生成完整财务表结构、幂等键、金额约束、发票不可变 trigger、退款上限 trigger、webhook 事件表和对账表；不得只保留 `plans` 或 `users.current_plan_id` 作为正式计费模型。

## 4. 团队表（MVP）

> 团队管理从 V1 开始纳入 MVP，支持个人/团队双模式：个人 Profile 的 `team_id` 为 null，团队 Profile 绑定到团队。

### 4.1 teams

| 字段 | 类型 | 约束 | 说明 |
|---|---|---|---|
| id | uuid | pk | 团队 ID |
| name | varchar(100) | not null | 团队名称 |
| slug | varchar(100) | not null unique | 唯一标识 |
| description | text | null | 说明 |
| owner_id | uuid | not null fk users.id | 团队创建者 |
| member_count | integer | not null default 1 | 成员数 |
| max_members | integer | null | 成员上限（套餐约束） |
| status | varchar(30) | not null default 'active' | `active` / `disabled` / `deleted` |
| created_at | timestamp | not null | 创建时间 |
| updated_at | timestamp | not null | 更新时间 |
| deleted_at | timestamp | null | 软删除 |

索引：

```sql
CREATE UNIQUE INDEX uniq_teams_slug ON teams (lower(slug));
CREATE INDEX idx_teams_owner ON teams (owner_id);
```

### 4.2 team_members

| 字段 | 类型 | 约束 | 说明 |
|---|---|---|---|
| id | uuid | pk | 成员 ID |
| team_id | uuid | not null fk teams.id | 团队 |
| user_id | uuid | not null fk users.id | 用户 |
| role | varchar(30) | not null default 'member' | `owner` / `admin` / `member` |
| joined_at | timestamp | not null | 加入时间 |
| created_at | timestamp | not null | 创建时间 |
| updated_at | timestamp | not null | 更新时间 |
| deleted_at | timestamp | null | 软删除 |

索引：

```sql
CREATE UNIQUE INDEX uniq_team_members_team_user ON team_members (team_id, user_id) WHERE deleted_at IS NULL;
CREATE INDEX idx_team_members_user ON team_members (user_id);
```

### 4.3 team_invitations

| 字段 | 类型 | 约束 | 说明 |
|---|---|---|---|
| id | uuid | pk | 邀请 ID |
| team_id | uuid | not null fk teams.id | 团队 |
| email | varchar(255) | not null | 被邀请人邮箱 |
| role | varchar(30) | not null default 'member' | 邀请角色 |
| token_hash | varchar(255) | not null unique | 邀请令牌 hash |
| invited_by | uuid | not null fk users.id | 邀请人 |
| expires_at | timestamp | not null | 过期时间 |
| accepted_at | timestamp | null | 接受时间 |
| declined_at | timestamp | null | 拒绝时间 |
| created_at | timestamp | not null | 创建时间 |
| updated_at | timestamp | not null | 更新时间 |

索引：

```sql
CREATE INDEX idx_team_invitations_team ON team_invitations (team_id);
CREATE INDEX idx_team_invitations_email ON team_invitations (email);
```

### 4.4 api_keys

> 团队 API Key，用于 OpenAPI 调用。

| 字段 | 类型 | 约束 | 说明 |
|---|---|---|---|
| id | uuid | pk | Key ID |
| team_id | uuid | not null fk teams.id | 团队 |
| name | varchar(100) | not null | 名称 |
| key_hash | varchar(255) | not null unique | Key hash |
| last_used_at | timestamp | null | 最后使用 |
| expires_at | timestamp | null | 过期时间 |
| created_at | timestamp | not null | 创建时间 |
| updated_at | timestamp | not null | 更新时间 |
| deleted_at | timestamp | null | 软删除 |

## 5. 不属于 portal-web 的表

以下表不得放在 `portal-web` 主职责中：

```text
nodes
node_heartbeats
publish_tasks
task_executions
config_versions
```

这些属于 `portal-web(原 console 域)`。

DNS 高频查询日志长期不放 MySQL，进入 ClickHouse。
