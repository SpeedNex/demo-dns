# ADR-004: MySQL + Redis + ClickHouse + NATS 存储与异步架构

## 状态

已接受。

## 背景

OcerDNS Security Platform 同时包含：

- 事务型业务数据：用户、Profile、规则、套餐、订阅、审计。
- 控制面元数据：节点、心跳、配置版本、发布任务、ACK。
- 高频查询日志：DNS 请求、命中规则、延迟、动作、设备。
- 短态运行数据：节点健康快照、限流、配置缓存。
- 异步事件：配置变更通知、日志上报、用量统计、告警。

单一存储无法同时满足事务、分析、短态和异步可靠性。

## 决策

采用：

| 组件 | 责任 |
|---|---|
| MySQL 8.0 | 业务主数据和控制面元数据 |
| Redis 7 | 缓存、限流、健康快照、短态调度数据 |
| ClickHouse 24.x | DNS 查询日志和聚合分析 |
| NATS JetStream 2.x | 配置事件、日志队列、用量事件、告警事件 |

## 关键约束

- MySQL 是唯一业务主库。
- resolver 不直接访问 MySQL。
- resolver 不直接写 ClickHouse；MVP 必须通过 portal-web HTTP batch，规模化可通过 portal-web 管理的 NATS ingestion，再由 Log Worker 写 ClickHouse。
- Redis 不作为强一致主存储。
- ClickHouse 日志保留周期必须与套餐和隐私策略一致。

## 后果

### 正面

- 业务事务清晰。
- 日志分析不压垮业务库。
- DNS 查询链路与 Web 控制面解耦。
- 后续可平滑扩容日志链路。

### 负面

- 组件较多，需要本地 docker-compose 和运维说明。
- 需要处理异步一致性和失败重试。
- ClickHouse 与 MySQL 查询模型不同，需要服务层隔离。

## 实施顺序

MVP：MySQL + Redis + ClickHouse 单机，日志可 HTTP batch 写入。  
规模化：引入 NATS JetStream、Log Worker、ClickHouse 集群和消息 DLQ。

