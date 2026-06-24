# REQUIREMENTS.md — 原始需求草案与当前有效规格说明

> 本文件保留原始需求草案内容，用于追溯产品意图。当前代码生成应优先使用 `README.md`、`START.md`、`project-doc/`、`specs/`、`contracts/`、`migrations/`。
> 原始草案中的默认账号、旧命名、旧状态和历史技术选择不得直接作为生产实现依据。

## 0.1 财务准确性补充要求

财务数据以 `specs/portal-web/billing-finance.md` 为准。V1 参考 NextDNS-like 低版本模型：Free 300,000 queries/month；Pro unlimited queries；Business 按 50 employees block；Education 按 250 students block。订单、发票、支付、退款和账务流水必须使用整数最小货币单位 `amount_minor`，不得使用 float/double。查询用量只从 `portal-web` 接收的 query log batch 派生，用于 Free quota、统计和风控；V1 不做 DNS 查询按量收费，不能从 heartbeat 或 metrics 扣费。


---

# REQUIREMENTS.md

# Ocer DNS 产品需求说明书

Version: 1.0

Status: Draft

---

# 1. 项目概述

## 1.1 产品名称

Ocer DNS 

---

## 1.2 产品定位

Ocer DNS 是一个云端 DNS 安全管理平台。 实现一个真正优秀、可长期维护、可上线、可交接的专业项目

面向：

* 个人用户
* 家庭用户
* 开发者
* 企业用户

提供：

* DNS 解析服务
* 广告拦截
* 安全防护
* 家长控制
* DNS 日志
* DNS 数据分析
* 多设备管理
* 多 Profile 管理

产品目标对标：

* NextDNS
* AdGuard DNS
* CleanBrowsing

---

## 1.3 产品目标

帮助用户：

* 管理 DNS 配置
* 管理多个设备
* 管理多个网络环境
* 查看 DNS 活动
* 拦截广告
* 拦截恶意网站
* 实现家长控制

---

## 1.4 系统组成

平台由四个独立系统组成：

### User Portal（用户网站+后台）

面向最终用户。

包含：

* 官网
* 用户认证
* 会员中心

---

### DNS Control Console（DNS 控制台）

面向 DNS 运维管理员。

负责：

* DNS 节点管理
* GeoDNS 管理
* Config 配置发布
* 节点心跳、日志接收

---

### GeoDNS

负责节点调度。

根据用户来源 IP 返回最佳 DNS 节点。

---

### DNS Resolver

部署在每台 DNS 服务器。

负责 DNS 查询处理。

---

# 2. User Portal（用户网站）

## 2.1 系统职责

面向最终用户，提供官网、会员控制台和管理后台。

---

## 2.2 会员端功能

| 模块 | 功能 |
|---|---|
| 官网 | 首页、产品介绍、功能介绍、套餐介绍、节点介绍、帮助中心、联系我们 |
| Auth | 注册、登录、退出、邮箱验证、找回密码、重置密码 |
| Dashboard | 今日查询量、拦截量、活跃设备数、Profile 数、查询/拦截/设备趋势图表 |
| Profile 管理 | CRUD、复制、版本管理、发布/回滚、安全/隐私/家长/黑白名单/广告规则配置 |
| 设备管理 | 添加/编辑/删除设备、更换 Profile、查看详情、来源 IP 绑定 |
| DNS 设置 | DoH/DoT 地址、IPv4/IPv6、各平台配置教程（Windows/macOS/Linux/Android/iPhone/Router/OpenWrt） |
| DNS 日志 | 时间/域名/设备/动作/规则来源/节点、筛选（时间/设备/域名/动作/Profile）、搜索、导出 CSV |
| DNS 统计 | 查询/拦截/设备/Profile 总量、小时/日/月统计、Top 域名/阻断/设备排行 |
| 套餐管理 | 查看套餐、升级/续费/取消套餐、查看订单、查看发票 |
| 账户设置 | 修改密码、邮箱、语言、时区、查看登录记录 |

---

## 2.5 Profile 管理

| 视图 | 功能 |
|---|---|
| Profile 列表 | 显示 Profile 名称、设备数量、查询数量、当前版本、创建时间 |
| 操作 | 创建、编辑、删除、复制、发布、查看详情 |
| 版本管理 | 查看版本、发布版本、回滚版本 |

### Profile 安全/规则配置

