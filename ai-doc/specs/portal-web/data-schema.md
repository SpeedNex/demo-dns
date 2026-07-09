# portal-web 数据模型

> `portal-web` 使用 MySQL 作为业务主库。本文定义所有表结构。类型以 MySQL 为准。

## 1. 命名约定

- 主键使用自增整数（`bigint unsigned auto_increment`），除非另有说明。
- 时间字段统一 `timestamp`。
- JSON 使用 `json`。
- 金额必须统一使用最小货币单位整数 `amount_minor bigint unsigned`，并同时保存 `currency char(3)`；禁止使用 float/double，禁止在财务主表中用 `decimal(12,2)` 作为金额事实字段。详见 `specs/portal-web/billing-finance.md`。
- 外键必须使用 `unsignedBigInteger`，引用自增主键。

## 2. 用户与认证表

### 2.1 users

> `users` 是 portal-web 前后台会员/终端用户登录表，与 `admins`（管理员登录表）**物理隔离**：两张表不共享行、不共享 password_hash 列、不得 union 也不得把管理员账号写进 users。管理员登录/session/审计走 admins / admin_user_roles / admin_audit_logs。

| 字段 | 类型 | 约束 | 说明 |
|---|---|---|---|
| uid | bigint unsigned | pk auto_increment | 用户 ID（自增整数） |
| username | varchar(100) | not null | 用户名 |
| email | varchar(190) | not null | 邮箱 |
| email_verified_at | timestamp | null | 邮箱验证时间 |
| password | varchar(255) | not null | 密码 hash |
| plan_code | varchar(40) | null | 套餐 code |
| locale | varchar(10) | not null default 'zh-CN' | 语言 |
| status | enum('active','suspended','closed') | not null default 'active' | 状态 |
| current_team_id | bigint unsigned | null fk teams.id | 当前团队 ID |
| remember_token | varchar(100) | null | Sanctum remember token |
| created_at | timestamp | not null | 创建时间 |
| updated_at | timestamp | not null | 更新时间 |

索引：

```sql
CREATE UNIQUE INDEX uniq_users_username ON users (username);
CREATE UNIQUE INDEX uniq_users_email ON users (email);
CREATE INDEX idx_users_plan ON users (plan_code);
ALTER TABLE users ADD CONSTRAINT fk_users_team FOREIGN KEY (current_team_id) REFERENCES teams(id) ON DELETE SET NULL ON UPDATE CASCADE;
```

### 2.2 personal_access_tokens

> Sanctum Token 表。`token` 列存储 SHA256(token) 哈希，不存明文。

| 字段 | 类型 | 约束 | 说明 |
|---|---|---|---|
| id | bigint unsigned | pk auto_increment | Token ID |
| tokenable_type | varchar(120) | not null | 资源类型，当前主要是 user |
| tokenable_id | bigint unsigned | not null | 用户 ID（关联 users.uid） |
| name | varchar(160) | not null | 设备或用途名 |
| token | varchar(64) | not null unique | SHA256(token) 哈希 |
| abilities | json | null | 权限 |
| last_used_at | timestamp | null | 最近使用时间 |
| expires_at | timestamp | null | 过期时间 |
| created_at | timestamp | not null | 创建时间 |
| updated_at | timestamp | not null | 更新时间 |

索引：

```sql
CREATE UNIQUE INDEX uniq_pat_token ON personal_access_tokens (token);
CREATE INDEX idx_pat_tokenable ON personal_access_tokens (tokenable_type, tokenable_id);
```

### 2.3 password_reset_tokens

| 字段 | 类型 | 约束 | 说明 |
|---|---|---|---|
| email | varchar(190) | pk | 邮箱 |
| token_hash | char(64) | not null | 令牌 hash |
| created_at | timestamp | null | 创建时间 |

### 2.4 sessions

| 字段 | 类型 | 约束 | 说明 |
|---|---|---|---|
| id | varchar(120) | pk | Session ID |
| user_id | bigint unsigned | null | 用户 ID |
| ip_address | varchar(45) | null | IP 地址 |
| user_agent | text | null | UA |
| payload | longtext | not null | 载荷 |
| last_activity | integer | not null | 最后活动时间戳 |

索引：

```sql
CREATE INDEX idx_sessions_user ON sessions (user_id);
CREATE INDEX idx_sessions_activity ON sessions (last_activity);
```

## 3. 管理员表

### 3.1 admins

> `admins` 是 portal-web 管理员登录表，与 `users`（会员/终端用户表）**物理隔离**。

| 字段 | 类型 | 约束 | 说明 |
|---|---|---|---|
| admin_id | bigint unsigned | pk auto_increment | 管理员 ID |
| username | varchar(100) | not null | 用户名 |
| email | varchar(190) | not null | 邮箱 |
| email_verified_at | timestamp | null | 邮箱验证时间 |
| password | varchar(255) | not null | 密码 hash |
| status | enum('active','suspended','closed') | not null default 'active' | 状态 |
| is_super | boolean | not null default false | 超级管理员 |
| last_login_at | timestamp | null | 最后登录时间 |
| last_login_ip | varchar(45) | null | 最后登录 IP |
| locale | varchar(10) | not null default 'zh-CN' | 语言 |
| remember_token | varchar(100) | null | Sanctum remember token |
| created_at | timestamp | not null | 创建时间 |
| updated_at | timestamp | not null | 更新时间 |

索引：

```sql
CREATE UNIQUE INDEX uniq_admins_username ON admins (username);
CREATE UNIQUE INDEX uniq_admins_email ON admins (email);
```

### 3.2 admin_roles

| 字段 | 类型 | 约束 | 说明 |
|---|---|---|---|
| id | bigint unsigned | pk auto_increment | 角色 ID |
| code | varchar(40) | not null | 角色编码 |
| name | varchar(120) | not null | 角色名称 |
| description | varchar(500) | null | 描述 |
| is_builtin | boolean | not null default false | 是否内置（后续重命名为 is_system） |
| created_at | timestamp | not null | 创建时间 |
| updated_at | timestamp | not null | 更新时间 |

索引：

```sql
CREATE UNIQUE INDEX uniq_admin_roles_code ON admin_roles (code);
```

V2.3 补充字段（2026_06_20_000062）：

```sql
ALTER TABLE admin_roles ADD COLUMN is_system boolean NOT NULL DEFAULT false AFTER description;
ALTER TABLE admin_roles ADD COLUMN status varchar(20) NOT NULL DEFAULT 'active' AFTER is_system;
```

### 3.3 admin_permissions

| 字段 | 类型 | 约束 | 说明 |
|---|---|---|---|
| id | bigint unsigned | pk auto_increment | 权限 ID |
| code | varchar(80) | not null unique | 权限编码 |
| resource | varchar(80) | not null | 资源 |
| action | varchar(80) | not null | 操作 |
| description | varchar(300) | null | 描述 |
| created_at | timestamp | not null | 创建时间 |
| updated_at | timestamp | not null | 更新时间 |

索引：

```sql
CREATE UNIQUE INDEX uniq_admin_perm_code ON admin_permissions (code);
CREATE INDEX idx_admin_perm_resource_action ON admin_permissions (resource, action);
```

