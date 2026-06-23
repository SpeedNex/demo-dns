# 03-DATA-FLOW.md — 系统数据流与闭环架构

> 本文档描述 OcerDNS 各组件之间的通信链路、数据流向和闭环流程。

---

## 1. 系统组件概览

| 组件 | 技术栈 | 职责 | 端口 |
|------|--------|------|------|
| **portal-web** | Laravel | 控制面：节点管理、配置发布、日志存储、计费 | 8081 |
| **dns-resolver** | Go | 数据面：DNS 解析、规则匹配、日志采集 | 53/443/853 |
| **geodns** | Go | 入口调度：健康视图同步、就近路由 | 53(权威)/5354(API) |
| **clickhouse** | ClickHouse | 日志分析：查询日志存储与统计 | 8123/9000 |

### ⚠️ 强约束：GeoDNS = 调度解析器，不是节点

> **GeoDNS 是调度解析器（Scheduler / Resolver），不是 Node（节点）。**
> 该约束必须在所有数据库设计、代码实现和文档中严格遵守。

| 维度 | Resolver 节点 | GeoDNS 调度解析器 |
|------|--------------|-------------------|
| 数据库表 | `dns_resolver_nodes` | `dns_geodns` |
| 模型 | `App\Models\Node` | `App\Models\DnsGeodns` |
| 控制器前缀 | `Node/NodeRegisterController` | `Node/GeoDnsRegisterController` |
| 数据流向 | 接收用户 DNS 查询，执行规则匹配 | 接收用户 DNS 查询，就近路由到 Resolver |
| 注册流程 | bearer token 鉴权 → 更新安装状态 → 签发 api_key | bearer token 鉴权 → 更新安装状态 → 签发 api_key |
| 心跳 | 每 30s 上报，portal-web 健康检测 | 每 30s 上报，portal-web 健康视图聚合 |
| 关联方式 | 无 | 通过 `region` 字段精确匹配 `dns_resolver_nodes.region` |
| 表前缀 | `dns_resolver_nodes.*` | `dns_geodns.*` |

**代码实现约束：**
1. GeoDNS 必须引用 `DnsGeodns` 模型，`$table = 'geodns'`（实际表名 `dns_geodns`）
2. Resolver 节点必须引用 `Node` 模型，`$table = 'resolver_nodes'`（实际表名 `dns_resolver_nodes`）
3. 两个模型不得混用，不得通过 `node_id` 关联
4. 关联查询必须使用 `region` 精确匹配，禁止 `like` 模糊匹配
5. 新增 API 时必须明确区分操作对象是 Resolver 还是 GeoDNS

---

## 2. Resolver 端口定义

| 端口 | 协议 | 说明 |
|------|------|------|
| **UDP 53** | DNS | 传统 UDP DNS 查询 |
| **TCP 53** | DNS | 传统 TCP DNS 查询 |
| **443** | DoH (HTTP) | DNS over HTTPS（明文 HTTP，TLS 由上游 nginx 代理终止）|
| **8443** | DoH (HTTP) | 开发测试用，绕过 443 端口限制 |
| **853** | DoT (TLS) | DNS over TLS |
| **853/UDP** | DoQ (QUIC) | DNS over QUIC（UDP 传输）|

### 生产环境推荐配置

```yaml
listen:
    doh: 8443     # 内部 DoH 端口，Caddy 443 → dns-resolver 8443
    dot: 853
    doq: 853
    udp: 53
    tcp: 53
```

### 开发环境配置

```yaml
listen:
    doh: 8443     # 开发环境绕过 443 端口限制
    dot: 853
    doq: 853
    udp: 53
    tcp: 53
```

---

## 3. DNS 查询闭环流程

### 3.0 完整用户查询流程总览

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                              用户查询完整闭环                                    │
└─────────────────────────────────────────────────────────────────────────────────┘

  ┌──────────┐    ┌──────────┐    ┌──────────┐    ┌──────────┐    ┌──────────┐
  │  用户    │───▶│  GeoDNS  │───▶│Resolver  │───▶│Portal   │───▶│ClickHouse│
  │ (任意设备)│    │ (调度)   │    │ (解析)   │    │ (控制面) │    │  (存储)  │
  └──────────┘    └──────────┘    └──────────┘    └──────────┘    └──────────┘
       │              │              │              │              │
       │ ① DoT/DoQ    │ ② DNS       │ ③ 规则     │ ④ 批量     │ ⑤ 查询
       │   查询       │   解析       │   匹配      │   上报      │   统计
       │ SNI域名      │ 返回IP       │ 日志写入   │              │
       │              │              │              │              │
       │─────────────▶│              │              │              │
       │              │─────────────▶│              │              │
       │              │              │─────────────▶│              │
       │              │              │              │─────────────▶│
       │              │              │              │              │
       ▼              ▼              ▼              ▼              ▼
