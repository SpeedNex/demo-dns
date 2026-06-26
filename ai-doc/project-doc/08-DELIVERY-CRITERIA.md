# 交付门槛与验收标准

> 本文件定义“什么时候可以说完成”。所有生成代码都必须按本文件验收。

## 1. 交付等级

| 等级 | 名称 | 可宣称内容 | 不可宣称内容 |
|---|---|---|---|
| L1 | 文档级 | 架构、接口、数据模型已定义 | 代码已可运行 |
| L2 | 代码草案 | 代码已生成，结构基本完整 | 可上线、可商用 |
| L3 | MVP 可运行 | 本地可启动、核心链路跑通、测试通过 | 高可用生产级 |
| L4 | 生产级 | 安全、监控、回滚、压测、运维证据齐全 | 无限制容量、无风险 |

当前文档包目标：支持生成 L3 MVP。达到 L4 还需要真实压测、安全审计、监控告警和灾备演练。

## 2. 通用验收

任何阶段完成必须提供：

- 变更文件清单。
- 数据库迁移执行结果。
- 后端测试结果。
- 前端构建结果。
- Go 测试和 vet 结果。
- API 示例请求和响应。
- 失败场景验证。
- 文档同步记录。

## 3. portal-web 验收

| 项目 | 标准 |
|---|---|
| 认证 | 注册、登录、退出、当前用户接口通过测试 |
| Profile | 只能访问自己的 Profile；管理员接口需后台权限 |
| 规则 | 域名归一化、重复检测、通配符校验 |
| 发布 | 发布生成不可变版本，调用 console internal API，有失败处理 |
| 日志 | 用户只能查看自己 Profile 的日志 |
| 审计 | 关键写操作有 audit log |
| 安全 | 无默认生产密码；敏感 token 不明文展示 |

## 4. portal-web(原 console 域) 验收

> 原 `dns-console-web` 已于 2026-06-15 至 2026-06-16 之间并入 `portal-web` 的总后台(原 console 域)子命名空间;以下验收项位于 `ocer-dns/portal-web/app/Http/Controllers/Api/V1/{Admin,Agent,Internal}/*` 与 `Domain/NodeToken`、`Domain/Heartbeat`、`Domain/ConfigVersion`、`Domain/Publish`、`Domain/HealthView`、`Domain/Ingest`、`Domain/RuleLibrary`、`Domain/SystemConfig`、`Domain/AdminConsoleAudit`。鉴权沿用 `shared.token:admin`(原 console 行为 100% 一致)与 `node.hmac`、`shared.token:internal` 三个中间件;数据库位于 `portal-web` 的 `ocer_dns` MySQL 库。

| 项目 | 标准 |
|---|---|
| 节点预创建 | 管理员在 `portal-web` 总后台通过 `POST /api/v1/admin/nodes` 预创建节点,调用 `POST /api/v1/admin/nodes/{nodeCode}/tokens` 签发 `(token=ocnd_xxx, hmac_secret=hmk_xxx)`;明文仅返回一次,`portal-web` 仅存 `token_hash` 与 `hmac_key_hash` + `hmac_secret_encrypted` |
| 节点安装 | `curl -fsSL <host>/build/install.sh \| sh -s -- --server=... --token=ocnd_xxx --node-id=xxx` → 下载二进制 → `geo-dns install` 调用 verify API 换取凭据 → 原子写入 `configs/server.yaml` |
| 心跳 | `POST /api/v1/node/nodes/heartbeat` token 校验、状态计算、`last_heartbeat_at` 更新 |
| 离线判断 | 超过阈值(默认 90s)标记 offline,健康视图不返回离线节点 |
| 配置版本 | `version` 单调递增,`checksum` 可验证 |
| 配置拉取 | `GET /api/v1/node/resolver/config` 无更新返回 204;有更新返回完整 bundle |
| ACK | `POST /api/v1/node/resolver/config/ack` applied / failed 都记录;failed 保存 `error_message` |
| 日志接收 | `POST /api/v1/node/query-logs/batch` batch 限制、幂等 `batch_id`、失败可重试 |
| 内部接口 | `shared.token:internal` 中间件校验 `Authorization: Internal <token>`;`shared.token:admin` 校验 `Authorization: Admin <token>`;`/api/v1/node/*` 使用 `node.hmac` (Bearer + HMAC-SHA256) |

## 5. dns-resolver 验收

| 项目 | 标准 |
|---|---|
| 启动 | 无外部 DB 也能启动 |
| 安装 | `resolver install` 写入 `configs/server.yaml`，启动时 `cfg.Validate()` 校验完整配置三元组 |
| 心跳 | 周期上报，控制台显示 online |
| 配置 | 拉取、checksum、原子替换、热加载、ACK |
| 查询 | DoH / UDP 至少支持 A / AAAA |
| 规则 | allow 优先 block；block 命中返回预期拦截响应 |
| 上游 | 多 upstream fallback；超时可配置 |
| 日志 | 异步 batch，不阻塞查询 |
| buffer | 上报失败写本地文件，恢复后重放 |
| 安全 | 不连接 MySQL；不调用 portal-web API |