### 3.4 admin_role_permissions

| 字段 | 类型 | 约束 | 说明 |
|---|---|---|---|
| id | bigint unsigned | pk auto_increment | 关联 ID |
| admin_role_id | bigint unsigned | not null fk admin_roles.id | 角色 |
| admin_permission_id | bigint unsigned | not null fk admin_permissions.id | 权限 |
| created_at | timestamp | not null | 创建时间 |
| updated_at | timestamp | not null | 更新时间 |

索引：

```sql
CREATE UNIQUE INDEX uniq_admin_role_perm ON admin_role_permissions (admin_role_id, admin_permission_id);
CREATE INDEX idx_admin_role_perm_perm ON admin_role_permissions (admin_permission_id);
```

### 3.5 admin_user_roles

| 字段 | 类型 | 约束 | 说明 |
|---|---|---|---|
| id | bigint unsigned | pk auto_increment | 关联 ID |
| admin_id | bigint unsigned | not null fk admins.admin_id | 管理员 |
| admin_role_id | bigint unsigned | not null fk admin_roles.id | 角色 |
| created_at | timestamp | not null | 创建时间 |
| updated_at | timestamp | not null | 更新时间 |

索引：

```sql
CREATE UNIQUE INDEX uniq_admin_user_role ON admin_user_roles (admin_id, admin_role_id);
CREATE INDEX idx_admin_user_role_role ON admin_user_roles (admin_role_id);
```

### 3.6 admin_audit_logs

| 字段 | 类型 | 约束 | 说明 |
|---|---|---|---|
| id | bigint unsigned | pk auto_increment | 审计 ID |
| actor_admin_id | bigint unsigned | not null fk admins.admin_id | 操作人 |
| actor_username | varchar(100) | null | 操作人用户名快照 |
| action | varchar(80) | not null | 操作 |
| target_type | varchar(80) | null | 目标类型 |
| target_id | bigint unsigned | null | 目标 ID |
| ip | varchar(45) | null | IP 地址 |
| user_agent | varchar(500) | null | UA |
| payload | json | null | 载荷 |
| created_at | timestamp | not null | 创建时间 |

索引：

```sql
CREATE INDEX idx_audit_actor ON admin_audit_logs (actor_admin_id);
CREATE INDEX idx_audit_action ON admin_audit_logs (action);
CREATE INDEX idx_audit_target ON admin_audit_logs (target_type, target_id);
CREATE INDEX idx_audit_created ON admin_audit_logs (created_at);
```

### 3.7 admin_menu_rule

| 字段 | 类型 | 约束 | 说明 |
|---|---|---|---|
| id | bigint unsigned | pk auto_increment | 菜单 ID |
| menu_key | varchar(80) | not null unique | 菜单 key |
| parent_key | varchar(80) | null | 父菜单 key |
| title_key | varchar(200) | not null | 标题 key（国际化） |
| path | varchar(300) | not null | 路由路径 |
| icon | varchar(100) | null | 图标 |
| sort_order | unsigned integer | not null default 0 | 排序 |
| visible | boolean | not null default true | 是否可见 |
| permission_code | varchar(80) | null | 权限编码 |
| group_key | varchar(50) | null | 分组 key |
| created_at | timestamp | not null | 创建时间 |
| updated_at | timestamp | not null | 更新时间 |

索引：

```sql
CREATE INDEX idx_admin_menu_rule_parent ON admin_menu_rule (parent_key);
CREATE INDEX idx_admin_menu_rule_group ON admin_menu_rule (group_key);
CREATE INDEX idx_admin_menu_rule_sort ON admin_menu_rule (sort_order);
```

## 4. 导航

### 4.1 navigation_catalogs

| 字段 | 类型 | 约束 | 说明 |
|---|---|---|---|
| id | integer unsigned | pk auto_increment | 目录 ID |
| key | varchar(80) | not null unique | 目录 key |
| label_key | varchar(160) | not null | 标签 key（国际化） |
| group_key | varchar(50) | null | 分组 key |
| path | varchar(300) | null | 路径 |
| icon | varchar(100) | null | 图标 |
| sort_order | integer | not null default 0 | 排序 |
| visible | boolean | not null default true | 是否可见 |
| created_at | timestamp | not null | 创建时间 |
| updated_at | timestamp | not null | 更新时间 |

## 5. DNS Profile 核心表

### 5.1 profiles

| 字段 | 类型 | 约束 | 说明 |
|---|---|---|---|
| id | bigint unsigned | pk auto_increment | 自增 ID |
| profile_id | char(6) | not null unique | 6 位 hex 路由标识，用于 DNS 识别 |
| user_id | bigint unsigned | not null fk users.uid | 所属用户 |
| name | varchar(120) | not null | 名称 |
| description | varchar(500) | null | 说明 |
| default_action | varchar(20) | not null default 'allow' | 默认动作（000062 补充） |
| block_response | varchar(20) | not null default 'nxdomain' | 拦截响应类型（000062 补充） |
| is_default | boolean | not null default false | 是否默认 Profile |
| status | enum('active','paused','closed') | not null default 'active' | 状态 |
| security_enabled | boolean | not null default true | 安全防护 |
| security_settings | json | null | 安全设置详细配置（000056 补充） |
| privacy_enabled | boolean | not null default true | 隐私保护 |
| privacy_settings | json | null | 隐私设置详细配置（000056 补充） |
| parental_enabled | boolean | not null default false | 家长控制 |
| parental_settings | json | null | 家长控制详细配置（000056 补充） |
| safesearch_enabled | boolean | not null default false | 安全搜索 |
| log_mode | varchar(20) | not null default 'full' | 日志模式（000062 补充） |
| log_retention_days | integer | not null default 24 | 日志保留天数 |
| version | integer | not null default 1 | 当前版本 |
| published_at | timestamp | null | 最后发布时间 |
| created_at | timestamp | not null | 创建时间 |
| updated_at | timestamp | not null | 更新时间 |

索引：

```sql
CREATE UNIQUE INDEX uniq_profiles_uid ON profiles (profile_id);
CREATE UNIQUE INDEX uniq_profiles_user_name ON profiles (user_id, name);
CREATE INDEX idx_profiles_user ON profiles (user_id);
ALTER TABLE profiles ADD CONSTRAINT chk_profiles_uid CHECK (profile_id REGEXP '^[0-9a-f]{6}$');
```

### 5.2 profile_rules

| 字段 | 类型 | 约束 | 说明 |
|---|---|---|---|
| id | bigint unsigned | pk auto_increment | 规则 ID |
| profile_id | bigint unsigned | not null fk profiles.id | Profile |
| rule_source_id | bigint unsigned | null fk rule_sources.id | 规则来源 |
| list_type | enum('allowlist','blocklist') | not null | 列表类型 |
| match_type | varchar(20) | not null default 'exact' | 匹配类型（000057 补充） |
| domain | varchar(255) | not null | 用户输入域名 |
| normalized_domain | varchar(255) | null | 归一化域名（000057 补充） |
| action | enum('block','allow','rewrite') | not null default 'block' | 动作 |
| enabled | boolean | not null default true | 是否启用（000057 补充） |
| category | varchar(64) | null | 分类（000057 补充） |
| created_by | varchar(32) | null | 创建人标识（000057 补充） |
| note | varchar(255) | null | 备注 |
| created_at | timestamp | not null | 创建时间 |
| updated_at | timestamp | not null | 更新时间 |

