# dns-resolver 运行时模型

> `dns-resolver` 是部署在 DNS 节点上的 Go 单二进制，包含 DNS 服务和 Agent。它处理真实 DNS 查询，也负责向 `portal-web` 心跳、拉配置、ACK、日志和指标上报。它不得直接连接 MySQL、Redis、ClickHouse。
>
> **节点凭据来源固定**：api_key / secret / node_id 由 `resolver install --console=... --node-id=... --api-key=... --secret=...` 写入 `configs/server.yaml`，**不存在** 自助注册、bootstrap token、`identity.json` 兜底。启动只走 `cfg.Validate()`。

## 1. 进程结构

```text
┌─────────────────────────────────────────────────────────────┐
│                       dns-resolver                           │
├─────────────────────────────────────────────────────────────┤
│ DNS Server                                                   │
│  ├─ UDP 53 / TCP 53                                          │
│  ├─ DoH /dns-query/{profile_id}                              │
│  ├─ DoT :853                                                 │
│  ├─ ProfileResolver                                          │
│  ├─ DeviceResolver                                           │
│  ├─ RuleEngine                                               │
│  ├─ DNS Cache                                                │
│  └─ Upstream Resolver                                        │
├─────────────────────────────────────────────────────────────┤
│ Agent                                                        │
│  ├─ Credentials (api_key + secret + node_id from yaml)      │
│  ├─ Heartbeat (Bearer + HMAC，只表达 online/offline)         │
│  ├─ Config Poll / Pull                                       │
│  ├─ Checksum Verify                                          │
│  ├─ Atomic Config Store                                      │
│  ├─ Hot Reload                                               │
│  ├─ Config ACK                                               │
│  └─ Query Log Batch Upload                                   │
└─────────────────────────────────────────────────────────────┘
```

## 2. 本地目录

```text
/etc/smart-dns/
├── server.yaml         # 控制面凭据 + 节点元数据（resolver install 写入）
├── data/
│   └── profiles/
│       └── {prefix2}/{profileID}.json
├── rules/
│   ├── adblock.bin
│   ├── security.bin
│   └── parental.bin
├── cache/
│   └── dns-cache.db
├── buffer/
│   └── query-log-20260612-1000.jsonl
└── logs/
    └── resolver.log
```

> `identity.json` 已被删除；所有凭据均在 `server.yaml` 的 `control_plane.{api_key,secret,node_id}` 字段中，文件权限 0600。

## 3. server.yaml

```yaml
node:
  node_name: resolver-hk-01
  region: ap-east-1
  country: HK
  city: Hong Kong
  provider: aws
  public_ipv4: 203.0.113.10
  public_ipv6: "2001:db8::10"
  supported_protocols: [udp, tcp, doh, dot]

server:
  listen_udp: ":53"
  listen_tcp: ":53"
  listen_doh: ":443"
  listen_dot: ":853"
  read_timeout: 3s
  write_timeout: 3s

control_plane:
  endpoint: "https://console.ocerlink.com"
  # 以下三项必须由 `resolver install` 写入；任何一项为空，resolver 启动直接拒绝
  api_key: "ak_xxx"             # 由 `resolver install --api-key=...` 写入
  secret: "sk_xxx"              # 由 `resolver install --secret=...` 写入
  node_id: "hk-01"              # 由 `resolver install --node-id=...` 写入
  heartbeat_interval: 30
  config_poll_interval: 30
  request_timeout: 5s

security:
  tls_cert_file: "/etc/smart-dns/tls/fullchain.pem"
  tls_key_file: "/etc/smart-dns/tls/privkey.pem"
  hmac_skew_seconds: 300

upstreams:
  - address: "1.1.1.1:53"
    protocol: udp
    timeout: 1500ms
  - address: "9.9.9.9:53"
    protocol: udp
    timeout: 1500ms

logging:
  level: info
  batch_size: 500
  flush_interval: 5s
  buffer_dir: "/etc/smart-dns/buffer"
  max_buffer_size_mb: 1024
```

> `metrics.prometheus_listen` 仍保留供本地 Prometheus 抓取，但 resolver 不再向 `portal-web` 上报运行指标；ops 监控只关心节点在线/离线。

`agent.bootstrap_token` / `agent.identity_path` 字段已删除。

## 4. 凭据存储（已替代 identity.json）

**已删除** `identity.json`。所有节点凭据直接保存在 `configs/server.yaml` 的 `control_plane` 段：

```yaml
control_plane:
  endpoint: "https://console.ocerlink.com"
  api_key: "ak_xxx"        # Bearer 身份凭证（明文保存于 0600 文件）
  secret: "sk_xxx"         # HMAC-SHA256 签名密钥（明文保存于 0600 文件）
  node_id: "hk-01"         # console 预签发节点 ID
```

