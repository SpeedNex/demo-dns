# AI 文档生成框架 - 模板定义

## 项目类型定义

### 1. php-web（PHP Web 应用）
**特点**：通用 PHP Web 应用，REST API、管理后台、队列任务
**必选模块**：
- project-doc/00-GOAL.md：项目目标
- project-doc/01-ARCHITECTURE.md：架构设计
- project-doc/03-plans.md：实施计划
**推荐模块**：
- project-doc/adr/：架构决策记录
- project-doc/02-features.md：功能清单
- project-doc/04-change-log.md：变更日志
- project-doc/05-delivery-criteria.md：验收、发布、回滚、监控标准
- specs/{system}/api.md：接口规格
- specs/{system}/data-schema.md：数据规格

### 2. ai-app（AI 应用）
**特点**：AI 模型调用、Prompt 管理、输入/输出处理
**必选模块**：
- project-doc/00-GOAL.md：AI 应用定位
- project-doc/01-ARCHITECTURE.md：Prompt 管理、模型调用架构
- project-doc/03-plans.md：实施计划

### 3. saas（SaaS 订阅平台）
**特点**：多租户、订阅计费、用户管理
**必选模块**：
- project-doc/00-GOAL.md
- project-doc/01-ARCHITECTURE.md
- project-doc/03-plans.md
**推荐模块**：
- project-doc/adr/：认证方案、多租户策略
- project-doc/05-delivery-criteria.md：验收和上线门槛
- specs/{system}/api.md：API 接口

### 4. ecommerce（电商/交易平台）
**特点**：商品、订单、支付、账务
**必选模块**：
- project-doc/00-GOAL.md
- project-doc/01-ARCHITECTURE.md
- project-doc/03-plans.md

### 5. tool（个人工具/小工具）
**特点**：简单、快速、不需要复杂架构
**最小模块**：
- project-doc/00-GOAL.md
- project-doc/01-ARCHITECTURE.md（极简版）

### 6. enterprise（企业内部系统）
**特点**：权限复杂、审批流程、集成多
**必选模块**：
- project-doc/00-GOAL.md
- project-doc/01-ARCHITECTURE.md
- project-doc/03-plans.md

### 7. custom（自定义/混合类型）
根据实际需求组合以上模块

---

## 使用流程

```
1. 复制 ai-doc/ 到新项目根目录
2. 读取 prompts/ 了解可用 AI 动作
3. 读取 project-doc/ 了解项目文档
4. 告诉 AI："请读取 ai-doc/ 并执行 [动作]"
5. AI 根据目录结构生成/更新文档
```
