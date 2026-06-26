# portal-web(原 console 域) API 规格

> `portal-web(原 console 域)` 是 DNS 控制面，已并入 `portal-web`。它接收 resolver 心跳、配置拉取、ACK、日志 / 指标上报；接收 portal-web Member 域的内部发布请求；为 geodns 提供健康节点视图。**resolver 上线流程已下线** `register` 端点，统一走 Console 预签发 + `resolver install` 流程（详见 `project-doc/06-INSTALL.md`）。

> **重要**: 原 `dns-console-web` 已于 2026-06-15 至 2026-06-16 之间整体并入 `portal-web`。实现位置：`portal-web/app/Http/Controllers/Api/V1/{Admin,Agent,Internal}/*`，路由路径完全不变。本文件保留作为 API 规格参考。

## 1. API 分组

| 分组 | 前缀 | 调用方 | 鉴权 | 用途 |
|---|---|---|---|---|
| Console API | `/api/v1/console/*` | 管理员 UI | 管理员登录 | 节点、任务、发布记录、健康状态 |
| Agent API | `/api/v1/agent/*` | `dns-resolver` | nodeBearer + apiKeyHmac | 心跳、拉配置、ACK、日志 |
| Internal API | `/api/v1/internal/*` | `portal-web` / `geodns` | HMAC | `POST profile-publishes`、`GET geodns/health-view`、`GET query-logs`、`GET query-analytics` |
| Admin API | `/api/v1/admin/*` | 管理员 UI / 内部脚本 | adminBearer | 节点预签发凭据、token 重新签发 |

**节点上线流程已统一为「Console 预签发 + resolver install」**：

```text
1. 管理员在 console 后台创建节点（指定 region / country / supported_protocols 等）
2. console 调用 NodeTokenService.issueToken() 预签发 (api_key, secret, node_id) 三元组
   三元组在创建响应中**仅返回一次**，console 仅保存 sha256(api_key) 与 sha256(secret)
3. 运维通过受控通道把三元组带到目标机
4. `resolver install --console=... --node-id=... --api-key=... --secret=...` 写入 configs/server.yaml
5. `resolver` 启动 → cfg.Validate() → 凭据直驱 agent.Credentials
```

**禁止**以下路径：

- ❌ `POST /api/v1/agent/nodes/register`（已删除）
- ❌ resolver 侧 `bootstrap_token` / `identity.json` 自助注册
- ❌ 任何 "凭据缺失就走旧流程" 的兜底 / 回退 / 虚拟代码

详细规范见 `project-doc/06-INSTALL.md`；API 契约见 `contracts/openapi.yaml` 中 `/api/v1/admin/nodes*` 端点。

## 当前实现映射（2026-06-15）

- 当前开发目录：`ocer-dns/portal-web`（已合并）
- 已落地 `routes/api.php` 中的 Agent / Internal / Admin / Console 路由
- 已实现的领域与持久化：
  - `app/Domain/ConfigVersion/ChecksumService.php`
  - `app/Domain/ConfigVersion/ConfigBuildService.php`
  - `app/Domain/ConfigVersion/ConfigAckService.php`
  - `app/Domain/Heartbeat/HeartbeatService.php`
  - `app/Domain/HealthView/NodeHealthViewService.php`
  - `app/Domain/Ingest/QueryLogIngestService.php`
  - `app/Domain/Ingest/MetricsIngestService.php`
  - `app/Domain/Auth/NodeTokenService.php`（预签发 api_key + secret，仅保存 hash）
  - `app/Models/Node.php`
  - `app/Models/NodeToken.php`（含 `token_hash` 与 `hmac_key_hash` 两列）
  - `app/Models/NodeHeartbeat.php`
  - `app/Models/ConfigVersion.php`
  - `app/Models/PublishTask.php`
  - `app/Models/TaskExecution.php`
  - `app/Models/QueryLogIngestBatch.php`
- 已接入服务层且有自动化测试覆盖的控制器：
  - `HeartbeatController` / `ConfigPullController` / `ConfigAckController`
  - `QueryLogController` / `MetricsController`
  - `AdminNodeController`（含 `store` 预签发、`issueToken` 重新签发、`revokeToken`）
  - `ProfilePublishController` / `HealthViewController`
