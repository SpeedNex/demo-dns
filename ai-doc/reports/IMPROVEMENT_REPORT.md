# 文档改进报告

## 1. 改进目标

本次改进目标是把原文档从“产品 / 架构讨论材料”升级为“可用于生成 MVP 软件的工程规格包”。

重点解决：

- 新旧架构命名混杂。
- PostgreSQL / MySQL 决策冲突。
- Laravel 版本和状态标记不一致。
- 新目标规格过薄，缺少 API、字段、Schema、迁移。
- GeoDNS 与 resolver 查询链路表达不准确。
- 心跳、日志、指标上报语义容易混淆。
- “文档已完成”和“代码已完成”状态混用。

## 2. 已完成改进

| 类别 | 改进内容 |
|---|---|
| 架构 | 固定当前唯一目标包为 `portal-web`、`dns-console-web`、`dns-resolver`、`geodns` |
| 历史文档 | 将 `admin-web`、`dns-control-web`、`control-plane` 移入 `archive/historical-specs/` |
| 数据流 | 新增 `project-doc/03-DATA-FLOW.md`，明确配置、查询、心跳、日志、GeoDNS、用量链路 |
| MVP | 新增 `project-doc/04-MVP-SCOPE.md`，锁定第一版闭环，避免范围过大 |
| API | 重写 `portal-web` 与 `dns-console-web` API 规格，补请求、响应、错误码和鉴权 |
| 数据库 | 补齐 PostgreSQL 表字段、约束、索引、保留策略 |
| resolver | 补齐 Go 单二进制运行模型、配置、协议、规则引擎、热加载、本地 buffer |
| geodns | 补齐健康视图、路由策略、调度模型 |
| 契约 | 新增 OpenAPI 和 JSON Schema，用于代码生成和校验 |
| 迁移 | 新增 PostgreSQL / ClickHouse migration 草案 |
| 部署 | 新增 docker-compose、env.example、本地启动说明 |
| 交付 | 细化 L1-L4 交付等级和 MVP 端到端验收步骤 |
| 完整性 | 保留原始压缩包和原始正文文档快照 |

## 3. 当前文档可支持的生成目标

可以支持：

```text
L3 MVP 代码生成：
- portal-web 基础业务后台
- dns-console-web 节点控制面
- dns-resolver Go 单节点
- geodns 简化健康调度
- PostgreSQL / ClickHouse / Redis / NATS 本地环境
```

不建议直接生成：

```text
L4 生产级商业系统
```

原因是生产级还需要真实压测、安全审计、容量评估、监控告警、灰度发布、灾备演练和合规审查。

## 4. 原始内容保留方式

为了避免遗漏，包内保留：

```text
_original_source/ai-doc(8).zip
_original_source/extracted_original/*
archive/historical-specs/*
```

当前有效文档则位于：

```text
README.md
START.md
project-doc/*
specs/{portal-web,dns-console-web,dns-resolver,geodns,clickhouse,nats}/*
contracts/*
migrations/*
deploy/*
```

## 5. 后续生成代码建议

建议下一步按以下顺序生成代码：

1. 基础设施：docker-compose、PostgreSQL migration、ClickHouse DDL。
2. `dns-console-web`：Agent API 和 Internal API。
3. `portal-web`：Profile、规则、发布、日志查询。
4. `dns-resolver`：心跳、拉配置、DoH / UDP、规则引擎、日志上报；凭据由 `resolver install` 一次性写入。
5. 简化 `geodns`：读取健康视图并返回节点。
6. 端到端验收脚本。

每一步都要更新 `project-doc/04-change-log.md`，并把 `impl_status`、`test_status` 写清楚。

