# 核心数据流

> 本文件定义系统运行时的 6 条核心链路。接口、数据库和测试都应围绕这些链路生成。

## 1. 用户配置链路

```text
用户登录 portal-web
  → 创建 / 修改 Profile
  → 配置会员中心功能：安全 / 隐私 / 家长监护 / 黑名单 / 白名单 / 设置
  →-> portal-web 写入 MySQL
  → 创建 profile_versions 草案
  → 用户点击发布
  → portal-web 调用 portal-web(原 console 域)Internal API(进程内服务)
  → portal-web(原 console 域)生成 config_versions 和 publish_tasks
  → resolver 通过心跳响应发现 should_pull_config=true
  → resolver 拉取最新配置 bundle
  → checksum 校验成功
  → 原子替换本地配置文件
  → 热加载到内存 RuleEngine
  → resolver 回传 config ACK
```

### 关键约束

- 只有发布后的版本才可被 resolver 消费。
- 每个配置版本必须有 `version`、`checksum`、`created_at`。
- resolver 只能拉取“已发布、可消费”的配置 bundle。
- 失败 ACK 必须记录错误原因，便于回滚。

## 2. DNS 查询链路

```text
用户设备发起 DNS 查询
  → resolver 接收 UDP/TCP/DoH/DoT 请求
  → 提取 Profile 标识
  → 识别 Device
  → 归一化域名：lowercase + trim dot + IDNA/Punycode
  → 查询本地缓存
  → 白名单匹配
  → 黑名单 / 安全 / 隐私 / 家长 / 广告规则匹配
  → 命中拦截：返回 NXDOMAIN / 0.0.0.0 / 自定义阻断页
  → 未命中：请求 upstream DNS
  → 返回用户设备
  → 异步写入 QueryLogItem
```

### 规则优先级

```text
1. profile disabled → 默认上游解析
2. allow list → ALLOW
3. blocklist → BLOCK
4. security malware / phishing / c2 → BLOCK
5. privacy tracking / telemetry → BLOCK
6. parental category / safe search rewrite → BLOCK or REWRITE
7. adblock rules → BLOCK
8. rewrite rules → REWRITE
9. default → ALLOW
```

## 3. 节点心跳链路

```text
resolver 定时生成 Heartbeat (默认 30s 间隔)
  → POST /api/v1/node/nodes/heartbeat  (合并后目标侧为 portal-web(原 console 域))
  Authorization: Bearer <ocnd_xxxxx>
  X-Signature: hex(HMAC-SHA256(secret, canonical))
  X-Timestamp: <unix_seconds>
  X-Nonce: <16-128 random chars>
  → portal-web(原 console 域) 通过 VerifyRequestSignature 中间件校验：
      - sha256(bearer_token) 查 node_tokens 表 → 获取加密的 hmac_secret
      - 校验 HMAC 签名 + 时间窗 ±300s + nonce 单次 (Redis 缓存防重放)
  → 写 node_heartbeats 表
  → 更新 nodes.last_heartbeat_at / status / current_config_version
  → HeartbeatService.evaluate() 响应:
      - ok, server_time, node_status
      - latest_config_version (desired_config_version)
      - should_pull_config (latest > current)
      - config_endpoint: /api/v1/node/resolver/config
      - next_heartbeat_after_seconds: 30
  → resolver 按 should_pull_config 决定是否拉配置
```

**节点凭据来源**:通过 `install.sh` → `geo-dns install --server=... --token=ocnd_xxx --node-id=xxx` 流程：
1. `install.go` 调用 `POST /api/v1/node/tokens/verify` 用 token 换取 api_key + hmac_secret
2. 原子写入 `configs/server.yaml` 的 `control_plane.{api_key,secret,node_id}`（权限 0600）
3. **不存在** resolver 侧 `register` / `bootstrap` 端点或 `identity.json` 兜底

**节点状态判定**：
- 收到心跳即标记 `online`（HeartbeatService.computeStatus()）
- 超时判定由 console 定时任务调用 `deriveOfflineStatusFromLastHeartbeat()` 后置计算（默认 90s 阈值）
- resolver 心跳本身不带 qps / cpu / mem / disk / error 等"健康"指标

### 心跳字段（实际代码）

```json
{
  "status": "online",
  "uptime_seconds": 3600,
  "version": "1.0.0",
  "current_config_version": 12,
  "profiles_loaded": 150,
  "last_config_pull_at": "2026-06-21T10:00:00Z",
  "last_log_flush_at": "2026-06-21T10:00:05Z"
}
```

> `node_id` 由中间件从 Bearer token 解析注入，不需要在请求体中重复传递。

## 4. 配置拉取链路

```text
resolver 判断需要拉取配置
  → GET /api/v1/node/resolver/config?current_version=12  (合并后目标侧为 portal-web(原 console 域))
  Authorization: Bearer <ocnd_xxxxx>
  X-Signature: hex(HMAC-SHA256(canonical))
  X-Timestamp: <unix_seconds>
  X-Nonce: <16-128 random chars>
  → portal-web(原 console 域)返回 config bundle 或 204
  → resolver 校验 bundle.version > current_version
  → 校验 checksum
  → 写入 staging 文件
  → 原子 rename 为 active 文件
  → 热加载内存规则
  → POST /api/v1/node/resolver/config/ack
```

### 失败处理