```

---

### 3.1 场景一：DoH 查询（最常用，直接访问）

**适用场景**：浏览器、Apps、移动设备

```
用户设备
     │
     │ https://dns.ocerlinkdata.com/b543d4/dns-query?dns=...
     ▼
┌────────────────────────────────────────────────────────────────────┐
│                         nginx / Caddy                              │
│                                                                    │
│  1. TLS 终止 (443 端口)                                          │
│  2. 路由: /b543d4/dns-query → upstream http://resolver:8443     │
└────────────────────────────────────────────────────────────────────┘
     │
     │ plain HTTP (已解密)
     ▼
┌────────────────────────────────────────────────────────────────────┐
│                     Resolver (DoH Server)                          │
│                        :8443 (开发) / :443 (生产)                   │
│                                                                    │
│  ┌──────────────────────────────────────────────────────────────┐ │
│  │  Step 1: 提取 Profile ID                                      │ │
│  │  从 URL path 提取: /b543d4 → profile_id = b543d4            │ │
│  └──────────────────────────────────────────────────────────────┘ │
│                              │                                      │
│  ┌──────────────────────────────────────────────────────────────┐ │
│  │  Step 2: 设备识别 (可选)                                      │ │
│  │  - X-Device-ID header                                        │ │
│  │  - X-Device-Type header                                       │ │
│  │  - 客户端 IP → 匹配 devices 列表                             │ │
│  └──────────────────────────────────────────────────────────────┘ │
│                              │                                      │
│  ┌──────────────────────────────────────────────────────────────┐ │
│  │  Step 3: 配额检查 (quota_status)                              │ │
│  │  active.json → profile.quota.quota_status                     │ │
│  │  exceeded → HTTP 403 "quota exceeded"                        │ │
│  └──────────────────────────────────────────────────────────────┘ │
│                              │                                      │
│  ┌──────────────────────────────────────────────────────────────┐ │
│  │  Step 4: 规则引擎匹配                                         │ │
│  │  domain: www.example.com                                      │ │
│  │  匹配规则优先级:                                              │ │
│  │  1. exact: example.com = 精确匹配                           │ │
│  │  2. wildcard: *.example.com                                  │ │
│  │  3. keyword: 关键词匹配                                      │ │
│  │  4. category: malware/phishing/ads                           │ │
│  │  5. default: default_action                                  │ │
│  │                                                               │ │
│  │  命中结果:                                                    │ │
│  │  - ALLOW: 放行，继续处理                                     │ │
│  │  - BLOCK: 返回 nxdomain / 0.0.0.0 / refused                  │ │
│  │  - REWRITE: 返回 CNAME 重写                                   │ │
│  └──────────────────────────────────────────────────────────────┘ │
│                              │                                      │
│  ┌──────────────────────────────────────────────────────────────┐ │
│  │  Step 5: DNS 缓存 (可选)                                      │ │
│  │  cache_key = domain + query_type + profile_id                │ │
│  │  命中 → 直接返回缓存                                          │ │
│  └──────────────────────────────────────────────────────────────┘ │
│                              │                                      │
│  ┌──────────────────────────────────────────────────────────────┐ │
│  │  Step 6: 上游转发 (ALLOW 路径)                                │ │
│  │  upstream: 1.1.1.1:53 / 8.8.8.8:53                         │ │
│  │  双上游容错: 第一个失败自动切换第二个                          │ │
│  └──────────────────────────────────────────────────────────────┘ │
│                              │                                      │
│  ┌──────────────────────────────────────────────────────────────┐ │
│  │  Step 7: 写日志 (first-seen 去重)                            │ │
│  │  dedup_key = client_ip + domain + query_type                 │ │
│  │  5秒内重复查询只记一次                                        │ │
│  │  写入本地 buffer: /var/lib/ocer-dns/log-buffer               │ │
│  └──────────────────────────────────────────────────────────────┘ │
│                              │                                      │
│  ┌──────────────────────────────────────────────────────────────┐ │
│  │  Step 8: 返回响应                                             │ │
│  │  HTTP 200 + Content-Type: application/dns-message            │ │
│  │  响应体: DNS wire format                                    │ │
│  └──────────────────────────────────────────────────────────────┘ │
└────────────────────────────────────────────────────────────────────┘
     │
     │ 批量上报 (每 10s 或 100 条)
     ▼
