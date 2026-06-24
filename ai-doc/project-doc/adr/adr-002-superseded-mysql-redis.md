# ADR-002: 数据存储架构 - MySQL + Redis（已恢复）

## 状态

已恢复，MySQL 8.0 重新作为业务主库。

## 恢复原因

原 ADR 决策为 MySQL 8.0 + Redis 7，后被 ADR-004 临时替换为 PostgreSQL 16。当前项目已统一回迁为 MySQL 8.0：

```text
MySQL 8.0：业务主库和控制面元数据
Redis 7：缓存、短态、健康快照、限流
ClickHouse 24.x：DNS 查询日志分析
NATS JetStream 2.x：异步事件和日志队列
```

## 处理原则

- 新代码使用 MySQL 语法。
- 新 migration 使用 Laravel PHP 迁移（MySQL）。
- 财务金额字段使用 `amount_minor bigint + currency`。
- DNS 查询日志不写入 MySQL 作为长期方案，统一进入 ClickHouse。
- Redis 不承担长期持久业务数据。