要求：

- 文件权限 `0600`，不写入 Git。
- 凭据**仅**由 `resolver install --console=... --node-id=... --api-key=... --secret=...` 写入；reissue 由 `POST /api/v1/admin/nodes/{node_id}/credentials` 触发后用 `install --force` 覆盖。
- 启动时 `cfg.Validate()` 校验三字段非空，否则 `log.Fatalf` 拒绝启动。

## 5. ProfileConfig 运行时结构

```go
type ResolverConfig struct {
    Version     int64           `json:"version"`
    Checksum    string          `json:"checksum"`
    GeneratedAt time.Time       `json:"generated_at"`
    Profiles    []ProfileConfig `json:"profiles"`
    Rulesets    []RulesetRef    `json:"rulesets"`
    Upstreams   []Upstream      `json:"upstreams"`
    Quota       QuotaBundle     `json:"quota"`
    Signature   string          `json:"signature"`
}

type ProfileConfig struct {
    ProfileID      string        `json:"profile_id"`
    UserID         string        `json:"user_id"`
    TeamID         *string       `json:"team_id"`
    Version        int64         `json:"version"`
    DefaultAction  string        `json:"default_action"` // allow / block
    BlockResponse  string        `json:"block_response"` // nxdomain / zero_ip / refused
    Security       FeatureSwitch `json:"security"`
    Adblock        FeatureSwitch `json:"adblock"`
    Parental       FeatureSwitch `json:"parental"`
    Devices        []DeviceEntry `json:"devices"`
    Rules          []RuleEntry   `json:"rules"`
    Quota          ProfileQuota  `json:"quota"`
}

type RuleEntry struct {
    RuleID           string `json:"rule_id"`
    ListType         string `json:"list_type"`  // allow / block
    MatchType        string `json:"match_type"` // exact / suffix / wildcard
    Domain           string `json:"domain"`
    NormalizedDomain string `json:"normalized_domain"`
    Action           string `json:"action"`     // allow / block / rewrite
    Category         string `json:"category"`
    Enabled          bool   `json:"enabled"`
}
```

## 6. Profile 识别

| 协议 | 优先识别方式 | 备选方式 | 风险 |
|---|---|---|---|
| DoH | URL path：`/dns-query/{profile_id}` | Header：`X-Profile-ID` | 推荐方式 |
| DoT | SNI：`{profile_id}.dot.example.com` | 客户端证书 / source IP | SNI 需要证书策略 |
| UDP | source IP 绑定 | EDNS Client Subnet / device key | NAT 场景可能误识别 |
| TCP | source IP 绑定 | Proxy Protocol | 依赖入口代理 |

MVP 推荐：DoH path + UDP source IP 绑定。DoT 和更复杂设备识别放 Stage 03。

## 7. 域名归一化

查询进入 RuleEngine 前必须：

```text
1. 去掉末尾根点：example.com.
2. 转小写。
3. IDNA 转 ASCII Punycode。
4. 拒绝非法 label。
5. 限制最大长度 253。
```

示例：

```text
WWW.Example.COM. → www.example.com
例子.测试 → xn--fsqu00a.xn--0zwm56d
```

## 8. RuleEngine

### 8.1 数据结构

```go
type RuleEngine struct {
    AllowExact  map[string]RuleDecision
    AllowSuffix *DomainTrie
    blockExact   map[string]RuleDecision
    blockSuffix  *DomainTrie
    RewriteMap  map[string]RewriteTarget
}
```

### 8.2 匹配语义

| match_type | 输入示例 | 命中 |
|---|---|---|
| exact | `ads.example.com` | 只命中 `ads.example.com` |
| suffix | `example.com` | 命中 `example.com` 和 `*.example.com` |
| wildcard | `*.example.com` | 命中一层或多层子域，按实现文档固定 |

建议内部把 suffix 和 wildcard 都编译到反向 Trie，但保留原始类型用于审计。

### 8.3 决策结构

```go
type Decision struct {
    Action   string // allow / blocked / rewrite
    Reason   string // allowlist / blocklist / security / parental / adblock / default
    Category string
    RuleID   string
    RCode    string // NOERROR / NXDOMAIN / REFUSED
}
```

## 9. DNS 查询处理伪代码