┌────────────────────────────────────────────────────────────────────┐
│                         portal-web                                   │
│                   POST /api/v1/node/query-logs/batch                 │
│                                                                    │
│  {                                                              │
│    "node_id": "16",                                              │
│    "logs": [                                                    │
│      {                                                          │
│        "profile_uid": "b543d4",                                  │
│        "device_uid": "dev_001",                                   │
│        "domain": "www.example.com",                              │
│        "action": "ALLOW",                                        │
│        "reason": "default",                                       │
│        "category": "",                                           │
│        "client_ip": "203.0.113.50",                              │
│        "query_type": "A",                                        │
│        "response_code": 0,                                        │
│        "response_time_ms": 12,                                   │
│        "protocol": "doh",                                        │
│        "queried_at": 1750644000                                  │
│      }                                                          │
│    ]                                                            │
│  }                                                              │
└────────────────────────────────────────────────────────────────────┘
     │
     ▼
┌────────────────────────────────────────────────────────────────────┐
│                        ClickHouse                                   │
│                                                                    │
│  INSERT INTO dns_query_logs (...)                                 │
│                                                                    │
│  用户可在 portal-web 查看:                                        │
│  - 查询日志列表                                                   │
│  - 域名分类统计                                                   │
│  - 用量趋势图                                                     │
│  - 拦截报告                                                       │
└────────────────────────────────────────────────────────────────────┘
```

---

### 3.2 场景二：DoT/DoQ 查询（通过 GeoDNS 调度）

**适用场景**：系统级 DNS 配置、设备直接支持 TLS/QUIC

```
客户端设备
     │
     │ 1. 解析 SNI 域名
     │    bcfe3a.dns.ocerlinkdata.com
     ▼
┌────────────────────────────────────────────────────────────────────┐
│                    系统 DNS 递归解析                                │
│                                                                    │
│  → GeoDNS Authoritative DNS (:53)                                 │
└────────────────────────────────────────────────────────────────────┘
     │
     │ 2. DNS 查询
     │    bcfe3a.dns.ocerlinkdata.com A
     ▼
┌────────────────────────────────────────────────────────────────────┐
│                 GeoDNS (Authoritative DNS)                         │
│                        UDP/TCP :53                                 │
│                                                                    │
│  1. 收到查询: bcfe3a.dns.ocerlinkdata.com                        │
│  2. 提取 profile_id: bcfe3a (从子域名)                            │
│  3. 从 HealthView 本地缓存选择最优 resolver:                       │
│     - 按 region/country 选择最近节点                               │
│     - 按 weight 加权负载                                           │
│     - 过滤 offline 节点                                           │
│  4. 返回 resolver IP: 203.0.113.10                               │
└────────────────────────────────────────────────────────────────────┘
     │
     │ 3. DNS 响应: 203.0.113.10
     ▼
┌────────────────────────────────────────────────────────────────────┐
│                     客户端 (TLS Handshake)                          │
│                                                                    │
│  SNI: bcfe3a.dns.ocerlinkdata.com                                │
│  → 连接 203.0.113.10:853 (DoT) 或 203.0.113.10:853 (DoQ)        │
└────────────────────────────────────────────────────────────────────┘
     │
     │ 4. DoT/DoQ DNS 查询
     ▼
