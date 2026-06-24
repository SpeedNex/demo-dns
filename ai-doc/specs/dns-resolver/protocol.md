# dns-resolver 协议与运行细节

## 1. 支持协议范围

| 协议 | MVP | 后续 | Profile 识别 |
|---|---|---|---|
| UDP 53 | 必须 | 持续支持 | source IP 绑定 / 默认 Profile |
| TCP 53 | 可延后 | Stage 03 | source IP 绑定 |
| DoH | 必须 | 持续支持 | path profile id |
| DoT | 可延后 | Stage 03 | SNI |
| DoQ | 不做 | Stage 07+ | 待定 |

## 当前实现映射（2026-06-15）

- 当前开发目录：`ocer-dns/dns-resolver`
- 已实现：
  - 域名归一化函数 `internal/rules/normalize.go`
  - allow 优先规则引擎 `internal/rules/engine.go`
  - 控制面 Agent：凭据直驱心跳、配置轮询、checksum 校验、ACK `internal/agent/agent.go`（凭据来自 `config.ControlPlane.{APIKey,Secret,NodeID}`）
  - 本地配置原子替换和 `profiles/active.json` 写入
  - 运行时配置结构 `internal/config/config.go`（含 `Validate()`）
  - `resolver install` 子命令 `cmd/dns-resolver/install.go`，把 console 预签发的 `(node_id, api_key, secret)` 写入 `configs/server.yaml`
  - DoH path / UDP source IP 的 Profile 识别 `internal/profile/resolver.go`
  - DoH HTTP 服务、UDP/TCP 53 DNS 服务、上游解析和查询日志 batch buffer `internal/doh/server.go`、`internal/dnsserver/server.go`、`internal/logging/buffer.go`
- 已有自动化验证：
  - `tests/normalize_test.go`
  - `tests/engine_test.go`
  - `tests/profile_resolver_test.go`
  - `internal/agent/agent_test.go`
  - `internal/logging/buffer_test.go`
- 已下线（**不得回退**）：
  - `agent.RegisterNode()` / `agent.loadIdentity()` / `agent.persistIdentity()`
  - `identity.json` 落盘
  - `bootstrap_token` / `identity_path` 配置字段
  - 任何"凭据缺失就走旧流程"的兜底 / 回退 / 虚拟代码
- 当前仍缺：
  - 多 Profile 隔离规则运行时，目前实际仍以单活跃 Profile 加载为主
  - metrics batch 上报的 portal 侧消费与展示

## 2. DoH 约定

推荐 URL：

```text
https://doh.example.com/dns-query/{profile_id}
```

也可支持：

```text
https://doh.example.com/{profile_id}/dns-query
```

要求：

- 支持 GET 和 POST。
- Content-Type：`application/dns-message`。
- 不在日志中记录完整 URL token；只记录 profile_id。
- profile_id 不应可枚举，推荐 ULID / UUID / 随机短码。

## 3. UDP 53 约定

MVP 中 UDP 通过 source IP 绑定到设备或 Profile：

```text
source_ip → devices.source_ip → profile_id
```

风险：

- 家庭 NAT 下多个设备共享公网 IP。
- 移动网络 IP 频繁变化。
- 企业出口 NAT 会导致误归属。

缓解：

- UDP 仅作为兼容入口。
- UI 中提示用户 DoH 更准确。
- 后续支持 EDNS device id 或专属 IP。

## 4. 拦截响应

| block_response | 行为 |
|---|---|
| `nxdomain` | 返回 NXDOMAIN |
| `zero_ip` | A 返回 0.0.0.0，AAAA 返回 :: |
| `refused` | 返回 REFUSED |
| `block_page` | A / AAAA 指向阻断页 IP，后续实现 |

MVP 默认：`nxdomain`。

## 5. 上游 DNS

配置示例：

```yaml
upstreams:
  - address: "1.1.1.1:53"
    protocol: udp
    timeout: 1500ms
    weight: 100
  - address: "9.9.9.9:53"
    protocol: udp
    timeout: 1500ms
    weight: 100
```

要求：

- 单次上游超时后切换下一个 upstream。
- 全部失败返回 SERVFAIL。
- 记录 upstream_error_count。
- 不因日志上报失败影响 DNS 响应。

## 6. 缓存

缓存 key：

```text
profile_id + qname + qtype + decision_context
```

要求：

- BLOCK 响应可使用短 TTL，例如 60s。
- ALLOW 上游响应遵守 DNS TTL，但应设置最大 TTL。
- 配置版本变化后清理或隔离旧版本缓存。

## 7. DNSSEC / ECS / QNAME minimization

MVP 可不强制实现，但配置中必须预留：

```json
{
  "dnssec_validate": false,
  "ecs_enabled": false,
  "qname_minimization": false
}
```

生产阶段再决定默认策略。

## 8. 日志字段映射

| DNS 概念 | 日志字段 |
|---|---|
| QNAME | `query_name` |
| QTYPE | `query_type` |
| RCODE | `rcode` |
| 规则动作 | `action` |
| 匹配原因 | `reason` |
| Profile | `profile_id` |
| 设备 | `device_id` |
| 节点 | `node_id` |
| 延迟 | `latency_ms` |

## 9. 错误处理

| 错误 | DNS 响应 | 日志 action / reason |
|---|---|---|
| Profile 不存在 | 可走默认上游或 REFUSED，按配置 | `allowed` / `profile_not_found` |
| 配置未加载 | SERVFAIL 或 fallback config | `error` / `config_not_loaded` |
| 上游超时 | SERVFAIL | `error` / `upstream_timeout` |
| 规则编译失败 | 保持旧配置 | 不影响查询 |

## 10. 测试用例

必须覆盖：

```text
NormalizeDomain
RuleEngine exact / suffix / wildcard
Allow 优先 Deny
DoH path 解析 profile_id
UDP source IP 映射 profile_id
Config checksum mismatch 不应用
Config hot reload 成功 ACK
Log upload failure 写 buffer
Upstream timeout fallback
config.Validate() 缺 api_key/secret/node_id 拒绝启动
resolver install 写入 server.yaml 后 resolver 启动成功
Agent 心跳 / 拉配置 / ACK 全部带 Bearer + HMAC 双因子
```