| 失败点 | 行为 |
|---|---|
| 网络失败 | 保留当前版本，指数退避重试 |
| checksum 失败 | 拒绝应用，上报 failed ACK |
| 热加载失败 | 回滚到上一版本，上报 failed ACK |
| 新版本规则为空 | 若 bundle 声明 empty_allowed=false，则拒绝应用 |

## 5. 查询日志链路

```text
resolver 处理查询后生成 QueryLogItem
  → 写入内存 ring buffer
  → 达到 batch size 或 flush interval
  → MVP:POST portal-web(原 console 域) /api/v1/node/query-logs/batch
  Authorization: Bearer <ocnd_xxxxx>
  X-Signature: hex(HMAC-SHA256(canonical))
  X-Timestamp: <unix_seconds>
  X-Nonce: <16-128 random chars>
  → 规模化:发布到 portal-web(原 console 域)管理的 NATS dns.logs ingestion 入口
  → portal-web(原 console 域)log worker 幂等写 ClickHouse
  → 失败时 resolver 写本地 buffer 文件
  → 恢复后按时间顺序重放到 portal-web(原 console 域)ingestion
  → portal-web(原 console 域)usage worker 从 query log 派生 usage batch
  → portal-web Member 域只读 ClickHouse 展示日志与统计,并用 usage_records/usage_counters 处理计费
```

### 日志约束

- 默认不保存 client IP 明文。
- `client_ip_hash` 使用服务端 salt 后哈希。
- 每条日志必须包含 `profile_id`、`query_name`、`query_type`、`action`、`latency_ms`、`node_id`。
- 日志上报不能阻塞 DNS 响应。

## 6. GeoDNS 调度链路

```text
geodns 周期拉取健康视图
  → portal-web(原 console 域) /api/v1/internal/geodns/health-view 返回节点健康视图 (合并后目标侧)
  → geodns 写入本地内存路由表
  → 用户解析服务域名
  → geodns 根据来源 IP / 地域 / 权重 / 节点状态（online/offline）选择节点
  → 返回 resolver A / AAAA 记录
  → 用户后续 DNS 查询直接访问 resolver
```

### 调度决策顺序

```text
1. 节点必须 online(由 portal-web(原 console 域) 在 (now - last_heartbeat_at) <= 阈值 后置判定)
2. 节点协议必须支持请求入口类型
3. 地域距离优先
4. 同区域按权重选择
5. 节点不再有"健康度降权"——ops 监控已下线
6. 失败节点进入冷却期
7. 无可用节点时返回全局备用节点
```

## 7. 用量统计链路（V1 拉模型，不存在 push 用量端点）

```text
resolver 批量上报 query-logs/batch 到 portal-web(原 console 域)
  → portal-web(原 console 域)写 query_log_ingest_batches (幂等 batch_id + content_sha256)
  → portal-web(原 console 域)log worker 异步写 ClickHouse dns_logs
  → portal-web Member 域主动 GET /api/v1/internal/query-logs?user_id=...&page=...  (内部走进程内服务)
  → portal-web Member 域主动 GET /api/v1/internal/query-analytics?user_id=...  (内部走进程内服务)
  → portal-web Member 域 BillingUsageService 幂等写入 usage_records / usage_counters
  → portal-web Member 域 QuotaService 生成 quota_snapshots
  → portal-web Member 域在 POST /api/v1/internal/profile-publishes 的 quota 字段一并下发 (内部走进程内服务)
  → portal-web(原 console 域)落 quota_snapshots,写入 resolver config bundle
  → dns-resolver 热加载后按 quota 执行限额策略
```

V1 **不提供** `POST /api/v1/internal/usage/batches`（push）；V2+ 评估替换为 push 模型。MVP 可先只统计查询数和拦截数，不强制实现扣费。




## 7A. 会员中心功能配置链路

```text
用户进入 portal-web 会员中心
  → 选择 Profile
  → 修改安全 / 隐私 / 家长监护 / 黑名单 / 白名单 / 设置
  → portal-web Member 域写 profiles / profile_feature_settings / profile_rules
  → 标记 Profile 有未发布变更
  → 用户点击发布
  → 生成 profile_versions.config_json
  → portal-web(原 console 域)生成 resolver config bundle
  → resolver 拉取后执行
```

白名单优先级必须高于黑名单、安全、隐私和家长监护。隐私 `log_mode=disabled` 时不得向用户展示详细域名日志，但仍可保留最小化 query_count 聚合用于 Free 额度。

## 8. 数据落点总表

详见 `project-doc/06-CLOSED-LOOP-AND-DATA-DESTINATIONS.md`。生成代码时以该文件为数据上报和闭环的强约束。


## NextDNS Lite V1 用量与订阅数据流

```text
resolver query log
  -> portal-web(原 console 域) /api/v1/node/query-logs/batch
  -> query_log_ingest_batches (幂等 batch_id + content_sha256)
  -> ClickHouse dns_logs (log worker 异步写)
  -> portal-web Member 域主动 GET /api/v1/internal/query-logs?user_id=...  (进程内)
  -> portal-web Member 域主动 GET /api/v1/internal/query-analytics?user_id=...  (进程内)
  -> usage_records / usage_counters
  -> Free quota check
  -> quota snapshot (内嵌于 POST /api/v1/internal/profile-publishes 的 quota 字段,进程内)
  -> resolver config
```

V1 中 `usage_counters` 不生成按量收费，只用于 Free 300,000 queries/month、统计展示和风控。Pro / Business / Education 为 unlimited queries。