┌────────────────────────────────────────────────────────────────────┐
│                  Resolver (DoT/DoQ Server)                        │
│                         :853                                       │
│                                                                    │
│  ┌──────────────────────────────────────────────────────────────┐ │
│  │  Step 1: 提取 Profile ID                                      │ │
│  │  DoT: 从 TLS SNI 提取: bcfe3a → profile_id = bcfe3a          │ │
│  │  DoQ: 从 QUIC 连接 SNI 提取                                  │ │
│  └──────────────────────────────────────────────────────────────┘ │
│                              │                                      │
│  ┌──────────────────────────────────────────────────────────────┐ │
│  │  Step 2-8: 同 DoH 流程                                        │ │
│  │  设备识别 → 配额检查 → 规则匹配 → 缓存 → 上游 → 日志          │ │
│  │  唯一区别: protocol = "dot" 或 "doq"                        │ │
│  └──────────────────────────────────────────────────────────────┘ │
└────────────────────────────────────────────────────────────────────┘
     │
     │ 批量上报
     ▼
┌────────────────────────────────────────────────────────────────────┐
│                    → portal-web → ClickHouse                        │
└────────────────────────────────────────────────────────────────────┘
```

---

### 3.3 场景三：传统 UDP/TCP DNS（不推荐，仅兼容）

**适用场景**：旧设备、网络限制环境

```
客户端设备
     │
     │ DNS 查询 (UDP/TCP :53)
     │ www.example.com A
     ▼
┌────────────────────────────────────────────────────────────────────┐
│                  Resolver (UDP/TCP DNS Server)                      │
│                          :53                                       │
│                                                                    │
│  1. 客户端 IP → 匹配 active.json 中的 devices                     │
│  2. 获取对应 profile_id                                           │
│  3. 其余流程同 DoH (规则匹配 → 缓存 → 上游 → 日志)               │
│                                                                    │
│  注意: 无 SNI/URL 路径，需靠 IP 识别 profile                      │
└────────────────────────────────────────────────────────────────────┘
```

---

### 3.4 完整查询日志字段说明

| 字段 | 类型 | 说明 | 示例 |
|------|------|------|------|
| profile_uid | string | Profile 唯一标识 | b543d4 |
| device_uid | string | 设备唯一标识 | dev_001 |
| domain | string | 查询域名 | www.example.com |
| action | enum | 处理动作 | ALLOW / BLOCK / REWRITE |
| reason | string | 命中原因 | default / allowlist / blocklist / safesearch |
| category | string | 域名分类 | malware / phishing / ads |
| client_ip | string | 客户端 IP（去端口化）| 203.0.113.50 |
| query_type | string | DNS 查询类型 | A / AAAA / CNAME / TXT |
| response_code | int | DNS 响应码 | 0 (NOERROR) / 3 (NXDOMAIN) |
| response_time_ms | int | 响应耗时（毫秒）| 12 |
| protocol | string | 使用协议 | doh / dot / doq / udp / tcp |
| queried_at | int | 查询时间戳 | 1750644000 |

---

### 3.5 拦截响应类型 (block_response)

| 类型 | DNS 响应 | HTTP 响应 | 适用场景 |
|------|---------|----------|---------|
| nxdomain | RCODE=3 NXDOMAIN | 404 | 完全隐藏 |
| refused | RCODE=5 REFUSED | 403 | 标准拒绝 |
| zeroip | A=0.0.0.0 | 200 + 0.0.0.0 | 静默拦截 |

```
浏览器/客户端
     │
     │ https://dns.ocerlinkdata.com/b543d4
     ▼
┌────────────────────────────────────────────┐
│              nginx / Caddy                  │
│         (TLS termination + 路由)            │
│     /b543d4 → Resolver :443 (HTTP)        │
└────────────────────────────────────────────┘
     │
     │ plain HTTP (已解密)
     ▼
┌────────────────────────────────────────────┐
│           Resolver (DoH Server)             │
│                                            │
│  1. 提取 profile_id: b543d4                │
│  2. 从 active.json 匹配设备 IP → Profile   │
│  3. 规则引擎判定 (allow/block/rewrite)     │
│  4. DNS 缓存查询                           │
│  5. 上游转发 (1.1.1.1 / 8.8.8.8)          │
│  6. 写日志到本地 buffer                    │
└────────────────────────────────────────────┘
     │
     │ 批量上报 (每 10s)
     ▼
┌────────────────────────────────────────────┐
│              portal-web                     │
│           POST /api/v1/node/query-logs/batch│
└────────────────────────────────────────────┘
     │
     ▼
┌────────────────────────────────────────────┐
│             ClickHouse                      │
│           INSERT dns_query_logs             │
└────────────────────────────────────────────┘
```

### 3.2 DoT/DoQ 查询流程（需要 GeoDNS 解析 SNI 域名）

```
客户端 (DNS over TLS/QUIC)
     │
     │ 解析: bcfe3a.dns.ocerlinkdata.com
     ▼