索引：

```sql
CREATE UNIQUE INDEX uniq_profile_rule ON profile_rules (profile_id, list_type, domain);
CREATE INDEX idx_profile_rules_profile ON profile_rules (profile_id);
CREATE INDEX idx_profile_rules_source ON profile_rules (rule_source_id);
CREATE INDEX idx_profile_rules_lookup ON profile_rules (profile_id, list_type, match_type, normalized_domain);
```

### 5.3 rule_sources

| 字段 | 类型 | 约束 | 说明 |
|---|---|---|---|
| id | bigint unsigned | pk auto_increment | 来源 ID |
| code | varchar(40) | not null unique | 来源编码 |
| name | varchar(160) | not null | 名称 |
| url | varchar(500) | null | 来源 URL |
| format | enum('domains','hosts','adblock','json') | not null default 'domains' | 格式 |
| category | enum('security','privacy','parental','custom') | not null default 'custom' | 分类 |
| enabled | boolean | not null default true | 是否启用 |
| last_sync_at | timestamp | null | 最后同步时间 |
| last_sync_status | enum('pending','ok','failed') | not null default 'pending' | 最后同步状态 |
| last_sync_message | varchar(500) | null | 同步消息 |
| item_count | bigint unsigned | not null default 0 | 规则条目数 |
| created_at | timestamp | not null | 创建时间 |
| updated_at | timestamp | not null | 更新时间 |

### 5.4 rule_items

| 字段 | 类型 | 约束 | 说明 |
|---|---|---|---|
| id | bigint unsigned | pk auto_increment | 条目 ID |
| rule_source_id | bigint unsigned | not null fk rule_sources.id | 规则来源 |
| domain | varchar(255) | not null | 域名 |
| category | varchar(60) | not null default 'default' | 分类 |
| action | enum('block','allow','rewrite','safe_search') | not null default 'block' | 动作 |
| created_at | timestamp | null | 创建时间 |

索引：

```sql
CREATE INDEX idx_rule_items_source ON rule_items (rule_source_id);
CREATE INDEX idx_rule_items_domain ON rule_items (domain);
```

### 5.5 devices

| 字段 | 类型 | 约束 | 说明 |
|---|---|---|---|
| id | bigint unsigned | pk auto_increment | 设备 ID |
| device_uid | varchar(40) | not null unique | 设备唯一标识 |
| profile_id | bigint unsigned | not null fk profiles.id | Profile |
| user_id | bigint unsigned | not null fk users.uid | 用户 |
| name | varchar(120) | null | 设备名称 |
| fingerprint | varchar(255) | not null | 设备指纹 |
| source | enum('auto','manual') | not null default 'auto' | 来源 |
| protocol | enum('doh','dot','doq','udp','tcp') | not null default 'doh' | DNS 协议 |
| user_agent | text | null | UA |
| sni | varchar(255) | null | SNI |
| ip_hash | varchar(64) | null | IP hash |
| source_ip | varchar(45) | null | 明文客户端 IP（000063 补充，用于 Profile->IP 路由，不记日志） |
| country | varchar(8) | null | 国家代码 |
| first_seen_at | timestamp | null | 首次发现时间 |
| last_seen_at | timestamp | null | 最近活跃时间 |
| last_query_at | timestamp | null | 最近查询时间 |
| query_count | bigint unsigned | not null default 0 | 查询计数 |
| status | enum('active','blocked','removed') | not null default 'active' | 状态 |
| created_at | timestamp | not null | 创建时间 |
| updated_at | timestamp | not null | 更新时间 |

索引：

```sql
CREATE UNIQUE INDEX uniq_devices_profile_fingerprint ON devices (profile_id, fingerprint);
CREATE UNIQUE INDEX uniq_devices_uid ON devices (device_uid);
CREATE INDEX idx_devices_user ON devices (user_id);
CREATE INDEX idx_devices_source_ip ON devices (source_ip);
```

### 5.6 profile_versions

> 发布给 resolver 的配置快照。由 console 域管理，portal-web 业务代码只读引用。

| 字段 | 类型 | 约束 | 说明 |
|---|---|---|---|
| id | bigint unsigned | pk auto_increment | 版本 ID |
| target_scope | enum('global','node','profile') | not null default 'node' | 发布范围 |
| target_node_id | bigint unsigned | null fk nodes.id | 目标节点 |
| target_profile_id | bigint unsigned | null fk profiles.id | 目标 Profile |
| version | integer | not null | 版本号 |
| config_json | json | not null | 配置快照 |
| checksum | varchar(64) | not null | sha256 校验和 |
| published_by | bigint unsigned | null fk admins.admin_id | 发布人 |
| published_at | timestamp | null | 发布时间 |
| created_at | timestamp | null | 创建时间 |

索引：

```sql
CREATE UNIQUE INDEX uniq_profile_version ON profile_versions (target_scope, target_node_id, target_profile_id, version);
CREATE INDEX idx_profile_versions_node ON profile_versions (target_node_id);
CREATE INDEX idx_profile_versions_profile ON profile_versions (target_profile_id);
```

### 5.7 policy_snapshots

| 字段 | 类型 | 约束 | 说明 |
|---|---|---|---|
| id | bigint unsigned | pk auto_increment | 快照 ID |
| user_id | bigint unsigned | not null fk users.uid | 用户 |
| profile_id | bigint unsigned | not null fk profiles.id | Profile |
| version | integer | not null | 版本号 |
| snapshot_json | json | not null | 快照 JSON |
| checksum | varchar(64) | not null | 校验和 |
| status | varchar(20) | not null default 'draft' | 状态（000062 补充） |
| published_at | timestamp | null | 发布时间 |
| created_at | timestamp | null | 创建时间 |

索引：

```sql
CREATE UNIQUE INDEX uniq_policy_snapshot ON policy_snapshots (profile_id, version);
CREATE INDEX idx_policy_snapshots_user ON policy_snapshots (user_id);
```

### 5.8 policy_publish_logs

| 字段 | 类型 | 约束 | 说明 |
|---|---|---|---|
| id | bigint unsigned | pk auto_increment | 日志 ID |
| profile_id | bigint unsigned | not null fk profiles.id | Profile |
| policy_snapshot_id | bigint unsigned | null fk policy_snapshots.id | 快照 |
| action | enum('publish','rollback','republish') | not null default 'publish' | 动作 |
| status | enum('pending','success','failed') | not null default 'pending' | 状态 |
| target_node_count | integer | not null default 0 | 目标节点数 |
| success_node_count | integer | not null default 0 | 成功节点数 |
| error_message | varchar(500) | null | 错误信息 |
| published_by | bigint unsigned | null fk admins.admin_id | 发布人 |
| published_at | timestamp | null | 发布时间 |
| created_at | timestamp | null | 创建时间 |

索引：