- 已支持的鉴权行为（中间件见 `app/Http/Middleware`）：
  - `Authorization: Bearer <api_key>` 命中 `node_tokens.token_hash`
  - `X-Hmac-Key` + `X-Signature`（HMAC-SHA256）命中 `node_tokens.hmac_key_hash`
  - `X-Timestamp` 时间窗 ±300s，`X-Nonce` 单次唯一
  - `Authorization: Internal <token>` / `Authorization: Admin <token>` 兼容 Internal / Admin API
- 当前 Internal 健康视图实现路径固定为：
  - `GET /api/v1/internal/geodns/health-view`
- 当前已下线：
  - `app/Domain/Node/NodeRegistrationService.php` 与 `NodeRegistrationController`（自助注册代码已删除）
  - `/api/v1/agent/nodes/register` 端点（已从 `openapi.yaml` 删除）
  - bootstrap token / 任何 resolver 侧自助注册兜底
- 当前仍缺：
  - MySQL / Redis / ClickHouse 的生产级外部服务接入与迁移策略
  - 节点凭据全生命周期管理 UI（控制台需要支持 revoke、reissue 流程）
  - 节点 `disabled` 状态下凭据吊销的强一致性

## 2. Agent API

> **节点注册流程已下线**。resolver 不再向 console 调用任何注册端点；上线流程统一走
> `project-doc/06-INSTALL.md` 中的「Console 预签发 + resolver install」五步。`/api/v1/agent/nodes/register`
> 端点**已从 openapi.yaml 删除**，不得回退。所有 `/api/v1/agent/*` 请求必须带
> `Authorization: Bearer <api_key>` 与 `X-Signature` (HMAC-SHA256) 双因子。

### 2.1 节点心跳

```http
POST /api/v1/agent/nodes/heartbeat
Authorization: Bearer <api_key>
X-Hmac-Key: <secret>
X-Signature: hex(HMAC-SHA256(canonical))
X-Timestamp: 1749990000
X-Nonce: <16 random bytes hex>
```

请求：

```json
{
  "node_id": "node_01H...",
  "status": "healthy",
  "uptime_seconds": 86400,
  "version": "0.1.0",
  "current_config_version": 12,
  "profiles_loaded": 200,
  "rules_loaded": 120000,
  "qps_1m": 1234,
  "qps_5m": 1100,
  "cpu_usage": 42.5,
  "memory_usage": 61.2,
  "disk_usage": 55.0,
  "error_count_1m": 3,
  "last_config_pull_at": "2026-06-12T09:59:30Z",
  "last_log_flush_at": "2026-06-12T09:59:55Z"
}
```

响应：

```json
{
  "data": {
    "ok": true,
    "server_time": "2026-06-12T10:00:00Z",
    "node_status": "online",
    "latest_config_version": 13,
    "should_pull_config": true,
    "config_endpoint": "/api/v1/agent/resolver/config",
    "next_heartbeat_after_seconds": 30
  }
}
```

错误：

| HTTP | code | 说明 |
|---:|---|---|
| 401 | `INVALID_NODE_TOKEN` | `api_key` 不存在 / 已吊销 |
| 401 | `INVALID_SIGNATURE` | HMAC 签名不匹配 |
| 401 | `TIMESTAMP_OUT_OF_RANGE` | 时间漂移超过 ±300s |
| 401 | `NONCE_REPLAYED` | nonce 重复 |
| 404 | `NODE_NOT_FOUND` | `node_id` 与 `api_key` 不匹配 |
| 422 | `VALIDATION_FAILED` | 请求体字段缺失或类型错误 |

语义：心跳只表示节点健康、负载和配置版本，不等于查询日志上报。

### 2.2 拉取 resolver 配置

```http
GET /api/v1/agent/resolver/config?node_id=node_01H...&current_version=12
Authorization: Bearer <api_key>
X-Hmac-Key: <secret>
X-Signature: hex(HMAC-SHA256(canonical))
X-Timestamp: 1749990000
X-Nonce: <16 random bytes hex>
```

无更新：`204 No Content`。

有更新响应：

```json
{
  "data": {
    "version": 13,
    "checksum": "sha256:...",
    "generated_at": "2026-06-12T10:00:00Z",
    "expires_at": "2026-06-12T10:10:00Z",
    "profiles": [],
    "rulesets": [],
    "upstreams": [],
    "quota": {},
    "signature": "base64-signature"
  }
}
```

要求：

- `version` 必须大于 resolver 当前版本。
- `checksum` 对 canonical JSON 计算。
- 响应必须符合 `contracts/resolver-config.schema.json`。

