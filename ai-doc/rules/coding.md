# 编码规范（Coding Standards）

> 基于 PSR 标准的 PHP 编码规范，适用于 PHP 项目。

## 硬约束（V1 强制，违反直接拒收）

下列条目是 V1 范围内的强制约束。AI 生成代码、`code-review`、PR 合并、CI 检查**必须**严格按此执行。任意一条违规直接判定为不合格并要求重构。

### HC-01 V1 禁止 NATS 依赖
- V1 范围内**禁止**新增 NATS JetStream 依赖：
  - Go `go.mod` 不得出现 `github.com/nats-io/*`
  - PHP `composer.json` 不得出现 `nats/*` / `repeater/nats-client` / 同类包
- 文档中出现的 "NATS / event / stream / ingestion / worker" 全部按 V2+ 替换路径处理：
  - 异步日志 → 走 `dns-console-web` HTTP `/api/v1/agent/query-logs/batch` + 本地 buffer
  - 跨服务通知 → 走"轮询 + 短 TTL Redis 缓存"
  - 配置变更通知 → 走 resolver `current_version` 轮询
- 任何在 V1 范围内引入 `NATS` 客户端、订阅、发布、JetStream stream 的代码必须被拒绝。
- V2+ 启用 NATS 时再单独评估：见 `specs/nats/events.md`（V1 阶段不读、不实现、不测试）。

### HC-02 V1 禁止 resolver 自助注册
- V1 范围内**禁止**实现以下任意端点、字段、文件：
  - `POST /api/v1/agent/nodes/register` 端点
  - `bootstrap_token` 字段、`Authorization: Bootstrap ...` scheme
  - `identity.json` 文件 / 持久化 / 加载逻辑
  - 任何"首次启动 / 启动时无凭据 / 启动时拉取身份"的自助注册流程
- 唯一允许的凭据来源：`resolver install --console=... --node-id=... --api-key=... --secret=...` 一次性写入 `configs/server.yaml`；启动走 `cfg.Validate()`。
- 详见 `project-doc/06-MVP-SCOPE.md` 与 `08-DELIVERY-CRITERIA.md`。

### HC-03 V1 数据所有权与同步方向单一
- `portal-web` 独占 `profiles / profile_rules / profile_feature_settings / profile_versions` 写入；`dns-console-web` 不得直接读写这三张表。
- `portal-web` 不得直接写 `config_versions / publish_tasks`，只能通过 `dns-console-web` 的 `Internal API` 发起发布。
- 任意双向同步、双写、回流都判定为不合格。详见 `project-doc/02-MODULES.md` §1.4。

### HC-04 查询隔离（租户/团队可见域硬约束）
- 所有返回 `profiles / profile_rules / profile_feature_settings / devices / audit_logs / dns_logs` 的查询**必须**显式带 WHERE 过滤：
  - `WHERE owner_user_id = :current_user_id`（个人资源）
  - 或 `WHERE team_id IN (current_user 所属 team 列表)`（团队资源）
  - 管理员后台查询需 `AND is_admin = true`。
- 禁止裸 `SELECT *`、禁止 `LIMIT` 截断分页而不带 `WHERE`、禁止"先查全表再应用层过滤"。
- 单元测试 / Feature Test 必须包含：跨用户读取应得 0 行（不得 403 / 404 之外的非显式拒绝）。

### HC-05 财务字段硬约束
- 所有金额字段使用 `amount_minor bigint`，**禁止** float / double / decimal 浮点。
- `invoice/order total` 必须有 DB CHECK 或等价领域断言：`total = subtotal - discount + tax`。
- 发票 `finalized_at` 之后金额不可修改；只能用 `credit_notes / refunds / adjustments` 修正。
- usage 写接口必须支持 `Idempotency-Key`，重放同 batch 不得重复写 ClickHouse、不得重复增 `usage_counters`。
- 详见 `project-doc/08-DELIVERY-CRITERIA.md` 末段"财务与计费交付门槛"。

### HC-06 错误处理与资源释放
- `curl_exec` / `Http::*` 返回值必须显式检查 null / false / 状态码。
- `json_encode` 失败必须捕获并日志化；`json_decode` 失败必须有显式错误路径。
- 文件 / DB 句柄 / Redis 连接 / HTTP 客户端必须 `try / finally` 或框架封装释放。
- 异常路径**禁止**用兜底值（`null / 0 / []`）掩盖问题。

