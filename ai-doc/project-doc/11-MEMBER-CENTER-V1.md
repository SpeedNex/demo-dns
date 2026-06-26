# 11 - 会员中心 V1 功能规格

> 本文件锁定第一版本 `portal-web` 会员中心必须具备的功能入口。V1 参考 NextDNS 的低版本体验：功能结构清晰、计费简单、DNS 查询不按量收费；Free 受 300,000 queries/month 限制，Pro/Business/Education/Enterprise 为 unlimited queries。

## 1. V1 会员中心一级导航

`portal-web` 的用户登录后控制台必须至少包含以下一级入口，不得把这些能力放到后续版本再实现：

| 导航 | 英文建议 | V1 目标 |
|---|---|---|
| 安全 | Security | 阻断恶意、钓鱼、恶意软件、C2、Cryptojacking 等基础安全域名 |
| 隐私 | Privacy | 阻断跟踪器、遥测、分析域名；控制日志模式和 IP 匿名化 |
| 家长监护 | Parental Control | 儿童 Profile、成人内容拦截、安全搜索、YouTube 受限模式 |
| 黑名单 | blocklist | 用户自定义阻断域名，支持 exact / suffix / wildcard |
| 白名单 | Allowlist | 用户自定义放行域名，优先级高于安全/隐私/家长/黑名单阻断 |
| 统计 | Analytics | 查询量、拦截量、Top 域名、Top 阻断、基础趋势 |
| 日志 | Logs | DNS 查询日志、拦截日志、筛选、分页、按 Profile/Device 查看 |
| 设置 | Settings | Profile 设置、设备接入、DNS 端点、语言、时区、日志保留偏好 |
| 会员中心 | Membership | 当前套餐、Free 额度、Pro 升级、订单、发票、支付、退款入口 |

说明：这里的"会员中心"不是单独的新服务，属于 `portal-web` 的用户控制台；`portal-web(原 console 域)` 负责 resolver 控制面。

## 2. 各功能 V1 范围

### 2.1 安全 Security

V1 必须支持一个总开关和基础安全子开关：

```text
security.enabled
security.block_malware
security.block_phishing
security.block_command_and_control
security.block_cryptojacking
```

实现方式：

```text
portal-web 保存 Profile 安全设置
  -> 发布配置到 portal-web(原 console 域)
  -> portal-web(原 console 域) 生成 resolver config
  -> dns-resolver 将安全规则作为 block 规则执行
```

V1 不要求接入商业威胁情报市场；可以先使用内置基础规则集、手工导入规则集或离线规则文件。

### 2.2 隐私 Privacy

V1 必须支持：

```text
privacy.enabled
privacy.block_trackers
privacy.block_analytics
privacy.block_telemetry
privacy.anonymize_client_ip
privacy.log_mode = full / blocked_only / disabled
```

语义：

```text
full         记录完整查询日志，但 client_ip 只能 hash，不存原始 IP。
blocked_only 只展示被阻断日志；仍可聚合 query_count 用于 Free 额度。
disabled     不展示明细日志；仍允许最小化聚合 query_count 用于 Free 额度和风控。
```

禁止：

```text
ClickHouse 保存原始客户端 IP 作为长期事实字段
Redis 保存隐私或日志事实
privacy.log_mode=disabled 后仍展示详细域名日志
```

### 2.3 家长监护 Parental Control Lite

V1 是基础家长监护，不是完整儿童管控平台。必须支持：

```text
parental.enabled
parental.block_adult_content
parental.safe_search
parental.youtube_restricted_mode
parental.block_gambling_basic
```

V1 不做：

```text
按时间段上网控制
App 使用时长
地理位置
孩子端 App
家长审批流程
复杂行为报告
```

resolver 只执行 DNS 层规则：阻断、放行、重写安全搜索域名。

### 2.4 黑名单 blocklist

黑名单是用户自定义阻断规则，保存到 `profile_rules`：

```text
list_type = block
action = block
match_type = exact / suffix / wildcard
```

示例：

```text
exact: ads.example.com
suffix: .tracker.example
wildcard: *.bad.example
```

### 2.5 白名单 Allowlist

白名单是用户自定义放行规则，保存到 `profile_rules`：

```text
list_type = allow
action = allow
match_type = exact / suffix / wildcard
```

优先级：

```text
白名单 allowlist > 黑名单 blocklist > 安全 security > 隐私 privacy > 家长 parental > 默认动作 default_action
```

因此，用户加入白名单的域名即使命中安全/隐私/家长分类，也必须优先放行。唯一例外是系统级保留域或 abuse 风控规则，这类规则必须单独标记为 `system_enforced=true`，并在 UI 中解释。

### 2.6 统计 Analytics

V1 必须展示：

```text
今日查询数
今日阻断数
当前周期查询数
Free 额度剩余 / 已用百分比
Top domains
Top blocked domains
按小时/天基础趋势
```

数据来源：

```text
dns-resolver -> portal-web(原 console 域) -> ClickHouse -> portal-web 查询展示
```

财务注意：统计查询量不能直接生成 DNS 按量账单，只用于 Free quota、展示、风控和容量规划。

### 2.7 日志 Logs

V1 必须支持：

```text
按 Profile 查看日志
按 Device 查看日志
按域名搜索
按 action=allowed/blocked/rewritten/error 筛选
按时间范围筛选
分页
```

数据来源是 ClickHouse。日志写入链路必须是：