### 2.3 配置应用 ACK

```http
POST /api/v1/agent/resolver/config/ack
Authorization: Bearer <api_key>
X-Hmac-Key: <secret>
X-Signature: hex(HMAC-SHA256(canonical))
X-Timestamp: 1749990000
X-Nonce: <16 random bytes hex>
```

请求：

```json
{
  "node_id": "node_01H...",
  "config_version": 13,
  "checksum": "sha256:...",
  "status": "applied",
  "applied_at": "2026-06-12T10:00:05Z",
  "error_code": null,
  "error_message": null
}
```

失败请求示例：

```json
{
  "node_id": "node_01H...",
  "config_version": 13,
  "checksum": "sha256:...",
  "status": "failed",
  "applied_at": "2026-06-12T10:00:05Z",
  "error_code": "CHECKSUM_MISMATCH",
  "error_message": "expected sha256:abc but got sha256:def"
}
```

响应：`200 OK`。

### 2.4 查询日志批量上报

```http
POST /api/v1/agent/query-logs/batch
Authorization: Bearer <api_key>
X-Hmac-Key: <secret>
X-Signature: hex(HMAC-SHA256(canonical))
X-Timestamp: 1749990000
X-Nonce: <16 random bytes hex>
```

请求：

```json
{
  "batch_id": "batch_01H...",
  "node_id": "node_01H...",
  "sent_at": "2026-06-12T10:00:00Z",
  "items": [
    {
      "timestamp": "2026-06-12T09:59:59Z",
      "profile_id": "prf_01H...",
      "user_id": "usr_01H...",
      "team_id": null,
      "device_id": "dev_01H...",
      "query_name": "ads.example.com",
      "query_type": "A",
      "action": "blocked",
      "reason": "blocklist",
      "category": "custom",
      "rule_id": "rule_01H...",
      "latency_ms": 7,
      "upstream": null,
      "rcode": "NXDOMAIN",
      "client_ip_hash": "sha256_first16",
      "profile_version": 13
    }
  ]
}
```

响应：

```json
{
  "data": {
    "accepted": true,
    "batch_id": "batch_01H...",
    "received_count": 1,
    "duplicate": false
  }
}
```

约束：

- 单 batch 默认最多 1000 条或 1MB。
- `batch_id` 幂等。
- 接收失败时 resolver 必须本地 buffer。
- 长期存储由 portal-web(原 console 域) 的 log worker 写 ClickHouse，不写 MySQL 作为主日志库。resolver 不得直接连接 ClickHouse。
- `batch_id + content_sha256` 幂等；同一 batch 重放不得重复写 ClickHouse，不得重复产生计费用量。

### 2.5 指标批量上报

```http
POST /api/v1/agent/metrics/batch
Authorization: Bearer <api_key>
X-Hmac-Key: <secret>
X-Signature: hex(HMAC-SHA256(canonical))
X-Timestamp: 1749990000
X-Nonce: <16 random bytes hex>
```

请求：

```json
{
  "node_id": "node_01H...",
  "sent_at": "2026-06-12T10:00:00Z",
  "interval_seconds": 60,
  "metrics": {
    "query_count": 60000,
    "blocked_count": 12000,
    "cache_hit_count": 22000,
    "upstream_error_count": 5,
    "p50_latency_ms": 4,
    "p95_latency_ms": 18,
    "p99_latency_ms": 40
  }
}
```

### 2.6 数据写入与上报落点

| Agent 接口 | portal-web(原 console 域) 处理 | 最终落点 |
|---|---|---|
| `/nodes/heartbeat` | 写 `node_heartbeats`，更新 `nodes`，写 Redis health | MySQL + Redis |
| `/resolver/config/ack` | 写 `task_executions`，更新发布状态 | MySQL + Redis |
| `/query-logs/batch` | 写 `query_log_ingest_batches`，异步写 ClickHouse，派生 usage batch | MySQL ingest 表 + ClickHouse + portal usage API |
| `/metrics/batch` | 写短期指标 / Prometheus / Redis health，不用于扣费 | Redis / Prometheus / 可选 ClickHouse |

resolver 不直接写 Redis、ClickHouse、MySQL。MVP 主路径是 HTTP Agent API；NATS 只作为规模化替代入口。

### 2.7 计费用量上报到 portal-web

当日志批次成功接收并完成幂等校验后，usage worker 必须按 profile/user/period 生成用量批次，并调用：

