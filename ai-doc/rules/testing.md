# 测试规范（Testing Standards）

> 测试策略与规范，确保代码质量和系统稳定性。

---

## 测试层级

| 层级 | 框架 | 覆盖目标 | 覆盖率目标 |
|------|------|---------|-----------|
| 单元测试 | PHPUnit（PHP）/ Go test（Go） | Service 层、工具函数 | ≥ 80% |
| 功能测试 | Laravel Test | API 端点、请求/响应 | 100% 端点 |
| 集成测试 | Go test + TestContainers | 跨服务流程（发布→同步→解析） | 关键路径 |
| E2E 测试 | Playwright | 前端用户操作全流程 | 核心流程 |

---

## 单元测试规范

### PHP（Laravel）
- 测试文件：`tests/Unit/{Module}/{Service}Test.php`
- 方法命名：`test_{method}_{scenario}_{expected}`
  ```php
  public function test_create_profile_with_valid_data_returns_success()
  ```
- 使用 Factory 创建测试数据
- 每个方法只测一个行为

### Go
- 测试文件：与源码同级 `_test.go`
- 方法命名：`Test{Function}_{Scenario}`
  ```go
  func TestProfileConfig_Load_ValidJSON_Success(t *testing.T)
  ```
- 使用 Table-Driven Tests

---

## 功能测试规范

- 测试文件：`tests/Feature/{Module}/{Controller}Test.php`
- 覆盖：
  - ✅ 正常请求（200）
  - ✅ 参数校验失败（422）
  - ✅ 未认证（401）
  - ✅ 无权限（403）
  - ✅ 资源不存在（404）

```php
public function test_create_profile_returns_422_when_name_missing()
{
    $response = $this->postJson('/api/v1/profiles', []);
    $response->assertStatus(422);
}
```

---

## 集成测试规范

测试跨服务关键路径：
- Profile 发布 → Control Plane → NATS → dns-resolver 同步
- DNS 查询 → 规则匹配 → 日志写入 → ClickHouse 查询
- 用户注册 → 订阅 → 计费 → 用量限制

---

## 测试数据管理

- 单元测试：Factory + Faker
- 集成测试：Docker 启动真实依赖（MySQL / Redis / NATS）
- 测试数据库：独立 `testing` 数据库，每次运行迁移

---

## 执行命令

```bash
# Laravel
composer test                    # 全部测试
php artisan test --testsuite=Feature  # 仅功能测试
php artisan test --testsuite=Unit     # 仅单元测试
php artisan test --filter=Profile     # 仅 Profile 模块

# Go
go test ./...                          # 全部测试
go test ./internal/profile/...         # 仅 Profile 模块
go test -run TestPublish -v            # 仅发布测试

# E2E
npx playwright test                    # 全部 E2E
```


## 财务测试要求

- 金额计算必须用整数断言，禁止 float 断言。
- 税费计算覆盖 ROUND_HALF_UP。
- 订单 / 发票 total 必须测试：`total = subtotal - discount + tax`。
- 支付 webhook 重放必须只入账一次。
- 退款累计不得超过原支付金额。
- 发票 finalized 后金额不可修改。
- credit note 不修改原发票金额。
- usage batch 重放不得重复累加 `usage_counters`。
- billing ledger 每个财务事实必须存在且幂等。
- 每日 reconciliation mismatch 必须可追踪。
