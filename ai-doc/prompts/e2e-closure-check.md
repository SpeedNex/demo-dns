# E2E Closure Check 前后端闭环检查提示词

> 用于 AI 验证某个功能是否真正完成**前后端完整闭环**
>
> ⚠️ **本文件是新建的闭环检查工具**。前后端各自的单端审查请使用：
> * 前端审查 → `prompts/frontend-review.md`（引用 `frontend-ui.md`）
> * 后端审查 → `prompts/review.md`
>
> 本文件**不重复**单端审查的具体检查项，只关注"前端→后端→数据库→前端"这条链路是否真正联通。

---

## 检查目标

验证指定功能是否真正形成以下完整闭环：

```text
用户操作前端页面
  → 前端路由正确
  → 前端页面存在且可访问
  → 前端 API 方法已定义
  → 请求真正发出（非假数据 / mock）
  → 后端路由存在且注册
  → 中间件鉴权正确
  → Controller 已实现
  → Service 已实现
  → 数据库表存在且字段匹配
  → 响应结构统一
  → 前端正确处理成功 / 失败 / 空数据
  → 状态反馈（loading / empty / error）完整
```

任何一个环节缺失或断开，都不能称为"功能已完成"。

---

## 检查准备（强制，禁止跳过）

检查前必须按以下顺序阅读：

1. `project-doc/04-FEATURES.md` ← 确认功能蓝图与前后端边界
2. `specs/portal-web/api.md` ← 后端接口清单
3. `specs/portal-web/data-schema.md` ← 数据库字段定义
4. `rules/coding.md` ← 后端硬约束（财务字段、查询隔离、错误处理等）
5. `rules/ui.md` ← 前端 UI / 表单 / 表格规范
6. `contracts/openapi.yaml` ← API 契约（路径 / 方法 / 参数 / 响应 / 鉴权）
7. 功能设计文档或需求说明（由调用方提供）

阅读完成后**不要马上开始检查**，先输出：

```markdown
### 闭环检查计划

1. **功能名称**：[本次要检查的功能]
2. **检查范围**：[明确包含的页面和接口]
3. **检查方式**：[静态代码审查 / 运行态验证 / 混合]
4. **排除范围**：[明确不检查的部分]
```

确认后再开始。

---

## 检查原则

1. **端到端**：从用户点击到数据落库再到页面展示，整条链路必须走通
2. **证据驱动**：每个检查项都要有文件路径 / 代码证据，不接受"应该已联"这种描述
3. **不靠猜测**：信息不足时标注 `⚠️ Needs Verification`
4. **范围边界**：只检查调用方指定的功能，不顺手检查无关功能
5. **不重复单端审查**：UI 一致性、代码风格、安全漏洞等单端问题不在本文件范围内，引用对应审查提示词即可

---

## 检查清单（12 项，逐项核实）

对以下 12 项逐项给出判定（✅ 已完成 / ❌ 缺失 / ⚠️ 部分完成-说明）：

### 1. 页面入口

- [ ] 功能入口页面存在（`web/src/views/**/*.vue`）
- [ ] 页面可通过导航菜单 / 路由直接访问
- [ ] 无"死链"或空白页

### 2. 前端路由

- [ ] `web/src/router/index.js` 中存在对应路由
- [ ] 路由守卫（权限、登录状态）配置正确
- [ ] 路由参数 `/path/:id` 定义与跳转到该页面的链路一致

### 3. 前端 API 方法

- [ ] `web/src/api/` 中存在对应 API 请求方法
- [ ] HTTP 方法（GET/POST/PUT/DELETE）与后端契约一致
- [ ] 请求路径与 `contracts/openapi.yaml` 一致
- [ ] 请求参数类型与后端期望一致

### 4. 请求真实联通（非假数据 / mock）

- [ ] 前端代码中无硬编码的假数据作为"暂时展示用"
- [ ] 无 `setTimeout(() => res = [...mock...])` 这种 mock 模式
- [ ] 请求层（`api/client.js`）已配置 baseURL、拦截器、错误处理
- [ ] 若有 mock 配置（如 `vite.config.js` 的 server.proxy 到 mock server），需标注风险

