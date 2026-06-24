# 配置架构：Global Config + Lazy Profile

> 本文档描述 Resolver 配置拉取架构，取代旧的 Config Bundle 全量推送模式。

---

## 1. 架构总览

```
Portal (MySQL)
    │
    ├── GET  /config               → Global Config（全量同步，5分钟间隔）
    ├── GET  /profiles/{id}        → Profile Config（按需拉取，SingleFlight 防击穿）
    └── POST /profiles/check       → 版本检查（5分钟间隔，V2 升级为 Redis PubSub）
    │
Resolver
    ├── Memory Cache  (LRU, max 5000, TTL 30min)
    ├── Disk Cache    (LRU, max 20000, TTL 7天, CacheEnvelope)
    └── SingleFlight  (同 Profile 并发请求仅回源 1 次)
```

## 2. 核心原则

> **Global Config = Resolver 运行配置**（全量同步，不含任何用户数据）
> 
> **Profile Config = 用户配置**（按需懒加载，单条拉取）

## 3. API 定义

### 3.1 GET /config — 拉取 Global Config

**鉴权**：`node.api_key`

**频率**：启动时 + 每 5 分钟

**返回**：

```json
{
  "version": 42,
  "upstreams": [
    { "address": "1.1.1.1:53", "protocol": "udp", "timeout": "1500ms" }
  ],
  "plans": {
    "free":     { "monthly_query_limit": 300000, "profiles_limit": 3 },
    "pro":      { "monthly_query_limit": null,   "profiles_limit": 10 },
    "business": { "monthly_query_limit": null,   "profiles_limit": 50 }
  },
  "rulesets": {},
  "limits": { "max_qps": 1000, "rate_limit_rps": 100 }
}
```

**保存**：`data/global.json`

**绝对不包含**：任何 Profile / 用户规则 / 用户设备 / 用户设置

### 3.2 GET /profiles/{profile_id} — 拉取单个 Profile

**鉴权**：`node.api_key`

**触发**：Memory Cache MISS → Disk Cache MISS （SingleFlight 防击穿）

**返回**：单个 Profile 的完整配置（rules / devices / settings / quota）

**保存**：`data/profiles/{prefix2}/{profile_id}.json`（CacheEnvelope 包裹）

### 3.3 POST /profiles/check — 批量版本检查

**鉴权**：`node.api_key`

**频率**：每 5 分钟

**请求**：

```json
{ "profiles": { "b543d4": 3, "a1b2c3": 5 } }
```

**响应**：

```json
{ "data": { "updated": { "b543d4": 4 } } }
```

有更新的 Profile 自动触发 `FetchProfile` 重新拉取。

## 4. 数据流

### 4.1 Resolver 启动

```
StartConfigSync()
  ├── pullGlobalConfig()         → GET /config
  │                                → 写 data/global.json
  │                                → 加载到 GlobalEngine
  │
  ├── pCache.LoadFromDiskOnStartup()
  │                                → 扫描 data/profiles/*.json
  │                                → 加载到 Memory Cache
  │                                → 删除过期文件
  │
  ├── go evictLoop()              → 每 5 分钟清理孤儿文件
  │
  └── for {
        ticker.C (5min)            → pullGlobalConfig()
        checkTicker.C (5min)       → checkProfiles()
      }
```

### 4.2 用户首次 DNS 查询

```
DNS 请求到来（DoH/DoT/DoQ/UDP/TCP）
  → 识别 profile_id（SNI / X-Profile-UID / URL path）
  → server.profileLoader(profileID)          ← 三个协议 server 统一入口
      → agent.FetchProfile(profileID)
          → pCache.GetFromMemory(profileID)
              → MISS
          → pCache.GetFromDisk(profileID)
              → MISS
          → pCache.DoOnce(profileID, ...)     ← SingleFlight 防击穿
              → GET /profiles/{profileID}
              → pCache.SetToDisk(...)         ← 原子写盘
              → pCache.SetToMemory(...)       ← 加载到内存
              → engine.LoadProfileRules(...)  ← 注入引擎
  → engine.MatchWithProfile(profileID, domain)
  → 返回匹配结果
```