```http
POST {PORTAL_URL}/api/v1/internal/usage/batches
X-Internal-Key-Id: portal-web
X-Internal-Timestamp: 2026-06-12T10:01:00Z
X-Internal-Nonce: nonce
X-Internal-Signature: hmac-sha256(...)
Idempotency-Key: portal-web:{source_batch_id}
```

该接口归属 `portal-web`，写入 `usage_records` / `usage_counters`，用于套餐限制和账单生成。heartbeat / metrics 不能作为计费事实来源。

## 3. Admin API（节点凭据签发）

### 3.1 预签发节点凭据

```http
POST /api/v1/admin/nodes
Authorization: Bearer <admin_session>
```

请求：

```json
{
  "node_name": "resolver-hk-01",
  "region": "ap-east-1",
  "country": "HK",
  "city": "Hong Kong",
  "provider": "aws",
  "supported_protocols": ["udp", "tcp", "doh", "dot"],
  "capacity_qps": 5000,
  "weight": 100,
  "labels": { "env": "prod" }
}
```

响应（**`api_key` 与 `secret` 仅返回一次**）：

```json
{
  "data": {
    "node_id": "hk-01",
    "api_key": "ak_xxx",
    "secret": "sk_xxx",
    "heartbeat_interval_seconds": 30,
    "config_poll_interval_seconds": 30,
    "latest_config_version": 0,
    "server_time": "2026-06-12T10:00:00Z"
  }
}
```

错误：

| HTTP | code | 说明 |
|---:|---|---|
| 401 | `UNAUTHENTICATED_ADMIN` | 管理员登录失效 |
| 403 | `INSUFFICIENT_PRIVILEGE` | 无节点管理权限 |
| 409 | `NODE_NAME_CONFLICT` | `node_name` 重复 |
| 422 | `VALIDATION_FAILED` | 参数错误 |

### 3.2 重新签发凭据

```http
POST /api/v1/admin/nodes/{node_id}/credentials
Authorization: Bearer <admin_session>
```

行为：吊销该节点当前所有未吊销凭据，签发新的 `(api_key, secret)`。新值在响应中**仅返回一次**；老凭据立即失效。

响应同 3.1。

错误：

| HTTP | code | 说明 |
|---:|---|---|
| 401 | `UNAUTHENTICATED_ADMIN` | 管理员登录失效 |
| 404 | `NODE_NOT_FOUND` | 节点不存在 |
| 409 | `NODE_DISABLED` | 节点处于 `disabled` 状态，禁止签发 |

### 3.3 吊销节点凭据

```http
POST /api/v1/admin/nodes/{node_id}/tokens/{token_id}/revoke
Authorization: Bearer <admin_session>
```

行为：把指定 token 标记为 `revoked_at = now()`，该 token 立即失效；节点下一次心跳返回 401。

## 4. Internal API

> 当前 `routes/api.php` 已落地：
>
> - `POST /api/v1/internal/profile-publishes`
> - `GET  /api/v1/internal/geodns/health-view`
> - `GET  /api/v1/internal/query-logs`
> - `GET  /api/v1/internal/query-analytics`
>
> V1 **不实现**：`POST /api/v1/internal/usage/batches`、`POST /api/v1/internal/publish-status/callback`、`POST /api/v1/internal/quota/snapshots`（V2+ 评估 push 模型或回调）。

### 4.1 portal-web 发布 Profile 配置

```http
POST /api/v1/internal/profile-publishes
X-Internal-Key-Id: portal-web
X-Internal-Timestamp: 2026-06-12T10:00:00Z
X-Internal-Signature: hmac-sha256(...)
```

请求：

```json
{
  "profile_id": "prf_01H...",
  "user_id": "usr_01H...",
  "team_id": null,
  "version": 4,
  "checksum": "sha256:...",
  "config": {
    "default_action": "allow",
    "block_response": "nxdomain",
    "rules": []
  },
  "quota": {
    "plan_code": "free",
    "monthly_query_limit": 300000,
    "used_query_count": 0,
    "quota_status": "normal",
    "log_retention_days": 90
  },
  "message": "Update blocklist"
}
```

响应：

```json
{
  "data": {
    "publish_id": "pub_01H...",
    "config_version": 13,
    "status": "queued",
    "target_node_count": 3
  }
}
```

### 4.2 查询发布状态