### 5. 后端路由

- [ ] `routes/v1/*.php` 中存在对应路由注册
- [ ] 路由前缀（`/api/v1/user/`、`/api/v1/admin/`、`/api/v1/agent/`、`/api/v1/internal/`）与 API 路径约定一致
- [ ] 路由方法（GET/POST/PUT/DELETE）与前端请求一致

### 6. 中间件鉴权

- [ ] 白名单接口（`/api/v1/auth/*`）无鉴权中间件
- [ ] 用户接口（`/api/v1/user/*`）使用 `user.only`（或等同 Sanctum Token 校验）
- [ ] 管理员接口（`/api/v1/admin/*`）使用 `admin.only` + 权限中间件
- [ ] Agent 接口（`/api/v1/agent/*`）使用 `node.api_key` 鉴权
- [ ] 历史路径（`/api/v1/member/*`、`/api/v1/agent/*` 老命名）已无路由引用

### 7. Controller 实现

- [ ] Controller 文件存在于 `app/Http/Controllers/Api/V1/{Module}/`
- [ ] Controller 方法签名正确（Request 注入 / 参数绑定）
- [ ] 调用 Service 层，不在 Controller 写业务逻辑
- [ ] 返回统一响应结构（`ApiResponse<T>` 或项目约定格式）

### 8. Service 实现

- [ ] Service 文件存在于 `app/Domain/{Domain}/`
- [ ] 事务边界正确（多表操作用 `DB::transaction`）
- [ ] 参数显式校验（类型、范围、格式）
- [ ] 异常处理完整（不吞异常、不返回兜底值掩盖问题）
- [ ] 查询带租户/团队隔离（`WHERE owner_user_id = :current_user_id` 或等价）

### 9. 数据库表与字段

- [ ] 相关表存在于 `database/migrations/`（或 SQL 迁移）
- [ ] 字段名、类型、索引与前端传参 / Controller 期望一致
- [ ] 财务字段使用 `amount_minor bigint`，禁止 float/double
- [ ] 外键引用使用正确的主键名（`uid` / `admin_id` / `id`）
- [ ] **表名不带 `dns_` 前缀**（Laravel 会自动加前缀，代码中写裸表名）

### 10. 响应结构统一

- [ ] 成功响应：`{ code: 0, message: "ok", data: ... }` 或项目约定
- [ ] 错误响应：包含可处理的错误码和可读 message
- [ ] 分页响应：包含 `total` / `per_page` / `current_page` / `data`
- [ ] 敏感字段已过滤（密码 hash、Token 等不返回前端）

### 11. 前端状态处理

- [ ] 成功：列表刷新 / 弹窗关闭 / 路由跳转 / 成功提示
- [ ] 失败：接口错误码解析 + 可理解提示（非"操作失败"）
- [ ] 空数据：`el-empty` 或等价组件展示
- [ ] 加载中：loading 状态 + 按钮禁用 / 防重复点击
- [ ] Token 失效：跳转登录页 + 清理本地态

### 12. 测试与自测

- [ ] 有 Feature Test 覆盖核心链路（`tests/Feature/`）
- [ ] 测试用例覆盖：成功 / 失败 / 空数据 / 权限拒绝 / 参数非法
- [ ] 有手动自测步骤文档（含预期结果）
- [ ] 无 `console.log` / `TODO` / `FIXME` 阻塞项遗留

---

## 闭环链路可视化输出

对每条检查项，必须输出证据链路：

```markdown
### 功能：[功能名称] 闭环链路

| 环节 | 证据 | 判定 |
|------|------|------|
| 前端页面 | `web/src/views/xxx.vue:23` 入口按钮 | ✅ |
| 前端路由 | `web/src/router/index.js:45` 路由定义 | ✅ |
| API 方法 | `web/src/api/xxx.js:12` `fetchList` 函数 | ✅ |
| 真实联通 | 无 mock（扫描确认） | ✅ |
| 后端路由 | `routes/v1/user.php:67` `Route::get(...)` | ✅ |
| 中间件 | 使用 `user.only` 中间件 | ✅ |
| Controller | `app/Http/Controllers/Api/V1/User/XxxController.php:34` `index` 方法 | ✅ |
| Service | `app/Domain/Xxx/XxxService.php:78` `getList` 方法 | ✅ |
| 数据库表 | `database/migrations/2026_xx_xx_xxx_create_xxx.php` 存在 | ✅ |
| 字段匹配 | 请求参数 `name` ↔ 表字段 `name` varchar(255) | ✅ |
| 响应结构 | `{ code:0, data:{ total:10, data:[...]} }` | ✅ |
| 状态处理 | `el-empty` / `loading` / `ElMessage.error` 均已实现 | ✅ |
| 测试 | `tests/Feature/XxxTest.php` 存在 | ⚠️ 缺失失败场景测试 |
```