### 4.3 用户持续访问

```
DNS 请求 → 识别 profile_id
  → pCache.GetFromMemory(profileID)
      → HIT（更新 lastUsedAt）
  → engine.MatchWithProfile(profileID, domain)
  → 返回结果

零 Portal 调用，零磁盘 I/O。
```

### 4.4 用户停止访问（30 分钟无查询）

```
evictLoop (每5分钟):
  Memory Cache: lastUsedAt > 30min
    → engine.RemoveProfile(profileID)
    → delete(pCache.memory[profileID])
    → 磁盘文件 data/profiles/{id}.json 保留

Disk Cache: cachedAt > 7天
    → os.Remove(diskPath)
```

### 4.5 用户回来继续查询

```
DNS 请求 → 识别 profile_id
  → pCache.GetFromMemory → MISS（已淘汰）
  → pCache.GetFromDisk  → HIT
  → pCache.SetToMemory
  → engine.LoadProfileRules
  → MatchWithProfile

无需访问 Portal。
```

### 4.6 用户修改配置

```
用户在 Portal 修改设置
  → ProfileVersion++
  → ConfigVersion v+1

Resolver 5 分钟周期:
  POST /profiles/check { profiles: { "b543d4": 3 } }
  → 返回 { updated: { "b543d4": 4 } }
  → FetchProfile("b543d4")
  → GET /profiles/b543d4
  → 覆盖 data/profiles/{id}.json
  → 热更新 engine（无需重启）
```

## 5. 缓存配置

| 参数 | 默认值 | 说明 |
|------|--------|------|
| `profiles_cache_dir` | `./data/profiles` | 磁盘缓存根目录 |
| `profile_cache_memory` | 5000 | Memory Cache 上限 |
| `profile_cache_disk` | 20000 | Disk Cache 上限 |
| `profile_evict_ttl_min` | 30 | 内存淘汰 TTL（分钟） |
| `profile_disk_ttl_days` | 7 | 磁盘淘汰 TTL（天） |
| `version_check_minutes` | 5 | 版本检查间隔（分钟） |

## 6. 淘汰策略

| 层级 | 上限 | 算法 | 淘汰后 |
|:----:|:----:|:----:|:------:|
| Memory | 5000 | LRU（30min 无命中） | 保留磁盘文件 |
| Disk | 20000 | LRU（7天 无命中） | 删除文件 |

## 7. 安全与边界

| 场景 | 处理 |
|------|------|
| 同一 Profile 5000 并发请求 | SingleFlight -> 仅 1 次回源 |
| 磁盘写满 | `SetToDisk` 失败 log error，不影响引擎已有规则 |
| Profile 在 Portal 被删除 | `checkProfiles` 跳过已删除；`FetchProfile` 返回 404 |
| 网络中断时按需拉取 | `FetchProfile` 超时返回 error，log 后不影响其他 Profile |
| 启动时大量文件恢复 | 串行加载，每个文件约 1ms，5000 文件约 5s |
| Memory + Disk 同时 MISS | 回源 Portal（SingleFlight 保护），写入两级缓存 |

## 8. 与旧架构对比

| 维度 | 旧架构（Config Bundle） | 新架构（Global + Lazy Profile） |
|------|------------------------|----------------------------------|
| 全量同步内容 | 所有 Profile | 仅 Global Config |
| Profile 拉取 | 随全量同步顺带 | 按需单条拉取 |
| 版本检查 | 依赖心跳 `should_pull_config` | 独立 `profiles/check` 接口 |
| 并发防护 | 无 | SingleFlight |
| 磁盘缓存 | 无版本信息 | CacheEnvelope 含 version/cached_at |
| 淘汰上限 | 无限制 | Memory 5000 + Disk 20000 LRU |
| 用户规模 | 几十 | 十万+ |
