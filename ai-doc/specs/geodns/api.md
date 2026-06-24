# geodns API / 协议规格

> `geodns` 负责服务入口调度，将 `dns.example.com`、`doh.example.com` 等服务域名解析到健康 resolver 节点。它不参与每一次用户 DNS 过滤查询。

## 1. 权威 DNS 行为

## 当前实现映射（2026-06-12）

- 当前开发目录：`ocer-dns/geodns`
- 已实现：
  - 健康视图模型与 HTTP client 草案 `internal/healthview/*`
  - 按 region 和 weight 选优的路由逻辑 `internal/router/router.go`
- 当前配置 URL 约定：
  - `GET /api/v1/internal/geodns/health-view`
- 已有自动化验证：
  - `tests/router_test.go`
- 当前仍缺：
  - 真正的权威 DNS server
  - GeoIP
  - ECS 和多答案返回

注意：ops 监控只关心节点在线/离线，geodns 路由不再基于 `qps_1m` / `capacity_qps` 做"健康度降权"。`routing.overload_threshold` 配置项已弃用但保留兼容。

### 1.1 输入

- 查询域名：如 `dns.example.com`、`doh.example.com`、`dot.example.com`。
- 查询类型：`A` / `AAAA`。
- 来源 IP：递归 DNS 的来源 IP，必要时使用 ECS。
- 本地健康节点视图。

### 1.2 输出

- 一个或多个 resolver 节点 IP。
- TTL，建议 MVP 30-120 秒。

示例：

```text
doh.example.com. 60 IN A 203.0.113.10
doh.example.com. 60 IN AAAA 2001:db8::10
```

## 2. 健康视图同步

geodns 周期调用：

```http
GET {DNS_CONSOLE_URL}/api/v1/internal/geodns/health-view
```

响应必须符合 `contracts/geodns-health-view.schema.json`。

同步策略：

| 项目 | 建议 |
|---|---|
| 拉取周期 | 5-15 秒 |
| 本地视图 TTL | 30 秒 |
| 失败处理 | 使用最近一次健康视图 |
| 长时间失败 | 仅剔除失败节点，不引入 fallback；所有节点失败时返回 SERVFAIL |

## 3. 路由策略

选择顺序：

```text
1. 过滤 status != online 的节点
2. 过滤不支持目标协议的节点
3. 根据来源 IP 计算 region / country
4. 匹配同 region 节点
5. 若无同 region，匹配同洲或全局节点
6. 按 weight 选优
7. 节点健康度降权已下线——ops 监控只关心 online/offline
8. 返回最多 N 个 A / AAAA 记录
```

## 4. Admin / Debug API

可选管理接口，仅内网开放：

```http
GET /healthz
GET /readyz
GET /debug/routes
GET /debug/nodes
POST /debug/reload
```

## 5. 配置示例

```yaml
server:
  listen: ":53"
  zones:
    - "example.com."

console:
  health_view_url: "https://dns-console.example.com/api/v1/internal/geodns/health-view"
  hmac_key_id: "geodns"
  hmac_secret: "secret"
  pull_interval: 10s

routing:
  default_ttl: 60
  max_answers: 2
  # overload_threshold 已弃用：ops 监控只关心 online/offline，不再基于 QPS/CPU/MEM 做降权
  overload_threshold: 0.8
  fallback_ipv4:
    - "203.0.113.100"
  fallback_ipv6: []
```

## 6. 不做事项

- 不存储用户规则。
- 不查询 MySQL。
- 不接收 resolver 查询日志。
- 不作为递归 resolver。
- 不修改 Profile 配置。