## PHP

### 命名（PSR-1 / PSR-4）
- 类名：`PascalCase`（如 `UserService`）
- 方法名：`camelCase`（如 `getUserById`）
- 变量名：`camelCase`（如 `$userList`）
- 常量：`SCREAMING_SNAKE_CASE`（如 `MAX_RETRY_COUNT`）
- 接口：`PascalCase` + 后缀 `Interface`（如 `CacheInterface`）
- 抽象类：`PascalCase` + 前缀 `Abstract`（如 `AbstractController`）
- 命名空间：`PascalCase`，与目录结构对应（PSR-4）

### 函数设计
- 单个函数不超过 50 行
- 单一职责原则
- 参数不超过 5 个，超过用数组或 DTO

### 错误处理
```php
// ✅ 正确：显式检查返回值
$result = DB::select('SELECT * FROM users WHERE id = ?', [$id]);
if (empty($result)) {
    throw new RuntimeException('User not found');
}

// ❌ 错误：直接使用未检查的结果
$user = DB::select('SELECT * FROM users WHERE id = ?', [$id])[0];
```

### 外部接口调用
```php
// ✅ 正确：检查 HTTP 状态码和响应体
$response = Http::get('https://api.example.com/data');
if (!$response->successful()) {
    Log::error('API call failed', ['status' => $response->status()]);
    return null;
}
$data = $response->json();
if (!is_array($data)) {
    return null;
}

// ❌ 错误：未检查响应状态
$data = Http::get('https://api.example.com/data')->json();
```

### 资源释放
```php
// ✅ 正确：使用 try-finally 确保释放
$file = fopen($path, 'r');
try {
    while (($line = fgets($file)) !== false) {
        // 处理行
    }
} finally {
    fclose($file);
}

// ✅ 推荐使用框架封装
$content = Storage::get($path);
```

## 数据库

### 索引命名
- 普通索引：`idx_{table}_{columns}`
- 外键：`fk_{table}_{ref_table}`
- 唯一索引：`uniq_{table}_{columns}`

### 查询规范
```sql
-- ✅ 正确：参数化查询
SELECT * FROM users WHERE email = ?;

-- ❌ 错误：字符串拼接
SELECT * FROM users WHERE email = '$email';
```

## PHP CS Fixer 推荐配置
```php
// .php-cs-fixer.php
return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR12' => true,
        'array_syntax' => ['syntax' => 'short'],
        'ordered_imports' => true,
    ]);
```

## 调试规范

### 调试原则
1. **可复现** — 问题必须能在相同条件下复现
2. **最小化** — 隔离问题，只改必要代码
3. **有回滚** — 修改前确保能恢复
4. **记录过程** — 调试步骤和结论写入 `project-doc/04-change-log.md`

### 调试流程
```
Step 1：复现问题 → Step 2：定位根因 → Step 3：修复代码
     ↓                    ↓                    ↓
Step 4：验证修复 → Step 5：记录归档
```

### 问题分类
| 类型 | 特征 | 处理优先级 |
|-----|------|-----------|
| 逻辑错误 | 行为与预期不符 | P0 |
| 性能问题 | 响应慢、超时 | P1 |
| 兼容性问题 | 特定环境/数据出错 | P1 |
| 安全问题 | 权限绕过、数据泄露 | P0 |

### 日志级别
| 级别 | 用途 | 示例 |
|-----|------|------|
| DEBUG | 开发调试 | 变量值、分支路径 |
| INFO | 正常流程 | 请求入口、响应出口 |
| WARNING | 异常但可处理 | 参数边界、超时重试 |
| ERROR | 错误需关注 | 异常捕获、连接失败 |

### 调试报告格式
```markdown
## 调试报告
### 问题描述
[现象描述]
### 根因分析
[根本原因]
### 修复方案
[修改内容]
### 验证结果
[测试结果]
### 影响范围
[涉及模块、功能]
```

### 调试检查清单
- [ ] 问题可复现
- [ ] 已记录触发条件
- [ ] 修改范围最小化
- [ ] 未破坏其他功能
- [ ] 相同条件验证通过
- [ ] 已更新 `project-doc/04-change-log.md`