```sql
CREATE INDEX idx_policy_logs_profile ON policy_publish_logs (profile_id);
CREATE INDEX idx_policy_logs_snapshot ON policy_publish_logs (policy_snapshot_id);
```

## 6. 套餐与订阅表

### 6.1 plans

| 字段 | 类型 | 约束 | 说明 |
|---|---|---|---|
| id | bigint unsigned | pk auto_increment | 套餐 ID |
| code | varchar(40) | not null unique | `free` / `pro` / `business` / `education` / `enterprise` |
| name | varchar(120) | not null | 名称 |
| description | varchar(500) | null | 描述 |
| category | enum('free','pro','business','education','enterprise') | not null default 'free' | 套餐分类 |
| status | enum('active','archived') | not null default 'active' | 状态 |
| monthly_query_limit | bigint unsigned | null | 月查询上限 |
| profiles_limit | integer | null | Profile 上限 |
| devices_limit | integer | null | 设备上限 |
| log_retention_days | integer | not null default 24 | 日志保留天数 |
| sort_order | integer | not null default 0 | 排序权重（000058 补充） |
| is_featured | boolean | not null default false | 是否推荐（000058 补充） |
| badge | varchar(60) | null | 徽章标签（000058 补充） |
| features | json | null | 功能开关列表（000058 补充） |
| limits | json | null | 限制配置（000058 补充） |
| created_at | timestamp | not null | 创建时间 |
| updated_at | timestamp | not null | 更新时间 |

### 6.2 plan_prices

| 字段 | 类型 | 约束 | 说明 |
|---|---|---|---|
| id | bigint unsigned | pk auto_increment | 价格 ID |
| plan_id | bigint unsigned | not null fk plans.id | 套餐 |
| billing_cycle | enum('monthly','yearly') | not null default 'monthly' | 计费周期 |
| currency | char(3) | not null default 'USD' | 币种 |
| amount_minor | bigint unsigned | not null | 金额（最小货币单位） |
| original_amount_minor | bigint unsigned | null | 原价（000059 补充） |
| status | varchar(20) | not null default 'active' | 状态（000059 补充） |
| stripe_price_id | varchar(120) | null | Stripe Price ID |
| is_active | boolean | not null default true | 是否启用 |
| created_at | timestamp | not null | 创建时间 |
| updated_at | timestamp | not null | 更新时间 |

索引：

```sql
CREATE UNIQUE INDEX uniq_plan_price ON plan_prices (plan_id, billing_cycle, currency);
```

### 6.3 plan_features

| 字段 | 类型 | 约束 | 说明 |
|---|---|---|---|
| id | bigint unsigned | pk auto_increment | 功能 ID |
| plan_id | bigint unsigned | not null fk plans.id | 套餐 |
| feature_key | varchar(80) | not null | 功能 key |
| feature_value | varchar(255) | null | 功能值 |
| enabled | boolean | not null default true | 是否启用 |
| created_at | timestamp | not null | 创建时间 |
| updated_at | timestamp | not null | 更新时间 |

索引：

```sql
CREATE UNIQUE INDEX uniq_plan_feature ON plan_features (plan_id, feature_key);
```

### 6.4 subscriptions

| 字段 | 类型 | 约束 | 说明 |
|---|---|---|---|
| id | bigint unsigned | pk auto_increment | 订阅 ID |
| order_id | bigint unsigned | null unique fk orders.id | 关联订单 |
| user_id | bigint unsigned | not null | 用户 |
| plan_id | bigint unsigned | not null | 套餐 |
| plan_code | varchar(50) | null | 套餐 code 快照（000062 补充） |
| status | enum('pending','active','past_due','cancelled','expired') | not null default 'pending' | 状态 |
| auto_renew | boolean | not null default true | 自动续费 |
| started_at | timestamp | null | 开始时间 |
| current_period_start | timestamp | null | 当前周期开始 |
| current_period_end | timestamp | null | 当前周期结束 |
| cancelled_at | timestamp | null | 取消时间 |
| expired_at | timestamp | null | 过期时间 |
| created_at | timestamp | not null | 创建时间 |
| updated_at | timestamp | not null | 更新时间 |

索引：

```sql
CREATE UNIQUE INDEX uniq_subscriptions_order ON subscriptions (order_id);
CREATE INDEX idx_subscriptions_user ON subscriptions (user_id);
CREATE INDEX idx_subscriptions_plan ON subscriptions (plan_id);
CREATE INDEX idx_subscriptions_status ON subscriptions (status);
```

### 6.5 orders

| 字段 | 类型 | 约束 | 说明 |
|---|---|---|---|
| id | bigint unsigned | pk auto_increment | 订单 ID |
| order_no | varchar(40) | not null unique | 订单编号 |
| user_id | bigint unsigned | not null | 用户 |
| plan_id | bigint unsigned | null | 套餐（可为 null，如钱包充值）（000057 改） |
| plan_price_id | bigint unsigned | null | 套餐价格（可为 null）（000057 改） |
| billing_cycle | enum('monthly','yearly') | not null default 'monthly' | 计费周期 |
| currency | char(3) | not null default 'USD' | 币种 |
| plan_code_snapshot | varchar(40) | not null | 套餐 code 快照 |
| original_amount_minor | bigint unsigned | not null | 原价 |
| discount_amount_minor | bigint unsigned | not null default 0 | 折扣金额 |
| payable_amount_minor | bigint unsigned | not null | 应付金额 |
| idempotency_key | varchar(80) | not null unique | 幂等键 |
| status | enum('pending','paid','cancelled','refunded','failed','expired') | not null default 'pending' | 状态 |
| provider | varchar(40) | null | 支付渠道 |
| paid_at | timestamp | null | 支付时间 |
| cancelled_at | timestamp | null | 取消时间 |
| refunded_at | timestamp | null | 退款时间 |
| meta | json | null | 元数据 |
| created_at | timestamp | not null | 创建时间 |
| updated_at | timestamp | not null | 更新时间 |

索引：

```sql
CREATE UNIQUE INDEX uniq_orders_no ON orders (order_no);
CREATE UNIQUE INDEX uniq_orders_idempotency ON orders (idempotency_key);
CREATE INDEX idx_orders_user ON orders (user_id);
CREATE INDEX idx_orders_plan ON orders (plan_id);
CREATE INDEX idx_orders_status ON orders (status);
```

## 7. 支付与财务表

### 7.1 payment_transactions

| 字段 | 类型 | 约束 | 说明 |
|---|---|---|---|
| id | bigint unsigned | pk auto_increment | 交易 ID |
| order_id | bigint unsigned | not null | 订单 |
| user_id | bigint unsigned | not null | 用户 |
| provider | varchar(40) | not null | 支付渠道 |
| provider_session_id | varchar(255) | null | 渠道 session ID |
| provider_payment_intent_id | varchar(255) | null | 渠道支付 intent ID |
| provider_charge_id | varchar(255) | null | 渠道扣款 ID |
| currency | char(3) | not null default 'USD' | 币种 |
| amount_minor | bigint unsigned | not null | 金额（最小货币单位） |
| status | enum('created','processing','succeeded','failed','cancelled','refunded') | not null default 'created' | 状态 |
| failure_code | varchar(80) | null | 失败代码 |
| failure_message | varchar(500) | null | 失败信息 |
| raw_payload | json | null | 原始回调数据 |
| created_at | timestamp | not null | 创建时间 |
| updated_at | timestamp | not null | 更新时间 |