```go
func HandleQuery(ctx context.Context, req DNSRequest) DNSResponse {
    started := time.Now()
    profile := profileResolver.Resolve(req)
    if profile == nil {
        return upstream.Resolve(req)
    }

    qname := NormalizeDomain(req.QName)
    device := deviceResolver.Resolve(req, profile)

    if cached := cache.Get(profile.ID, qname, req.QType); cached != nil {
        logAsync(req, profile, device, cached.Decision, time.Since(started))
        return cached.Response
    }

    decision := ruleEngine.Match(profile, qname, req.QType)
    if decision.Action == "blocked" {
        resp := blockResponse(req, profile.BlockResponse)
        logAsync(req, profile, device, decision, time.Since(started))
        return resp
    }

    resp := upstream.Resolve(req)
    cache.Set(profile.ID, qname, req.QType, resp)
    logAsync(req, profile, device, decision, time.Since(started))
    return resp
}
```

## 10. Agent 启动序列（已下线自助注册）

```text
START
  → load configs/server.yaml
  → cfg.Validate()：缺 api_key / secret / node_id 任一项 → log.Fatalf 拒绝启动
  → 构造 agent.Credentials{NodeID, APIKey, Secret}（全部来自 yaml）
  → start DNS server with cached profiles (ProfileCache)
  → heartbeat loop（Bearer + HMAC）
  → config poll loop（Bearer + HMAC）
  → log flush loop（Bearer + HMAC）
```

**禁止**：

- ❌ 加载 `identity.json`（已删除）
- ❌ 任何"凭据缺失就调用 `register` 端点"的兜底
- ❌ 任何"先按空凭据启动、心跳失败再补救"的回退流程
- ❌ AES-GCM / SHA-256 wrapping 等加密壳（凭据以 0600 文件明文保存）

### 10.1 配置热加载

```text
GET config (global.json)
  → canonical JSON checksum verify
  → parse global config, extract profile list
  → for each profile in profile list:
       → if profile not in local cache (data/profiles/{prefix2}/{profileID}.json):
            → FetchProfile: first try local cache, then try portal-web, finally return error
       → parse and compile RuleEngine
  → if all profiles succeed:
       write data/profiles/{prefix2}/{profileID}.json for each new profile
       swap pointer atomically
       ACK applied
    else:
       keep current config
       ACK failed (with details on which profile failed)
```

### 10.2 本地 buffer

失败时将日志写 JSONL：

```json
{"batch_id":"batch_01H...","item":{}}
```

恢复后：

1. 按文件名时间顺序读取。
2. 按 batch 发送。
3. 成功后删除或标记 done。
4. 失败超过阈值移入 dead-letter 文件。

## 11. 指标

resolver 不再向 `portal-web` 上报运行指标。ops 监控只关心节点在线/离线（通过心跳超时判定），节点健康度、QPS、CPU、MEM、DISK 等指标不进入 Agent API。

本地 Prometheus 抓取（`127.0.0.1:9100/metrics`）仍可保留用于运维侧的本地抓取，但**不得**作为控制面健康判定依据。

## 12. 安全要求

- 不连接 MySQL。
- 不调用 `portal-web`。
- **node 凭据（api_key + secret）只用于 Agent API**；不允许任何对外控制命令。
- Agent API 必须使用 **Bearer + HMAC-SHA256 双因子鉴权**（`Authorization: Bearer <api_key>` + `X-Signature`）。
- DoH / DoT 证书私钥文件权限限制。
- 日志不保存 client IP 明文，除非用户和合规策略明确允许。
- DNSSEC / ECS / QNAME minimization 在 MVP 可关闭，但必须预留配置字段。



### 10.4 QueryLogItem 必填字段

resolver 上报日志时，每条查询必须包含稳定 `event_id`，用于降低重复写入影响，并帮助排查计费差异。

```text
event_id = stable_hash(node_id + batch_id + sequence + timestamp + profile_id + query_name + query_type)
```

必须包含：

```text
event_id
profile_id
user_id
query_name
query_type
action
latency_ms
node_id
profile_version
```

resolver 仍然以 `batch_id` 作为重放幂等主键；同一 batch 重放必须保证内容和顺序一致。


## 13. NextDNS Lite quota 执行规则

resolver 从配置包读取 quota：

```json
{
  "plan_code": "free",
  "monthly_query_limit": 300000,
  "used_query_count": 300000,
  "quota_status": "exceeded"
}
```

执行规则（严格匹配，无降级路径）：

| 条件 | 模式 | 行为 |
|---|---|---|
| `quota_status='normal'` | protected | 正常执行过滤、日志、统计 |
| `quota_status='unlimited'` | protected | 正常执行过滤、日志、统计（不受限额约束） |
| `quota_status='exceeded'` | rejected | DNS 协议层硬拒绝：返回 SERVFAIL/REFUSED，不执行过滤，不写详细日志，不向上游转发 |

resolver 不计算金额，不生成账单，不判断 Pro/Business 是否续费，只执行配置包中的 quota 状态。系统**不再保留**任何 classic_dns 降级中间态；Free 超额即硬拒绝，付费订阅正常服务。