---

## 风险等级

### P0 阻塞上线

* 前端页面/路由/接口 任一缺失
* 前端未真正调用后端（假数据 / mock）
* 后端路由/Controller/Service 任一缺失
* 数据库表或关键字段缺失
* 鉴权绕过（该校验未校验）

### P1 严重问题

* 响应结构与前端期望不一致
* 失败/空状态无提示
* 无测试覆盖核心链路
* 前端loading缺失导致重复提交

### P2 中等问题

* 提示文案不清晰
* 错误码未完整处理
* 分页参数与后端约定不一致

### P3 优化建议

* 边界场景测试覆盖不足
* 错误提示可更友好

---

## 输出格式（强制）

```markdown
# 前后端闭环检查报告

## 1. 功能信息

| 项目 | 内容 |
|------|------|
| 功能名称 | |
| 检查范围 | |
| 检查方式 | 静态 / 运行态 / 混合 |
| 排除范围 | |

## 2. 闭环链路可视化

[见上方模板，逐项填写证据与判定]

## 3. 已完成 vs 缺失清单

### 已完成

| 环节 | 证据 |
|------|------|

### 缺失 / 部分完成

| 环节 | 缺失内容 | 风险等级 | 建议修复顺序 |

## 4. 风险评估

| 风险等级 | 问题 | 影响 | 建议 |
|----------|------|------|------|

## 5. 检查结论

- [ ] ✅ 闭环完成，可上线
- [ ] ⚠️ 主体完成，P1 问题需修复
- [ ] ❌ 未闭环，需补齐缺失环节后再上线

原因：[说明]

## 6. 修复顺序建议

按优先级列出需要修复的项目（P0 → P1 → P2）：

1. ...
2. ...
```

---

## 检查后自检

- [ ] 每条检查项都有代码证据，未用"应该已联"这种描述
- [ ] 假数据 / mock 已明确标注（若存在）
- [ ] 数据库表名已确认无 `dns_` 前缀问题
- [ ] 鉴权中间件与路由分组（user/admin/agent/internal）一致
- [ ] 权限校验不仅前端控制，后端也有校验
- [ ] 信息不足处已标注 `⚠️ Needs Verification`
- [ ] 未复制 `prompts/frontend-review.md` 或 `prompts/review.md` 的单端检查项

---

## 检查入口示例

```text
请检查"用户 Profile 编辑"功能是否前后端完整闭环。

1. 先阅读 prompts/e2e-closure-check.md（本文件）
2. 再依次阅读：
   - project-doc/04-FEATURES.md
   - specs/portal-web/api.md
   - specs/portal-web/data-schema.md
   - rules/coding.md（后端硬约束）
   - rules/ui.md（前端规范）
   - contracts/openapi.yaml

阅读后先输出闭环检查计划，确认后再开始检查。

禁止检查 Profile 编辑以外的功能。
```

---

## 与其它提示词的关系

| 场景 | 应使用的提示词 |
|------|----------------|
| 前端**审查** | `prompts/frontend-review.md` → 引用 `frontend-ui.md` |
| 后端**审查** | `prompts/review.md` |
| 前后端**闭环检查**（本文件） | `prompts/e2e-closure-check.md` |
| 前端**开发** | `rules/ui.md` + `prompts/feature-start.md` |
| 后端**开发** | `prompts/feature-start.md` |
| **代码重构** | `prompts/refactor.md` |
| **Bug 修复** | `prompts/bug-fix.md` |