## 6. geodns 验收

| 项目 | 标准 |
|---|---|
| 健康视图 | 可读取 online 节点列表 |
| 路由 | 返回符合 region / weight 的 resolver |
| 摘除 | offline 节点不再返回 |
| fallback | 无同区节点时返回全局备用节点 |
| 性能 | 内存路由表决策，不依赖实时数据库查询 |

## 7. 数据库验收

MySQL：

- 每张表有主键。
- 必要外键、唯一约束、索引明确。
- 时间字段统一 `created_at`、`updated_at`，必要时 `deleted_at`。
- JSON 字段使用 `jsonb` 并说明结构。
- token / secret 只能存 hash 或加密值。

ClickHouse：

- 日志表按时间分区。
- TTL 与套餐保留策略一致或可覆盖。
- 排序键支持常用查询：profile、time、domain、action。
- 大字段和敏感字段脱敏。

## 8. API 验收

- OpenAPI 路径与实际路由一致。
- 所有写接口有鉴权和参数校验。
- 错误响应统一结构。
- 401 / 403 / 404 / 409 / 422 / 429 / 500 明确。
- Internal / Agent API 不使用用户登录态鉴权。

统一错误结构：

```json
{
  "error": {
    "code": "VALIDATION_FAILED",
    "message": "The given data was invalid.",
    "details": {}
  },
  "trace_id": "trace_01H..."
}
```

## 9. 安全与隐私验收

- 生产环境首次启动必须通过 CLI 或一次性 token 创建管理员。
- 禁止生产默认账号 `admin / 123456`。
- client IP 默认 hash，不保存明文。
- 日志保留周期可配置，支持按套餐 / 合规删除。
- HMAC secret、node token、JWT secret 不提交到仓库。
- resolver 控制接口不暴露在公网可写入口。
- 所有 webhook / internal 调用有签名和重放保护。

## 10. 运行验收命令建议

```bash
# portal-web 交付标准
composer install
php artisan migrate --force
php artisan test
npm ci
npm run build

# dns-resolver / geodns
go test ./...
go vet ./...
go build ./...

# 部署配置
docker compose config

# 契约校验
python -m json.tool contracts/resolver-config.schema.json
python -m json.tool contracts/node-heartbeat.schema.json
python -m json.tool contracts/query-log.schema.json
python -m json.tool contracts/resolver-metrics.schema.json
```

## 11. MVP 端到端验收

必须记录以下 12 步证据：

```text
1. 用户注册成功
2. 用户登录成功
3. 创建 Profile 成功
4. 添加 block rule: ads.example.com
5. 发布配置版本成功
6. 管理员预创建节点并签发 api_key / secret
7. resolver 凭三元组启动后心跳 online
8. resolver 拉取到 config_version=1
9. DoH 查询 ads.example.com 被拦截
10. DoH 查询 example.com 被放行
11. 查询日志批量上报成功
12. portal-web 能看到日志，console-web 能看到节点版本
```

> 节点凭据流程：管理员在 `portal-web` 总后台通过 `POST /api/v1/admin/nodes` 预创建节点，再通过 `POST /api/v1/admin/nodes/{nodeCode}/tokens` 签发 `token=ocnd_xxx` + `hmac_secret=hmk_xxx`，安装时由 `curl -fsSL <host>/build/install.sh | sh -s -- --token=ocnd_xxx --node-id=xxx` 一键安装，内部调用 `POST /api/v1/node/tokens/verify` 换取 api_key + secret。不存在 `POST /api/v1/agent/nodes/register`、`bootstrap_token`、`identity.json` 任何自助注册路径。



## 财务与计费交付门槛

财务是重点模块，未满足以下条件不得声称计费可用：

- 所有金额字段使用 `amount_minor bigint`，不使用 float/double。
- 所有订单、发票、支付、退款都有 `currency`。
- invoice/order total 必须有数据库 CHECK 或等价领域断言：`total = subtotal - discount + tax`。
- 发票 finalized 后金额不可修改，只能用 credit note / refund / adjustment 修正。
- 支付 webhook 以 `provider + provider_event_id` 幂等。
- 下单、支付、退款、usage batch 写接口必须支持 Idempotency-Key。
- usage billing 只从 query log batch 派生，不从 heartbeat / metrics 扣费。
- 重放同一个 query log batch 不得重复写 ClickHouse，不得重复增加 usage_counters。
- 每日支付对账必须记录 reconciliation_runs / reconciliation_items。
- 必须有金额舍入、税额、部分退款、重复 webhook、重复 usage batch、发票不可变的自动化测试。
