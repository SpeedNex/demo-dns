# NATS JetStream 事件规格

> NATS 是规模化阶段的异步消息总线。MVP 可以先使用 HTTP batch，接口设计必须兼容后续切换到 NATS。

## 1. 统一 Envelope

```json
{
  "event_id": "evt_01H...",
  "event_type": "profile.config.published",
  "trace_id": "trace_01H...",
  "source": "portal-web",
  "occurred_at": "2026-06-12T10:00:00.000Z",
  "schema_version": 1,
  "payload": {}
}
```

## 2. Topic / Subject

| Subject | 持久化 | 生产者 | 消费者 | 说明 |
|---|---|---|---|---|
| `profile.config.published` | 是 | portal-web / dns-console-web | dns-console-web / resolver agent | Profile 配置发布 |
| `profile.config.deleted` | 是 | portal-web | dns-console-web / resolver agent | Profile 删除 |
| `resolver.config.ack` | 是 | dns-resolver | dns-console-web | 配置应用结果 |
| `resolver.heartbeat` | 否 / 可选 | dns-resolver | dns-console-web | 心跳，HTTP 是主路径 |
| `dns.query_logs` | 是 | dns-resolver | log-worker | DNS 查询日志 |
| `resolver.metrics` | 是 | dns-resolver | metrics-worker | resolver 指标 |
| `billing.usage` | 是 | dns-console-web usage-worker | portal-web billing-worker | 由 query log batch 派生的计费用量；不得由 metrics/heartbeat 派生 |
| `alerts.created` | 是 | alert-manager | notification-worker | 告警 |

## 3. profile.config.published

```json
{
  "event_id": "evt_01H...",
  "event_type": "profile.config.published",
  "trace_id": "trace_01H...",
  "source": "dns-console-web",
  "occurred_at": "2026-06-12T10:00:00.000Z",
  "schema_version": 1,
  "payload": {
    "publish_id": "pub_01H...",
    "profile_id": "prf_01H...",
    "config_version": 13,
    "checksum": "sha256:...",
    "target_scope": "all_nodes"
  }
}
```

消费者行为：resolver 收到后可立即拉取配置；若消息丢失，心跳响应和轮询仍能补偿。

## 4. dns.query_logs

payload 与 `contracts/query-log.schema.json` 的 `items[]` 一致，可以按 batch 发布：

```json
{
  "event_id": "evt_01H...",
  "event_type": "dns.query_logs",
  "trace_id": "trace_01H...",
  "source": "dns-resolver",
  "occurred_at": "2026-06-12T10:00:00.000Z",
  "schema_version": 1,
  "payload": {
    "batch_id": "batch_01H...",
    "node_id": "node_01H...",
    "items": []
  }
}
```

## 5. resolver.config.ack

```json
{
  "event_id": "evt_01H...",
  "event_type": "resolver.config.ack",
  "trace_id": "trace_01H...",
  "source": "dns-resolver",
  "occurred_at": "2026-06-12T10:00:05.000Z",
  "schema_version": 1,
  "payload": {
    "node_id": "node_01H...",
    "config_version": 13,
    "checksum": "sha256:...",
    "status": "applied",
    "error_code": null,
    "error_message": null
  }
}
```

## 6. JetStream 建议

| Stream | Subjects | Retention | Max Age |
|---|---|---|---|
| `CONFIG` | `profile.config.*`, `resolver.config.*` | limits | 7d |
| `DNS_LOGS` | `dns.query_logs` | workqueue | 3d |
| `METRICS` | `resolver.metrics` | limits | 1d |
| `BILLING` | `billing.usage` | limits | 30d |
| `ALERTS` | `alerts.*` | limits | 30d |

## 7. 可靠性

| Subject | AckWait | MaxDeliver | DLQ |
|---|---:|---:|---|
| `dns.query_logs` | 60s | 5 | `dns.query_logs.dlq` |
| `profile.config.published` | 30s | 5 | `profile.config.published.dlq` |
| `resolver.config.ack` | 30s | 5 | `resolver.config.ack.dlq` |
| `billing.usage` | 60s | 5 | `billing.usage.dlq` |
| `alerts.created` | 30s | 3 | `alerts.created.dlq` |

## 8. 幂等

消费者必须按以下字段幂等：

| 事件 | 幂等键 |
|---|---|
| `dns.query_logs` | `batch_id` |
| `profile.config.published` | `publish_id` |
| `resolver.config.ack` | `node_id + config_version` |
| `billing.usage` | `source + source_batch_id + profile_id`；同一批次重放不得重复入账 |



## Billing usage 约束

`billing.usage` 只允许由 `dns-console-web` 的 usage-worker 从已接收的 query log batch 派生。`dns-resolver` 不直接发布财务事件；metrics、heartbeat、Prometheus 指标不得作为扣费事实来源。