索引：

```sql
CREATE INDEX uniq_pt_session ON payment_transactions (provider, provider_session_id);
CREATE INDEX uniq_pt_intent ON payment_transactions (provider, provider_payment_intent_id);
CREATE INDEX idx_pt_order ON payment_transactions (order_id);
CREATE INDEX idx_pt_user ON payment_transactions (user_id);
CREATE INDEX idx_pt_status ON payment_transactions (status);
```

### 7.2 stripe_webhook_logs

| 字段 | 类型 | 约束 | 说明 |
|---|---|---|---|
| id | bigint unsigned | pk auto_increment | 日志 ID |
| event_id | varchar(120) | not null unique | Stripe Event ID |
| event_type | varchar(80) | not null | 事件类型 |
| payload | json | not null | 原始 payload |
| signature_ok | boolean | not null default false | 签名验证结果 |
| status | enum('received','processed','failed','ignored') | not null default 'received' | 处理状态 |
| error_message | varchar(500) | null | 错误信息 |
| received_at | timestamp | not null | 接收时间 |
| processed_at | timestamp | null | 处理时间 |

索引：

```sql
CREATE UNIQUE INDEX uniq_stripe_event ON stripe_webhook_logs (event_id);
CREATE INDEX idx_stripe_status ON stripe_webhook_logs (status);
CREATE INDEX idx_stripe_type ON stripe_webhook_logs (event_type);
```

### 7.3 wallets

| 字段 | 类型 | 约束 | 说明 |
|---|---|---|---|
| id | bigint unsigned | pk auto_increment | 钱包 ID |
| user_id | bigint unsigned | not null unique fk users.uid | 用户 |
| balance_minor | bigint unsigned | not null default 0 | 余额（最小货币单位） |
| frozen_minor | bigint unsigned | not null default 0 | 冻结金额 |
| currency | char(3) | not null default 'USD' | 币种 |
| status | enum('active','frozen','closed') | not null default 'active' | 状态 |
| created_at | timestamp | not null | 创建时间 |
| updated_at | timestamp | not null | 更新时间 |

### 7.4 wallet_transactions

| 字段 | 类型 | 约束 | 说明 |
|---|---|---|---|
| id | bigint unsigned | pk auto_increment | 交易 ID |
| wallet_id | bigint unsigned | not null | 钱包 |
| user_id | bigint unsigned | not null | 用户 |
| billing_id | bigint unsigned | null | 账单 |
| transaction_no | varchar(40) | not null unique | 交易编号 |
| type | enum('credit','debit','refund','adjustment') | not null | 交易类型 |
| amount_minor | bigint unsigned | not null | 金额 |
| currency | char(3) | not null default 'USD' | 币种 |
| balance_after_minor | bigint unsigned | not null | 交易后余额 |
| source | enum('topup','subscription','usage','refund','manual') | not null default 'topup' | 来源 |
| description | varchar(255) | null | 描述 |
| idempotency_key | varchar(80) | not null unique | 幂等键 |
| status | enum('pending','succeeded','failed','cancelled') | not null default 'pending' | 状态 |
| created_at | timestamp | not null | 创建时间 |
| updated_at | timestamp | not null | 更新时间 |

索引：

```sql
CREATE UNIQUE INDEX uniq_wallet_tx_no ON wallet_transactions (transaction_no);
CREATE UNIQUE INDEX uniq_wallet_tx_idempotency ON wallet_transactions (idempotency_key);
CREATE INDEX idx_wallet_tx_wallet ON wallet_transactions (wallet_id);
CREATE INDEX idx_wallet_tx_user ON wallet_transactions (user_id);
CREATE INDEX idx_wallet_tx_billing ON wallet_transactions (billing_id);
```

### 7.5 billing_periods

| 字段 | 类型 | 约束 | 说明 |
|---|---|---|---|
| id | bigint unsigned | pk auto_increment | 账期 ID |
| user_id | bigint unsigned | not null | 用户 |
| period_start | datetime | not null | 账期开始 |
| period_end | datetime | not null | 账期结束 |
| status | enum('open','closed','billed') | not null default 'open' | 状态 |
| closed_at | timestamp | null | 关闭时间 |
| created_at | timestamp | not null | 创建时间 |
| updated_at | timestamp | not null | 更新时间 |

索引：

```sql
CREATE UNIQUE INDEX uniq_billing_period ON billing_periods (user_id, period_start, period_end);
CREATE INDEX idx_billing_periods_user ON billing_periods (user_id);
CREATE INDEX idx_billing_periods_status ON billing_periods (status);
```

### 7.6 billings

| 字段 | 类型 | 约束 | 说明 |
|---|---|---|---|
| id | bigint unsigned | pk auto_increment | 账单 ID |
| billing_no | varchar(40) | not null unique | 账单编号 |
| user_id | bigint unsigned | not null | 用户 |
| subscription_id | bigint unsigned | null | 订阅 |
| billing_period_id | bigint unsigned | null | 账期 |
| currency | char(3) | not null default 'USD' | 币种 |
| subtotal_minor | bigint unsigned | not null default 0 | 小计 |
| discount_minor | bigint unsigned | not null default 0 | 折扣 |
| tax_minor | bigint unsigned | not null default 0 | 税费 |
| total_minor | bigint unsigned | not null | 总计 |
| status | enum('draft','pending','paid','overdue','cancelled') | not null default 'draft' | 状态 |
| issued_at | timestamp | null | 发出时间 |
| due_at | timestamp | null | 到期时间 |
| paid_at | timestamp | null | 支付时间 |
| cancelled_at | timestamp | null | 取消时间 |
| meta | json | null | 元数据 |
| created_at | timestamp | not null | 创建时间 |
| updated_at | timestamp | not null | 更新时间 |

索引：

```sql
CREATE UNIQUE INDEX uniq_billings_no ON billings (billing_no);
CREATE INDEX idx_billings_user ON billings (user_id);
CREATE INDEX idx_billings_subscription ON billings (subscription_id);
CREATE INDEX idx_billings_period ON billings (billing_period_id);
CREATE INDEX idx_billings_status ON billings (status);
```

### 7.7 billing_items

| 字段 | 类型 | 约束 | 说明 |
|---|---|---|---|
| id | bigint unsigned | pk auto_increment | 账单项 ID |
| billing_id | bigint unsigned | not null fk billings.id | 账单 |
| item_type | enum('subscription','usage','wallet_topup','credit','adjustment') | not null | 项目类型 |
| source_type | enum('subscription','usage_record','wallet_transaction') | null | 来源类型 |
| source_id | bigint unsigned | null | 来源 ID |
| description | varchar(255) | null | 描述 |
| quantity | decimal(20,4) | not null default 1 | 数量 |
| unit_price_minor | bigint unsigned | not null default 0 | 单价 |
| amount_minor | bigint unsigned | not null | 金额 |
| created_at | timestamp | not null | 创建时间 |
| updated_at | timestamp | not null | 更新时间 |

索引：

