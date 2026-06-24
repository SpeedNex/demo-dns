# 完整性检查清单（Completeness Checklist）

> AI 生成各系统规格文档后，必须逐条对照此清单检查，遗漏项必须补充，禁止跳过。

---

## 通用检查（所有系统适用）

- [ ] 该系统的数据模型是否完整覆盖其所有业务模块？
- [ ] 该系统的 API/协议是否覆盖其所有对外交互？
- [ ] 每个端点是否有明确的请求参数和响应格式？
- [ ] 每个数据表是否有主键、外键、索引定义？
- [ ] 是否有统一错误处理/响应格式？
- [ ] 是否有分页/限流/鉴权说明？

---

## 1. Laravel / PHP Web 系统检查

### 数据表完整性

**用户与认证模块**：
- [ ] `users` — 用户主表（email, password_hash, status, locale, timezone, last_login_at, email_verified_at）
- [ ] `password_resets` — 密码重置
- [ ] `personal_access_tokens` / `sessions` — API 认证令牌

**核心业务模块**：
- [ ] `profiles` — Profile 配置单元（user_id, team_id, profile_uid, name, status, current_version）
- [ ] `profile_versions` — 版本快照（profile_id, version, config_json, checksum, status）

**规则模块**：
- [ ] `allow_lists` — 白名单（profile_id, domain, match_type, enabled）
- [ ] `deny_lists` — 黑名单
- [ ] `custom_rules` — 用户自定义规则
- [ ] `rule_sources` — 规则订阅源（name, url, type, enabled, version）

**设备模块**：
- [ ] `devices` — 设备（profile_id, device_uid, name, type, last_ip, last_seen_at）

**团队模块**：
- [ ] `teams` — 团队（owner_id, name, status）
- [ ] `team_members` — 团队成员（team_id, user_id, role, status）
- [ ] `team_invitations` — 团队邀请

**计费模块**：
- [ ] `plans` — 套餐定义（name, price, monthly_quota, max_profiles, max_devices, features）
- [ ] `subscriptions` — 订阅（user_id, plan_id, status, started_at, expired_at）
- [ ] `orders` — 订单（amount_minor, currency, status, idempotency_key）
- [ ] `invoices` — 发票（amount_minor 字段、finalized 后不可变）
- [ ] `usage_records` — 用量记录（user_id, month, query_count）

**API 与集成**：
- [ ] `api_keys` — API Key（user_id, name, key_hash, permissions）

**通知模块**：
- [ ] `notifications` — 通知（user_id, type, title, body, read_at）
- [ ] `notification_settings` — 通知设置

**审计模块**：
- [ ] `audit_logs` — 审计日志（user_id, action, target_type, target_id, ip, payload）

**管理后台**：
- [ ] `admins` — 管理员表（user_id, role, permissions）
- [ ] `system_configs` — 系统配置（key, value）

### API 端点完整性

**认证 API**（Public）：
- [ ] `POST /api/v1/auth/register` / `login` / `logout` / `forgot-password` / `reset-password` / `email/verify`

**Profile API**（Member）：
- [ ] `GET/POST/PUT/DELETE /api/v1/profiles`
- [ ] `POST /api/v1/profiles/{id}/publish`
- [ ] `GET /api/v1/profiles/{id}/versions`
- [ ] `POST /api/v1/profiles/{id}/rollback`

**安全设置 API**（Member）：
- [ ] `GET/PUT /api/v1/profiles/{id}/security` / `parental` / `adblock`

**规则 API**（Member）：
- [ ] `GET/POST /api/v1/profiles/{id}/allowlist` / `denylist`
- [ ] `DELETE /api/v1/profiles/{id}/allowlist/{rule_id}` / `denylist/{rule_id}`

**设备/日志/统计 API**（Member）：
- [ ] `GET/POST /api/v1/profiles/{id}/devices`
- [ ] `GET /api/v1/logs` / `logs/export`
- [ ] `GET /api/v1/stats/overview` / `timeseries` / `top-domains` / `top-blocked`

**团队/计费/API Key/通知 API**（Member）：
- [ ] 团队 CRUD + 邀请
- [ ] 套餐列表、订阅变更、订单、用量
- [ ] API Key 创建/撤销
- [ ] 通知列表/设置

**Admin API**：
- [ ] 仪表盘、用户管理、订单管理、系统配置、审计日志

---

## 2. DNS Control Plane / Go 系统检查

### 数据结构
- [ ] NodeInfo / Heartbeat / ProfileConfig / PublishTask / Ruleset / AlertRule

### API 端点
- [ ] Internal: publish, sync-status, nodes, rulesets/publish
- [ ] Agent: heartbeat, version-report, error-report, tasks
- [ ] Console: nodes CRUD, profiles, sync-status, monitor, alerts

---

## 3. DNS Node（dns-resolver）检查

- [ ] ProfileConfig 内存结构
- [ ] Decision 结构
- [ ] DoH / DoT / UDP 识别协议
- [ ] Device 识别（X-Device-ID / EDNS / IP）
- [ ] 配置同步协议（checksum / 原子替换 / ACK）

---

## 4. NATS 消息系统检查

- [ ] 8 个 Topic: profile.updated, profile.deleted, ruleset.updated, node.heartbeat, node.offline, dns.logs, billing.usage, alerts.created
- [ ] 统一消息格式：event_id, event_type, trace_id, source, timestamp, payload

---

## 5. ClickHouse 检查

- [ ] dns_logs 原始表（含 DDL）
- [ ] 物化视图：dns_minute_stats, dns_hourly_stats, dns_daily_stats

---

## 6. Redis 检查

- [ ] profile:{id}:current / :version:{v} / :checksum
- [ ] node:{id}:status / :heartbeat
- [ ] rate_limit:{key} / device_ip:{ip}

---

## 最终输出检查

- [ ] 每个系统有独立的 specs 目录和文档
- [ ] project-doc/00-GOAL.md ~ 05-delivery-criteria.md 已同步到当前架构状态
- [ ] 交付前已对照 `project-doc/05-delivery-criteria.md` 补齐验收证据
- [ ] 以上清单的每个复选框都已核实
- [ ] 无遗漏项


## 财务准确性必检

- [ ] 所有金额字段为 `amount_minor bigint + currency`
- [ ] 禁止 float/double 计算金额
- [ ] `billing_ledger_entries` 追加写
- [ ] Webhook 幂等
- [ ] 退款累计不得超过支付金额
- [ ] usage batch 重放不重复计费
