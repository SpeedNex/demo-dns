# ADR-001: 后端框架选型 - Laravel 11

## 状态
已接受

## 背景
项目需要构建一个 Web 应用，对外提供 REST API、管理后台、定时调度、异步队列。
核心诉求：
- 成熟的 ORM（数据模型多且关系复杂）
- 内置队列 / 任务调度
- 中等规模项目，PHP 生态能显著降低开发成本
- 配套生态丰富（Horizon、Breeze、Sanctum 等）

可选方案：
- **方案 A：Laravel 11**（PHP 8.2+）
- **方案 B：NestJS**（Node.js + TypeScript）
- **方案 C：Spring Boot**（Java 17）
- **方案 D：FastAPI**（Python 3.11）

## 决策
采用 **方案 A：Laravel 11**。

理由：
1. 内置队列、调度、ORM、缓存、事件系统一站式
2. Laravel Horizon 提供开箱即用的队列监控面板
3. Sanctum 提供轻量 API Token 鉴权
4. Guzzle 是推荐 HTTP 客户端
5. PHP 生态能够显著降低维护成本

## 后果

### 正面
- 队列 + 调度 + 缓存抽象统一，开发聚焦业务
- Eloquent 迁移 + Seed 工具链完善
- 社区活跃，包生态丰富

### 负面
- 相比 Node.js/Python 的异步框架，高并发吞吐略低
- 大版本升级偶有 BC 变化

## 相关文档
- [1-ARCHITECTURE.md](../1-ARCHITECTURE.md)