| 分类 | 功能 |
|---|---|
| 安全防护 | Malware / Phishing / Botnet / Ransomware / Cryptojacking 开关 |
| 广告拦截 | OISD / AdGuard / EasyList 规则源开关 |
| 家长控制 | 成人内容、赌博、游戏、社交媒体、视频网站、下载网站 |
| 白名单 | 新增/编辑/删除，支持 `example.com` / `*.example.com` |
| 黑名单 | 新增/编辑/删除，支持 `example.com` / `*.example.com` |
| 自定义规则 | Allow / Deny / Rewrite |

---

## 2.6 Device 管理

| 维度 | 功能 |
|---|---|
| 显示 | 设备名称、所属 Profile、设备 IP、最后在线时间、状态 |
| 操作 | 添加、编辑、删除、更换 Profile、查看详情 |

---

## 2.7 DNS 设置

| 类型 | 内容 |
|---|---|
| 地址 | DoH 地址、DoT 地址、IPv4 地址、IPv6 地址 |
| 配置教程 | Windows / macOS / Linux / Android / iPhone / Router / OpenWrt |

---

## 2.8 DNS 日志

| 维度 | 功能 |
|---|---|
| 显示字段 | 时间、域名、设备、动作（ALLOW / BLOCK / REWRITE）、规则来源、节点 |
| 筛选 | 按时间、设备、域名、动作、Profile |
| 操作 | 查看日志、搜索日志、导出 CSV |

---

## 2.9 DNS 统计

| 维度 | 功能 |
|---|---|
| 总览 | 查询总量、拦截总量、设备总量、Profile 总量 |
| 图表 | 小时统计、日统计、月统计 |
| 排行榜 | Top Domains、Top Blocked Domains、Top Devices |

---

## 2.10 套餐管理

| 功能 |
|---|
| 查看套餐 |
| 升级套餐 |
| 续费套餐 |
| 取消套餐 |
| 查看订单 |
| 查看发票 |

---

## 2.11 账户设置

| 功能 |
|---|
| 修改密码 |
| 修改邮箱 |
| 修改语言 |
| 修改时区 |
| 查看登录记录 |

## 2.12 后台管理（portal-web 管理后台）

| 模块 | 功能 |
|---|---|
| 用户管理 | 查看用户/详情、禁用/解禁、重置密码、查看用户 Profile/设备 |
| 套餐管理 | 创建/编辑/删除套餐、上下架、查看订阅数量 |
| 订单管理 | 查看订单、支付状态、退款处理进度、查看发票 |
| 账单管理 | 发票列表/详情/导出、账单流水（ledger）、账务对账、Credit Note 管理、交易流水查询 |
| 服务管理 | 用户工单处理、退款申请审核、售后处理记录、用户服务操作日志 |
| 审计日志 | 管理员操作追踪 |

# 3. DNS Control Console（DNS 控制台）

> DNS 控制台负责 DNS 基础设施运维管理，不处理用户业务和财务。

## 3.1 系统职责

负责 DNS 基础设施运维管理，不处理用户业务和财务。

| 职责 |
|---|
| DNS 节点管理 |
| GeoDNS 管理 |
| Config 配置发布 |
| 节点心跳和日志上报接收 |

---

## 3.2 功能列表

| 模块 | 功能 |
|---|---|
| 节点管理 | 添加/编辑/删除/禁用/启用节点；显示名称/国家/城市/IP/状态/CPU/内存/QPS/延迟/最后心跳 |
| GeoDNS 管理 | 国家映射、节点优先级、节点权重、故障切换、健康检查 |
| Profile 发布中心 | 查看 Profile/版本、发布版本、回滚版本、查看发布记录 |
| 规则库管理 | 更新 OISD / AdGuard / EasyList、查看规则数量、查看同步状态 |
| 系统配置 | DNS 参数、日志参数、系统参数、安全参数 |
| 审计日志 | 管理员、时间、IP、操作内容、操作结果 |

# 4. GeoDNS（接入调度层）

> 服务入口调度层，不参与实际 DNS 过滤查询。

## 4.1 系统职责

根据用户来源 IP 返回最佳 DNS 节点。

---

## 4.2 功能

| 组件 | 功能 |
|---|---|
| 权威 DNS 响应 | 应答 `dns.example.com` / `doh.example.com` 等服务域名 |
| GeoIP 路由 | 根据来源 IP 国家/区域选择节点 |
| 健康路由 | 引用 portal-web 健康视图，摘除离线节点 |
| 权重调度 | 节点权重分配、故障回退 |
| 灰度调度 | 逐步切流（Stage 06 完整） |

---

## 4.3 调度流程

用户请求

↓

GeoDNS

↓

识别国家

↓

选择节点

↓

返回 DNS 节点

---