┌────────────────────────────────────────────┐
│          系统 DNS 递归解析                   │
│     → GeoDNS Authoritative (UDP 53)         │
└────────────────────────────────────────────┘
     │
     │ 返回 Resolver IP
     ▼
┌────────────────────────────────────────────┐
│           Resolver (DoT/DoQ)               │
│             连接到 :853                      │
│                                            │
│  1. 从 SNI 提取 profile_id: bcfe3a         │
│  2. 从 active.json 匹配设备 IP → Profile   │
│  3. 规则引擎判定                            │
│  4. DNS 缓存 / 上游转发                    │
│  5. 写日志到本地 buffer                    │
└────────────────────────────────────────────┘
     │
     │ 批量上报
     ▼
┌────────────────────────────────────────────┐
│              portal-web                     │
│           POST /api/v1/node/query-logs/batch│
└────────────────────────────────────────────┘
     │
     ▼
┌────────────────────────────────────────────┐
│             ClickHouse                      │
└────────────────────────────────────────────┘
```

---

## 4. GeoDNS 架构（独立调度服务）

### 4.1 组件职责

- **Authoritative DNS (UDP/TCP 53)**：接收 DNS 查询，返回最优 resolver IP
- **HealthView Client**：定时从 portal-web 拉取 resolver 节点健康状态
- **Local Cache**：缓存节点列表，本地选择最优 resolver
- **Selector**：根据客户端 IP 地理位置选择最近/最优节点

### 4.2 数据流

```
┌─────────────────────────────────────────────────────────────┐
│                     GeoDNS Server                           │
│                                                             │
│  ┌─────────────────────────────────────────────────────┐   │
│  │  定时任务 (每 30s)                                    │   │
│  │  GET http://portal-web:8081/internal/health-view      │   │
│  │  → 本地缓存节点列表                                   │   │
│  └─────────────────────────────────────────────────────┘   │
│                          │                                  │
│  ┌─────────────────────────────────────────────────────┐   │
│  │  Authoritative DNS Server                            │   │
│  │  UDP/TCP :53                                        │   │
│  │                                                     │   │
│  │  收到查询: ns1.ocerlinkdata.com                     │   │
│  │  → 从本地缓存的 HealthView 选择最优 resolver         │   │
│  │  → 返回 resolver IP                                 │   │
│  └─────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────┘
```

---

## 5. Resolver ↔ portal-web 通信

### 5.1 Resolver → portal-web API

| 方向 | API | 频率 | 说明 |
|------|-----|------|------|
| **心跳** | `POST /api/v1/node/heartbeat` | 30s | 节点状态、配置版本、在线时长 |
| **拉取配置** | `GET /api/v1/node/dns-resolver/config` | 版本比较 | checksum 比对，有变化则下载 |
| **ACK 配置** | `POST /api/v1/node/config-ack` | 拉取后 | 确认配置已应用 |
| **上报日志** | `POST /api/v1/node/query-logs/batch` | 批量 | 查询日志写入 ClickHouse |

### 5.2 心跳响应（HeartbeatService）

```json
{
  "data": {
    "ok": true,
    "server_time": "2026-06-23T10:00:00Z",
    "node_status": "online",
    "latest_config_version": 100,
    "should_pull_config": true,
    "config_endpoint": "/api/v1/node/resolver/config",
    "next_heartbeat_after_seconds": 30
  }
}
```

**设计说明**：
- 返回 `latest_config_version` 数字，resolver 自己比较版本决定是否拉取
- `should_pull_config` 布尔值作为便捷字段，但核心逻辑基于版本号比较

### 5.3 配置热加载流程

```
1. 管理员在 portal-web 发布新配置 (version: 101)
2. Resolver 发送心跳携带 current_config_version: 100
3. portal-web 返回 latest_config_version: 101
4. Resolver 比较：101 > 100，主动拉取新配置
5. Resolver 拉取 config bundle 并校验 checksum
6. checksum 通过后，原子写入 active.json
7. Resolver 热加载新配置
8. Resolver 发送 ACK 确认
```

---

## 6. 查询日志闭环

### 6.1 日志采集流程

```
DNS 查询处理
     │
     │ 规则匹配完成
     ▼