```http
GET /api/v1/internal/profile-configs/publishes/{publish_id}/status
```

响应：

```json
{
  "data": {
    "publish_id": "pub_01H...",
    "status": "partially_applied",
    "target_node_count": 3,
    "applied_node_count": 2,
    "failed_node_count": 1,
    "latest_error": "node resolver-hk-02 checksum mismatch"
  }
}
```

### 4.3 geodns 健康视图

```http
GET /api/v1/internal/geodns/health-view?region=ap-east-1
```

响应：

```json
{
  "data": {
    "generated_at": "2026-06-12T10:00:00Z",
    "ttl_seconds": 15,
    "nodes": [
      {
        "node_id": "node_01H...",
        "region": "ap-east-1",
        "country": "HK",
        "city": "Hong Kong",
        "status": "online",
        "public_ipv4": "203.0.113.10",
        "public_ipv6": "2001:db8::10",
        "supported_protocols": ["udp", "tcp", "doh", "dot"],
        "weight": 100,
        "qps_1m": 1234,
        "capacity_qps": 50000,
        "last_heartbeat_at": "2026-06-12T09:59:50Z"
      }
    ]
  }
}
```

## 5. Console API

### 5.1 节点列表

```http
GET /api/v1/console/nodes?status=online&region=ap-east-1&page=1
```

### 5.2 节点详情

```http
GET /api/v1/console/nodes/{node_id}
```

包括节点主数据、最近心跳、当前配置版本、最近任务执行记录、**已签发凭据列表（仅含 hash 与元数据，不含明文 api_key / secret）**。

### 5.3 节点启用 / 禁用

```http
POST /api/v1/console/nodes/{node_id}/disable
POST /api/v1/console/nodes/{node_id}/enable
```

`disable` 会同时吊销该节点所有未吊销凭据；`enable` 后必须重新走 `POST /api/v1/admin/nodes/{node_id}/credentials` 才能恢复上报。

### 5.4 发布任务列表

```http
GET /api/v1/console/publish-tasks?status=queued&profile_id=prf_01H...
```

### 5.5 发布任务详情

```http
GET /api/v1/console/publish-tasks/{publish_id}
```

### 5.6 重试发布任务

```http
POST /api/v1/console/publish-tasks/{publish_id}/retry
```

## 6. 鉴权要求

- Agent API：
  - `Authorization: Bearer <api_key>`，console 端仅存 `sha256(api_key)` 命中 `node_tokens.token_hash`。
  - `X-Hmac-Key` = 明文 secret（用于服务端验证），`X-Signature` = `hex(HMAC-SHA256(secret, canonical))`。
  - `canonical = <ts>\n<METHOD>\n<path>\n<sha256(body) hex>`，`X-Timestamp` 时间窗 ±300s，`X-Nonce` 单次唯一。
- Admin API（节点预签发）：管理员登录 session（Sanctum bearer）。
- Internal API：HMAC 包含 method、path、timestamp、body_sha256，时间偏移不得超过 5 分钟。
- Console API：管理员登录 + RBAC。
- **已下线**：bootstrap token / `Authorization: Bootstrap ...` 任何用法；resolver 侧 `identity.json` 任何用法。

## 7. 状态机

### 7.1 node status

```text
pending → online → offline
pending → disabled
offline → online
disabled → pending（需重新签发凭据）
```

`degraded` 状态已下线：ops 监控只关心 online/offline，节点不再有"健康度降级"中间态。

### 7.2 node_token status

```text
active → revoked（管理员手动吊销 / 节点 disable）
active → superseded（管理员重新签发，老 token 立即 superseded）
```

### 7.3 publish task status

```text
queued → building → ready → distributing → partially_applied → applied
queued/building/distributing → failed
failed → retrying → distributing
applied → superseded
```


## NextDNS Lite quota 语义

`portal-web(原 console 域)` 只缓存和分发 portal-web 的 quota snapshot，不自行计算财务金额。

```text
Free: monthly_query_limit=300000, quota_status=normal|exceeded
Pro/Business/Education: monthly_query_limit=null, quota_status=unlimited
```

Free 超额（quota_status=exceeded）时 resolver 在 DNS 协议层硬拒绝返回 SERVFAIL，不再保留 classic_dns 降级模式。

`portal-web(原 console 域)` 可以根据 usage worker 聚合 query count，但最终订阅状态和 Free quota 判定以 `portal-web` 返回的 quota snapshot 为准。