# 5. DNS Resolver（DNS 节点）

> 部署在每台 DNS 服务器上的 Go 单二进制，只负责 DNS 执行和上报。

## 5.1 系统职责

处理所有 DNS 查询，执行规则匹配，上报日志和心跳。

禁止行为：

- 直接连接 MySQL / ClickHouse
- 直接调用 portal-web API
- 创建或修改订单/发票/支付/退款

## 5.2 部署方式

每台 DNS 服务器部署一个 `dns-resolver`。

---

## 5.3 DNS 协议

| 协议 | MVP | Stage 03 |
|---|---|---|
| UDP 53 | ✅ | ✅ |
| TCP 53 | ✅ | ✅ |
| DoH | ✅ | ✅ |
| DoT | ✅ | ✅ |
| DoQ / DoH3 | — | 评估 |

---

## 5.4 功能列表

| 组件 | 功能 |
|---|---|
| Profile 识别 | DoH path、DoT SNI、UDP 来源 IP 映射 |
| 设备识别 | Device Token、HTTP Header、EDNS、Source IP |
| 规则引擎 | exact / suffix / wildcard 匹配，优先级：白名单 > 黑名单 > 安全 > 隐私 > 家长 > 广告 > 默认放行 |
| 安全分类 | 恶意/钓鱼/C2/Cryptojacking 拦截 |
| 隐私分类 | 跟踪器/遥测/分析域名阻断 |
| 家长分类 | 成人内容/安全搜索/YouTube 受限 |
| 白名单 | 优先级高于所有阻断规则 |
| DNS 缓存 | 内存缓存、TTL 管理、缓存统计 |
| 上游转发 | 递归 DNS fallback |
| Agent | 节点注册、心跳上报、配置拉取、配置 ACK |
| 日志上报 | 批量上报查询日志（时间/Profile/Device/Domain/Action/Rule/Node/Latency） |
| 指标上报 | 基础运行指标 |
| 配置热加载 | 原子替换本地配置，版本 + checksum 校验 |
| 失败缓冲 | NATS/HTTP 上报失败时本地 buffer 落盘 |

---

## 5.5 运行要求

| 类别 | 要求 |
|---|---|
| 禁止 | 访问数据库、访问 User Portal、访问 ClickHouse、访问 MySQL |
| 必须 | 使用内存规则、支持热更新、支持水平扩容 |

---

# 6. 系统通信

## 6.1 User Portal → DNS Control Console

| 维度 | 内容 |
|---|---|
| 通信方式 | REST API |
| 功能 | Profile 管理、设备管理、日志查询、统计查询、配置发布 |

---

## 6.2 DNS Control Console → GeoDNS

| 维度 | 内容 |
|---|---|
| 同步 | 节点信息、节点状态、节点权重 |

---

## 6.3 DNS Control Console → Resolver

| 维度 | 内容 |
|---|---|
| 同步 | Profile 配置、规则库、节点配置 |

---

## 6.4 Resolver → DNS Control Console

| 维度 | 内容 |
|---|---|
| 上报 | 节点心跳、DNS 日志、节点状态、告警信息 |

---

# 7. 核心业务规则

| 规则 | 内容 |
|---|---|
| Profile 名称唯一 | — |
| Profile 删除 | 删除前必须解绑设备 |
| Profile 版本 | 支持版本管理 |
| Device Token | 全局唯一 |
| 设备归属 | 一个设备只能属于一个 Profile |
| 日志保留 Free | 7 天 |
| 日志保留 Pro | 90 天 |
| 日志保留 Enterprise | 365 天 |

---

# 8. MVP 范围

## MVP 必须实现

| 系统 | 模块 |
|---|---|
| User Portal | 用户系统、Profile、Device、Allowlist、Denylist、DNS 日志、DNS 统计 |
| DNS Control Console | 节点管理、GeoDNS 管理、配置发布 |
| GeoDNS | 节点调度 |
| DNS Resolver | DoH、DNS53、规则引擎 |

## MVP 暂不实现

| 模块 |
|---|
| Anycast |
| Team |
| Webhook |
| API Key |
| SCIM |
| SSO |
| 企业专属节点 |

---

# 9. 成功标准

| 指标 | 目标 |
|---:|---|
| 用户 | 100+ |
| 设备 | 1000+ |
| DNS 请求 | 100 万 / 天 |
| 性能 P95 | < 20ms |
| 性能 P99 | < 50ms |
| GeoDNS 可用性 | 99.99% |
| Resolver 可用性 | 99.99% |
| 配置同步 | 30 秒内全节点同步 |