日志写入 Resolver 本地 buffer
(/var/lib/ocer-dns/log-buffer)
     │
     │ 批量上报 (每 10s 或 100 条)
     ▼
portal-web Node API
POST /api/v1/node/query-logs/batch
     │
     ▼
ClickHouse INSERT
dns_query_logs 表
     │
     ▼
用户可在 portal-web 查看统计和日志
```

### 6.2 日志内容结构

```json
{
  "profile_uid": "b2d137",
  "device_uid": "dev_001",
  "domain": "www.example.com",
  "action": "ALLOW",
  "reason": "default",
  "category": "",
  "client_ip": "192.168.1.100",
  "query_type": "A",
  "response_code": 0,
  "response_time_ms": 12,
  "protocol": "doh",
  "queried_at": 1750644000
}
```

---

## 7. 整体架构图

```
┌─────────────────────────────────────────────────────────────────────────┐
│                           portal-web (Laravel)                          │
│                         http://localhost:8081                           │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐   │
│  │  Node 管理   │  │  配置发布   │  │  日志存储   │  │   计费      │   │
│  └─────────────┘  └─────────────┘  └─────────────┘  └─────────────┘   │
└──────────────┬──────────────────────────────────────────┬──────────────┘
               │                                          │
               │ ① Heartbeat (30s)                       │ ④ Health View
               │ POST /api/v1/node/heartbeat              │ GET /internal/health-view
               │ ←────────────                            │ (定时拉取并缓存)
               │                                          │
┌──────────────▼──────────────────────────────────────────┐              │
│                     dns-resolver                         │              │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌────────┐ │              │
│  │ DNS/53   │  │ DoH/443  │  │ DoT/853  │  │ DoQ/853│ │              │
│  └────┬─────┘  └────┬─────┘  └────┬─────┘  └───┬────┘ │              │
│       │             │             │             │      │              │
│       └─────────────┴─────────────┴─────────────┘      │              │
│                        │                               │              │
│  ┌─────────────────────▼──────────────────────────────┐ │              │
│  │  Agent: 心跳 / 配置同步 / 日志上报                   │ │              │
│  └─────────────────────┬──────────────────────────────┘ │              │
│                        │                               │              │
│  ② Config Pull         │ ③ Query Logs                  │              │
│  GET /api/v1/node/.../config │ POST /api/v1/node/query-logs/batch    │
└────────────────────────┬───────────────────────────────┘              │
                         │                                             │
┌────────────────────────┴────────────────────────────────────────────┐│
│          geodns (Authoritative DNS + Selector)                       ││
│                                                                          ││
│  ┌────────────────────────────────────────────────────────────────────┐ ││
│  │  Authoritative DNS (UDP/TCP :53)                                   │ ││
│  │  → 本地缓存的 HealthView → 返回最优 resolver IP                    │ ││
│  └────────────────────────────────────────────────────────────────────┘ ││
│                          ▲                                             ││
│                          │ (定时拉取)                                   ││
└──────────────────────────┼──────────────────────────────────────────────┘
                           │
                           ▼
                   ┌───────────────┐
                   │  ClickHouse   │
                   │  dns_query_logs│
                   └───────────────┘
```

---

## 8. 关键设计原则

| 原则 | 说明 |
|------|------|
| **GeoDNS 定时拉取** | HealthView 每 30s 拉取一次，本地缓存，避免每次查询都打 portal-web |
| **Resolver 版本比较** | 心跳返回 `latest_config_version` 数字，resolver 自己计算是否需要拉取 |
| **日志批量上报** | Resolver 本地 buffer 持久化，批量上报到 portal-web，再写入 ClickHouse |
| **配置热加载** | checksum 校验 + 原子写盘 + 热加载 + ACK 确认 |
| **DoH 直连** | DoH 直接访问 Resolver，不经过 GeoDNS |

---

## 9. 版本历史

| 日期 | 描述 |
|------|------|
| 2026-06-23 | 修正 GeoDNS 端口定义，区分 Authoritative DNS (53) 和 Internal API (5354) |
| 2026-06-23 | 修正 DoH 流程说明，明确不经过 GeoDNS |
| 2026-06-23 | 修正 GeoDNS HealthView 拉取方式，定时同步而非每次查询拉取 |