```text
dns-resolver -> portal-web(原 console 域) Agent API -> portal-web log worker -> ClickHouse
```

`dns-resolver` 不得直接写 ClickHouse。

### 2.8 设置 Settings

V1 必须支持：

```text
账户语言 locale
账户时区 timezone
Profile 名称和默认动作
Block response：nxdomain / zero_ip / refused
日志模式 log_mode
设备接入说明
DoH URL / UDP endpoint 展示
```

设置变化只修改草案，必须通过“发布配置”进入 resolver。

### 2.9 会员中心 Membership

V1 必须支持：

```text
当前套餐展示
Free 300,000 queries/month 额度进度
Pro 月付/年付升级入口
Business 按 50 employees block 购买入口
订单列表
发票列表
支付记录
退款记录
```

V1 禁止：

```text
DNS 查询按量计费
自动超额扣费
usage_overage invoice line
query_usage_charge invoice line
```

## 3. 会员中心到系统闭环

```text
用户在会员中心修改安全/隐私/家长/黑白名单/设置
  -> portal-web 保存草案
  -> 用户点击发布
  -> portal-web 生成 profile_versions
  -> portal-web 调用 portal-web(原 console 域) Internal API
  -> portal-web(原 console 域) 生成全局 config version
  -> dns-resolver 拉取配置并热加载
  -> 用户 DNS 查询命中配置
  -> dns-resolver 上报 query logs / metrics / heartbeat
  -> portal-web(原 console 域) 写 ClickHouse / Redis / MySQL
  -> portal-web 读取日志和统计
  -> portal-web 更新 Free quota / membership 状态
  -> portal-web(原 console 域) 分发 quota snapshot
  -> dns-resolver 根据 quota_status 执行 protected（normal/unlimited）或 rejected（exceeded）模式
```

## 4. resolver 配置映射

每个 Profile 下发给 resolver 时，至少包含：

```json
{
  "profile_id": "prf_01H...",
  "security": {
    "enabled": true,
    "block_malware": true,
    "block_phishing": true,
    "block_command_and_control": true,
    "block_cryptojacking": true
  },
  "privacy": {
    "enabled": true,
    "block_trackers": true,
    "block_analytics": true,
    "block_telemetry": true,
    "anonymize_client_ip": true,
    "log_mode": "full"
  },
  "parental": {
    "enabled": false,
    "block_adult_content": false,
    "safe_search": false,
    "youtube_restricted_mode": false,
    "block_gambling_basic": false
  },
  "rules": [],
  "quota": {
    "plan_code": "free",
    "monthly_query_limit": 300000,
    "quota_status": "normal"
  }
}
```

## 5. 权限和数据边界

```text
portal-web：保存用户设置、规则、会员和财务数据。
portal-web(原 console 域)：接收发布配置和 resolver 上报，管理节点与配置版本。
dns-resolver：执行配置，不直接连接数据库。
ClickHouse：只保存 DNS 日志和统计分析。
Redis：只保存短期状态、调度视图、锁和缓存。
MySQL：保存业务、配置、财务和控制面事实数据。
```

## 6. V1 验收标准

| 编号 | 验收项 | 必须结果 |
|---|---|---|
| MC-01 | 登录后会员中心导航 | 显示安全、隐私、家长监护、黑名单、白名单、统计、日志、设置、会员中心 |
| MC-02 | 白名单与黑名单冲突 | 白名单优先生效 |
| MC-03 | 安全开关开启 | resolver config 中 `security.enabled=true` |
| MC-04 | 隐私日志关闭 | 不展示详细域名日志，但仍聚合 query_count |
| MC-05 | 家长监护 SafeSearch | resolver config 中 `parental.safe_search=true` |
| MC-06 | 查询日志 | 数据来自 ClickHouse，不来自 MySQL 高频表 |
| MC-07 | Free 超额 | resolver 硬拒绝返回 SERVFAIL，不自动扣费；无任何降级中间态 |
| MC-07a | Free 用量达 80% | 会员中心首页与 Membership 页面顶部出现黄色提示条「本月查询已使用 80%，请注意用量」；同一周期内只提示一次，不重复刷屏 |
| MC-07b | Free 用量达 95% | 提示条升级为橙色 + 顶部 banner，提示「本月查询已使用 95%，超额后 DNS 解析将直接返回 SERVFAIL，请升级或等待下月」；同一周期内只提示一次 |
| MC-07c | Free 用量达 100% | 提示条升级为红色 + 顶部 banner，明确写「Free 月度查询额度已用完，DNS 解析将硬拒绝返回 SERVFAIL，不会自动扣费」；提供 Pro 升级 CTA |
| MC-07d | 邮件提醒 | Free 用户在 80%、95%、100% 三个阈值各收到一封邮件提醒（同一周期同一阈值仅发一封）；发件邮箱、模板、退订链接必须可配置；邮件发送失败不得影响 DNS 查询 |
| MC-07e | 严格匹配 | 100% 后 resolver 行为只有 SERVFAIL 一档，禁止出现 `quota_exceeded` 阻断页 / `classic_dns` 降级 / 半解析中间态；任何引入"超额后降级"的实现必须被拒绝 |
| MC-08 | Pro active | `monthly_query_limit=null, quota_status=unlimited`，无 Free 限额约束 |
| MC-09 | 设置修改 | 仅保存草案，发布后 resolver 执行 |
| MC-10 | resolver 上报 | 只通过 portal-web Agent API |