```sql
CREATE INDEX idx_billing_items_billing ON billing_items (billing_id);
CREATE INDEX idx_billing_items_source ON billing_items (source_type, source_id);
```

### 7.8 usage_records

| 字段 | 类型 | 约束 | 说明 |
|---|---|---|---|
| id | bigint unsigned | pk auto_increment | 用量记录 ID |
| user_id | bigint unsigned | not null fk users.uid | 用户 |
| profile_id | bigint unsigned | not null fk profiles.id | Profile |
| device_id | bigint unsigned | null | 设备 |
| billing_category | varchar(30) | not null default 'query' | 计费分类 |
| billing_period_id | bigint unsigned | not null fk billing_periods.id | 账期 |
| query_count | bigint unsigned | not null default 0 | 查询次数 |
| blocked_count | bigint unsigned | not null default 0 | 拦截次数 |
| amount_minor | bigint unsigned | not null default 0 | 金额 |
| currency | char(3) | not null default 'USD' | 币种 |
| last_aggregated_at | timestamp | null | 最后聚合时间 |
| created_at | timestamp | not null | 创建时间 |
| updated_at | timestamp | not null | 更新时间 |

索引：

```sql
CREATE UNIQUE INDEX uniq_usage_aggregate ON usage_records (user_id, profile_id, device_id, billing_category, billing_period_id);
CREATE INDEX idx_usage_user ON usage_records (user_id);
CREATE INDEX idx_usage_profile ON usage_records (profile_id);
CREATE INDEX idx_usage_device ON usage_records (device_id);
CREATE INDEX idx_usage_period ON usage_records (billing_period_id);
```

## 8. 团队表

### 8.1 teams

| 字段 | 类型 | 约束 | 说明 |
|---|---|---|---|
| id | bigint unsigned | pk auto_increment | 团队 ID |
| owner_id | bigint unsigned | not null fk users.uid | 团队创建者 |
| name | varchar(160) | not null | 团队名称 |
| slug | varchar(120) | not null unique | 唯一标识 |
| description | varchar(500) | null | 说明 |
| status | enum('active','archived') | not null default 'active' | 状态 |
| member_count | unsigned integer | not null default 0 | 成员数（000061 补充） |
| max_members | unsigned integer | null | 成员上限（000061 补充） |
| created_at | timestamp | not null | 创建时间 |
| updated_at | timestamp | not null | 更新时间 |

索引：

```sql
CREATE UNIQUE INDEX uniq_teams_slug ON teams (slug);
CREATE INDEX idx_teams_owner ON teams (owner_id);
```

### 8.2 team_members

| 字段 | 类型 | 约束 | 说明 |
|---|---|---|---|
| id | bigint unsigned | pk auto_increment | 成员 ID |
| team_id | bigint unsigned | not null fk teams.id | 团队 |
| user_id | bigint unsigned | not null fk users.uid | 用户 |
| role_key | varchar(40) | not null default 'member' | `owner` / `admin` / `member` |
| joined_at | timestamp | null | 加入时间 |
| created_at | timestamp | not null | 创建时间 |
| updated_at | timestamp | not null | 更新时间 |

索引：

```sql
CREATE UNIQUE INDEX uniq_team_member ON team_members (team_id, user_id);
CREATE INDEX idx_team_members_user ON team_members (user_id);
```

### 8.3 team_invitations

| 字段 | 类型 | 约束 | 说明 |
|---|---|---|---|
| id | bigint unsigned | pk auto_increment | 邀请 ID |
| team_id | bigint unsigned | not null fk teams.id | 团队 |
| inviter_id | bigint unsigned | not null fk users.uid | 邀请人 |
| email | varchar(190) | not null | 被邀请人邮箱 |
| role_key | varchar(40) | not null default 'member' | 邀请角色 |
| token_hash | char(64) | not null unique | 邀请令牌 SHA256 哈希 |
| expires_at | timestamp | not null | 过期时间 |
| accepted_at | timestamp | null | 接受时间 |
| revoked_at | timestamp | null | 撤销时间 |
| declined_at | timestamp | null | 拒绝时间（000062 补充） |
| created_at | timestamp | not null | 创建时间 |
| updated_at | timestamp | not null | 更新时间 |

索引：

```sql
CREATE UNIQUE INDEX uniq_team_invite_token ON team_invitations (token_hash);
CREATE INDEX idx_team_invite_email ON team_invitations (email);
```

### 8.4 team_roles

| 字段 | 类型 | 约束 | 说明 |
|---|---|---|---|
| id | bigint unsigned | pk auto_increment | 角色 ID |
| team_id | bigint unsigned | not null fk teams.id | 团队 |
| code | varchar(40) | not null | 角色编码 |
| name | varchar(120) | not null | 角色名称 |
| description | varchar(500) | null | 描述 |
| created_at | timestamp | not null | 创建时间 |
| updated_at | timestamp | not null | 更新时间 |

索引：

```sql
CREATE UNIQUE INDEX uniq_team_role_code ON team_roles (team_id, code);
```

### 8.5 team_permissions

| 字段 | 类型 | 约束 | 说明 |
|---|---|---|---|
| id | bigint unsigned | pk auto_increment | 权限 ID |
| code | varchar(80) | not null unique | 权限编码 |
| resource | varchar(80) | not null | 资源 |
| action | varchar(80) | not null | 操作 |
| description | varchar(300) | null | 描述 |
| created_at | timestamp | not null | 创建时间 |
| updated_at | timestamp | not null | 更新时间 |

索引：

```sql
CREATE UNIQUE INDEX uniq_team_perm_code ON team_permissions (code);
CREATE INDEX idx_team_perm_resource_action ON team_permissions (resource, action);
```

### 8.6 team_role_permissions

| 字段 | 类型 | 约束 | 说明 |
|---|---|---|---|
| id | bigint unsigned | pk auto_increment | 关联 ID |
| team_role_id | bigint unsigned | not null fk team_roles.id | 团队角色 |
| team_permission_id | bigint unsigned | not null fk team_permissions.id | 团队权限 |
| created_at | timestamp | not null | 创建时间 |
| updated_at | timestamp | not null | 更新时间 |

索引：

```sql
CREATE UNIQUE INDEX uniq_team_role_perm ON team_role_permissions (team_role_id, team_permission_id);
CREATE INDEX idx_team_role_perm_perm ON team_role_permissions (team_permission_id);
```

### 8.7 team_user_roles

| 字段 | 类型 | 约束 | 说明 |
|---|---|---|---|
| id | bigint unsigned | pk auto_increment | 关联 ID |
| team_id | bigint unsigned | not null fk teams.id | 团队 |
| user_id | bigint unsigned | not null fk users.uid | 用户 |
| team_role_id | bigint unsigned | not null fk team_roles.id | 团队角色 |
| created_at | timestamp | not null | 创建时间 |
| updated_at | timestamp | not null | 更新时间 |

索引：

```sql
CREATE UNIQUE INDEX uniq_team_user_role ON team_user_roles (team_id, user_id, team_role_id);
CREATE INDEX idx_team_user_role_role ON team_user_roles (team_role_id);
```

### 8.8 api_keys

> API Key，用于 OpenAPI 调用。

