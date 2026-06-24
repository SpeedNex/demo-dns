# geodns 数据模型

> geodns 主要使用内存模型，数据来源是 `dns-console-web` 的健康视图。ops 监控只关心节点在线/离线，geodns 不再做"健康度降权"。

## 1. HealthView

```go
type HealthView struct {
    GeneratedAt time.Time    `json:"generated_at"`
    TTLSeconds  int          `json:"ttl_seconds"`
    Nodes       []NodeHealth `json:"nodes"`
}

type NodeHealth struct {
    NodeID             string   `json:"node_id"`
    Region             string   `json:"region"`
    Country            string   `json:"country"`
    City               string   `json:"city"`
    Status             string   `json:"status"`
    PublicIPv4         string   `json:"public_ipv4"`
    PublicIPv6         string   `json:"public_ipv6"`
    SupportedProtocols []string `json:"supported_protocols"`
    Weight             int      `json:"weight"`
    LastHeartbeatAt    string   `json:"last_heartbeat_at"`
}
```

## 2. RoutingPolicy

```go
type RoutingPolicy struct {
    DefaultTTLSeconds   int                 `json:"default_ttl_seconds"`
    MaxAnswers          int                 `json:"max_answers"`
    // OverloadThreshold 已弃用：ops 监控只关心 online/offline，不再基于 QPS/CPU/MEM 做降权
    OverloadThreshold   float64             `json:"overload_threshold,omitempty"`
    RegionFallbacks     map[string][]string `json:"region_fallbacks"`
    StaticFallbackIPv4  []string            `json:"static_fallback_ipv4"`
    StaticFallbackIPv6  []string            `json:"static_fallback_ipv6"`
}
```

## 3. 内存索引

```text
nodes_by_id[node_id]
nodes_by_region[region]
nodes_by_country[country]
nodes_by_protocol[protocol]
```

每次健康视图更新时构建新索引，然后原子替换。

## 4. 节点可用性判断

```text
status == online
AND public_ip exists for requested qtype
AND requested protocol in supported_protocols
AND now - last_heartbeat_at <= health_view_ttl
```

ops 监控只关心 online/offline，QPS/CPU/MEM/DISK 任何"健康"指标不再作为路由可用性 / 降权的判定依据。

## 5. 降权公式（已下线）

ops 监控下线后，geodns 不再对节点做"健康度降权"：`effective_weight = node.weight`，仅按 region 优先 + weight 选优。`qps_1m` / `capacity_qps` 字段仍可保留在健康视图中用于审计，但 geodns router 不再读取。

## 6. 数据保留

geodns 不做长期持久化。可将最近一次成功健康视图写入本地文件：

```text
/var/lib/geodns/last-health-view.json
```

用于进程重启后复用最近一次成功视图，期间不响应未知节点。