| 字段 | 类型 | 约束 | 说明 |
|---|---|---|---|
| id | bigint unsigned | pk auto_increment | Key ID |
| user_id | bigint unsigned | not null fk users.uid | 所属用户 |
| name | varchar(100) | not null | 名称 |
| key_hash | varchar(64) | not null | Key hash |
| key_prefix | varchar(20) | not null | Key 前缀（明文标识） |
| status | varchar(20) | not null default 'active' | 状态 |
| scopes | json | not null | 权限范围 |
| last_used_at | timestamp | null | 最后使用 |
| expires_at | timestamp | null | 过期时间 |
| created_at | timestamp | not null | 创建时间 |
| updated_at | timestamp | not null | 更新时间 |

索引：

```sql
CREATE INDEX idx_api_keys_user_id ON api_keys (user_id);
CREATE INDEX idx_api_keys_key_prefix ON api_keys (key_prefix);
CREATE INDEX idx_api_keys_status ON api_keys (status);
```

## 9. 基础设施表

### 9.1 nodes

> DNS 解析节点。由 console 域管理，portal-web 业务代码只读引用。

| 字段 | 类型 | 约束 | 说明 |
|---|---|---|---|
| id | bigint unsigned | pk auto_increment | 节点 ID |
| node_code | varchar(64) | not null unique | 节点编码 |
| name | varchar(120) | not null | 名称 |
| region | varchar(40) | null | 区域 |
| country | varchar(8) | null | 国家 |
| city | varchar(80) | null | 城市 |
| public_ipv4 | varchar(45) | null | 公网 IPv4 |
| public_ipv6 | varchar(64) | null | 公网 IPv6 |
| supported_protocols | json | null | 支持的协议 |
| status | enum('pending','online','offline','degraded','maintenance','disabled','retired') | not null default 'pending' | 状态 |
| desired_config_version | integer | not null default 1 | 期望配置版本 |
| current_config_version | integer | not null default 0 | 当前配置版本 |
| capacity_qps | unsigned integer | not null default 5000 | 容量 QPS（000065 补充） |
| last_heartbeat_at | timestamp | null | 最后心跳 |
| last_log_flush_at | timestamp | null | 最后日志刷新 |
| meta | json | null | 元数据 |
| created_by_admin_id | bigint unsigned | null fk admins.admin_id | 创建人 |
| created_at | timestamp | not null | 创建时间 |
| updated_at | timestamp | not null | 更新时间 |

索引：

```sql
CREATE UNIQUE INDEX uniq_nodes_code ON nodes (node_code);
CREATE INDEX idx_nodes_status ON nodes (status);
CREATE INDEX idx_nodes_region ON nodes (region);
```

### 9.2 node_tokens

| 字段 | 类型 | 约束 | 说明 |
|---|---|---|---|
| id | bigint unsigned | pk auto_increment | Token ID |
| node_id | bigint unsigned | not null fk nodes.id | 节点 |
| token_prefix | varchar(20) | not null unique | Token 前缀 |
| token_hash | char(64) | not null unique | Token SHA256 哈希 |
| hmac_key_hash | varchar(128) | null | HMAC key hash |
| hmac_secret_encrypted | text | null | HMAC secret 加密存储 |
| scopes | json | null | 权限范围 |
| status | enum('active','revoked','expired') | not null default 'active' | 状态 |
| last_used_at | timestamp | null | 最后使用 |
| expires_at | timestamp | null | 过期时间 |
| revoked_at | timestamp | null | 撤销时间 |
| revoke_reason | varchar(255) | null | 撤销原因 |
| created_by_admin_id | bigint unsigned | null fk admins.admin_id | 创建人 |
| created_at | timestamp | not null | 创建时间 |
| updated_at | timestamp | not null | 更新时间 |

索引：

```sql
CREATE UNIQUE INDEX uniq_node_tokens_prefix ON node_tokens (token_prefix);
CREATE UNIQUE INDEX uniq_node_tokens_hash ON node_tokens (token_hash);
CREATE INDEX idx_node_tokens_node ON node_tokens (node_id);
```

### 9.3 node_heartbeats

| 字段 | 类型 | 约束 | 说明 |
|---|---|---|---|
| id | bigint unsigned | pk auto_increment | 心跳 ID |
| node_id | bigint unsigned | not null fk nodes.id | 节点 |
| status | enum('online','degraded','offline') | not null default 'online' | 状态 |
| uptime_seconds | bigint unsigned | not null default 0 | 运行时长 |
| version | varchar(40) | null | 软件版本 |
| current_config_version | integer | not null default 0 | 当前配置版本 |
| profiles_loaded | integer | not null default 0 | 已加载 Profile 数 |
| last_config_pull_at | timestamp | null | 最后拉取配置时间 |
| last_log_flush_at | timestamp | null | 最后刷新日志时间 |
| reported_at | timestamp | null | 上报时间 |
| created_at | timestamp | null | 创建时间 |

索引：

```sql
CREATE INDEX idx_node_heartbeats_node ON node_heartbeats (node_id);
CREATE INDEX idx_node_heartbeats_reported ON node_heartbeats (reported_at);
```

### 9.4 geo_dns_mappings

| 字段 | 类型 | 约束 | 说明 |
|---|---|---|---|
| id | bigint unsigned | pk auto_increment | 映射 ID |
| domain | varchar(255) | not null | 域名 |
| country | varchar(8) | null | 国家 |
| region | varchar(40) | null | 区域 |
| target_node_id | bigint unsigned | null fk nodes.id | 目标节点 |
| target_endpoint | varchar(255) | null | 目标端点 |
| priority | unsigned integer | not null default 0 | 优先级（000062 补充） |
| weight | integer | not null default 100 | 权重 |
| enabled | boolean | not null default true | 是否启用 |
| created_at | timestamp | not null | 创建时间 |
| updated_at | timestamp | not null | 更新时间 |

索引：

```sql
CREATE INDEX idx_geo_domain ON geo_dns_mappings (domain);
CREATE INDEX idx_geo_country ON geo_dns_mappings (country);
CREATE INDEX idx_geo_node ON geo_dns_mappings (target_node_id);
```

### 9.5 regions

| 字段 | 类型 | 约束 | 说明 |
|---|---|---|---|
| id | bigint unsigned | pk auto_increment | 区域 ID |
| code | varchar(20) | not null unique | 区域编码，如 KR, JP |
| name | varchar(100) | not null | 区域名称 |
| status | varchar(20) | not null default 'active' | 状态 |
| note | varchar(255) | null | 备注 |
| created_at | timestamp | not null | 创建时间 |
| updated_at | timestamp | not null | 更新时间 |

### 9.6 publish_tasks

| 字段 | 类型 | 约束 | 说明 |
|---|---|---|---|
| id | bigint unsigned | pk auto_increment | 任务 ID |
| profile_version_id | bigint unsigned | not null fk profile_versions.id | Profile 版本 |
| profile_id | bigint unsigned | null fk profiles.id | Profile |
| status | enum('queued','running','succeeded','partial','failed') | not null default 'queued' | 状态 |
| target_scope | enum('all_nodes','profile','tag','node') | not null default 'all_nodes' | 发布范围 |
| target_filter | json | null | 目标过滤条件 |
| target_node_count | integer | not null default 0 | 目标节点数 |
| applied_node_count | integer | not null default 0 | 已应用节点数 |
| failed_node_count | integer | not null default 0 | 失败节点数 |
| retry_count | integer | not null default 0 | 重试次数 |
| message | varchar(500) | null | 消息 |
| latest_error | text | null | 最新错误 |
| queued_at | timestamp | null | 入队时间 |
| started_at | timestamp | null | 开始时间 |
| completed_at | timestamp | null | 完成时间 |
| created_at | timestamp | not null | 创建时间 |
| updated_at | timestamp | not null | 更新时间 |

索引：

```sql
CREATE INDEX idx_publish_tasks_status ON publish_tasks (status);
CREATE INDEX idx_publish_tasks_pv ON publish_tasks (profile_version_id);
```

### 9.7 task_executions

> 使用字符串主键（PublishService 生成 `texec_xxx`），与模型 `$keyType='string'` 对齐。

| 字段 | 类型 | 约束 | 说明 |
|---|---|---|---|
| id | varchar(32) | pk | 执行 ID（字符串主键） |
| publish_task_id | bigint unsigned | not null fk publish_tasks.id | 发布任务 |
| node_id | bigint unsigned | not null fk nodes.id | 节点 |
| config_version | integer | not null default 0 | 配置版本 |
| status | enum('pending','pulled','applied','ack','failed','skipped') | not null default 'pending' | 状态 |
| checksum | varchar(128) | null | 校验和 |
| error_code | varchar(64) | null | 错误代码 |
| error_message | text | null | 错误信息 |
| pulled_at | timestamp | null | 拉取时间 |
| applied_at | timestamp | null | 应用时间 |
| last_seen_at | timestamp | null | 最后可见时间 |
| created_at | timestamp | not null | 创建时间 |
| updated_at | timestamp | not null | 更新时间 |

索引：

```sql
CREATE INDEX idx_task_exec_pubtask ON task_executions (publish_task_id);
CREATE INDEX idx_task_exec_node ON task_executions (node_id);
CREATE INDEX idx_task_exec_status ON task_executions (status);
```

## 10. 运维与系统表

### 10.1 system_configs

| 字段 | 类型 | 约束 | 说明 |
|---|---|---|---|
| id | bigint unsigned | pk auto_increment | 配置 ID |
| config_key | varchar(120) | not null unique | 配置 key |
| config_value | json | null | 配置值 |
| description | varchar(500) | null | 描述 |
| is_secret | boolean | not null default false | 是否敏感配置 |
| created_at | timestamp | not null | 创建时间 |
| updated_at | timestamp | not null | 更新时间 |

### 10.2 alerts

| 字段 | 类型 | 约束 | 说明 |
|---|---|---|---|
| id | bigint unsigned | pk auto_increment | 告警 ID |
| code | varchar(80) | not null | 告警编码 |
| level | enum('info','warning','error','critical') | not null default 'warning' | 级别 |
| source | enum('node','billing','usage','system','security') | not null default 'system' | 来源 |
| subject_type | varchar(80) | null | 主体类型 |
| subject_id | bigint unsigned | null | 主体 ID |
| title | varchar(200) | not null | 标题 |
| message | text | null | 消息 |
| payload | json | null | 载荷 |
| status | enum('open','acknowledged','resolved','closed') | not null default 'open' | 状态 |
| acknowledged_by | bigint unsigned | null fk admins.admin_id | 确认人 |
| acknowledged_at | timestamp | null | 确认时间 |
| resolved_at | timestamp | null | 解决时间 |
| created_at | timestamp | not null | 创建时间 |
| updated_at | timestamp | not null | 更新时间 |

索引：

```sql
CREATE INDEX idx_alerts_code ON alerts (code);
CREATE INDEX idx_alerts_status ON alerts (status);
CREATE INDEX idx_alerts_subject ON alerts (subject_type, subject_id);
```

### 10.3 aggregation_offsets

| 字段 | 类型 | 约束 | 说明 |
|---|---|---|---|
| id | bigint unsigned | pk auto_increment | 偏移 ID |
| topic | varchar(80) | not null | 主题 |
| window_start | datetime | not null | 窗口开始时间 |
| processed_at | datetime | not null | 处理时间 |
| record_count | bigint unsigned | not null default 0 | 记录数 |
| status | enum('pending','processing','done','failed') | not null default 'pending' | 状态 |
| error_message | varchar(500) | null | 错误信息 |
| created_at | timestamp | not null | 创建时间 |
| updated_at | timestamp | not null | 更新时间 |

索引：

```sql
CREATE UNIQUE INDEX uniq_agg_topic_window ON aggregation_offsets (topic, window_start);
CREATE INDEX idx_agg_status ON aggregation_offsets (status);
```

### 10.4 job_executions

| 字段 | 类型 | 约束 | 说明 |
|---|---|---|---|
| id | bigint unsigned | pk auto_increment | 执行 ID |
| job_name | varchar(80) | not null | 任务名称 |
| started_at | datetime | not null | 开始时间 |
| finished_at | datetime | null | 结束时间 |
| status | enum('running','succeeded','failed') | not null default 'running' | 状态 |
| duration_ms | integer | null | 执行耗时（毫秒） |
| error_message | varchar(1000) | null | 错误信息 |
| meta | json | null | 元数据 |

索引：

```sql
CREATE INDEX idx_job_exec_name ON job_executions (job_name);
CREATE INDEX idx_job_exec_status ON job_executions (status);
```

## 11. Laravel 框架表

### 11.1 cache

| 字段 | 类型 | 约束 | 说明 |
|---|---|---|---|
| key | varchar(255) | pk | 缓存 key |
| value | longtext | not null | 缓存值 |
| expiration | integer | not null | 过期时间 |

### 11.2 cache_locks

| 字段 | 类型 | 约束 | 说明 |
|---|---|---|---|
| key | varchar(255) | pk | 锁 key |
| owner | varchar(255) | not null | 锁持有者 |
| expiration | integer | not null | 过期时间 |

### 11.3 jobs

| 字段 | 类型 | 约束 | 说明 |
|---|---|---|---|
| id | bigint unsigned | pk auto_increment | Job ID |
| queue | varchar(120) | not null | 队列名 |
| payload | longtext | not null | 载荷 |
| attempts | tinyint unsigned | not null | 尝试次数 |
| reserved_at | integer unsigned | null | 预留时间 |
| available_at | integer unsigned | not null | 可用时间 |
| created_at | integer unsigned | not null | 创建时间 |

索引：

```sql
CREATE INDEX idx_jobs_queue ON jobs (queue);
```

### 11.4 failed_jobs

| 字段 | 类型 | 约束 | 说明 |
|---|---|---|---|
| id | bigint unsigned | pk auto_increment | 失败 Job ID |
| uuid | varchar(120) | not null unique | UUID |
| connection | text | not null | 连接 |
| queue | text | not null | 队列 |
| payload | longtext | not null | 载荷 |
| exception | longtext | not null | 异常 |
| failed_at | timestamp | not null | 失败时间 |