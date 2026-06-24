# 功能清单（Features）

> 本文件保留全量产品能力视图；第一版实现范围以 `project-doc/04-MVP-SCOPE.md` 为准。
> 财务数据模型、金额约束、幂等、用量入账闭环不再后置：即使支付 UI / 真实支付接入放到 Stage 04，数据库和契约也必须按 `specs/portal-web/billing-finance.md` 从 MVP 开始生成。

## 1. MVP 功能

| 系统 | 模块 | 功能 | 状态 |
|---|---|---|---|
| portal-web | Auth | 注册、登录、退出、当前用户 | 代码草案 |
| portal-web | Dashboard | 查询量、拦截量、活跃设备、Profile 数量 | 代码草案 |
| portal-web | Profile | Profile CRUD、默认配置、版本草案、批量删除、复制 | 代码草案 |
| portal-web | Rules | 白名单、黑名单、自定义规则、行内编辑、批量删除 | 代码草案 |
| portal-web | Security | 安全防护 Lite：恶意、钓鱼、C2、Cryptojacking 基础拦截 | 代码草案 |
| portal-web | Privacy | 隐私保护 Lite：跟踪器/遥测阻断、日志模式、IP 匿名化 | 代码草案 |
| portal-web | Parental | 家长监护 Lite：成人内容、安全搜索、YouTube 受限模式 | 代码草案 |
| portal-web | Device | 设备管理、接入指南 | 代码草案 |
| portal-web | Publish | 发布 Profile 配置版本 | 代码草案 |
| portal-web | Logs | 查询日志筛选、分页 | 代码草案 |
| portal-web | Stats | 今日查询、拦截数、Top 域名、按分类（安全/隐私/家长等）维度聚合、Free 额度进度 | MVP |
| portal-web | Plan | 套餐查看、升级、续费、取消 | 代码草案 |
| portal-web | Billing | 套餐用量计算、超额判定与扣费；用量仅从 query log batch 派生；写接口幂等 | MVP |
| portal-web | Wallet | 余额、账单、充值、退款；金额 amount_minor bigint；发票定稿不可变 | MVP |
| portal-web | Notification | 告警通知：用量超额、扣费失败、节点离线、Heartbeat 异常、登录风控、Payment webhook 失败 | MVP |
| portal-web | Classification | 域名分类统计：按安全/隐私/家长等分类维度对查询/拦截/用量做聚合，支撑用户端 Dashboard 与管理后台分类视图 | MVP |
| portal-web | Membership | 安全/隐私/家长/黑白名单/统计/日志/设置/会员中心 | 代码草案 |
| portal-web | Settings | 密码、邮箱、语言、时区 | 代码草案 |
| portal-web | Team | 团队管理：创建团队、邀请成员、角色分配（owner/admin/member）、成员管理、角色变更、批量取消邀请、退出团队、转移所有权、团队切换 | 代码草案 |
| portal-web | RBAC | 管理员角色与权限：5 个内置角色、23 个权限点、14 个导航栏目、角色-权限映射、角色-导航规则映射、用户-角色分配、Admin 模型 `roles()` / `assignRole()` / `hasNavKey()` | 代码草案 |
| `portal-web(原 console 域)` | Nodes | 节点预创建、ApiKey/Secret 预签发、状态列表、节点 CRUD、批量删除、启用/禁用、凭据重新签发与吊销 | 代码草案 |
| `portal-web(原 console 域)` | Heartbeat | 心跳接收、online/offline（基于 last_heartbeat_at 超时） | 代码草案 |
| `portal-web(原 console 域)` | Config | 配置版本构建、拉取、ACK | 代码草案 |
| `portal-web(原 console 域)` | Ingest | 查询日志 batch 接收（指标 batch 已下线） | 代码草案 |
| `portal-web(原 console 域)` | Publish | 发布任务列表、单/批量重试、单/批量取消、清理已完成 | 代码草案 |
| `portal-web(原 console 域)` | GeoDNS | 国家/区域映射、节点优先级/权重/健康、CRUD、批量删除 | 代码草案 |
| `portal-web(原 console 域)` | SystemConfig | DNS/日志/安全参数配置 | 代码草案 |
| `portal-web(原 console 域)` | Audit | 管理员操作审计日志、过滤、NDJSON 导出、批量删除 | 代码草案 |
| `portal-web(原 console 域)` | RuleLibrary | 规则源 CRUD、批量删除、批量同步、立即同步 | 代码草案 |
| dns-resolver | DNS | UDP 53、TCP 53、DoH、DoT | 代码草案 |
| dns-resolver | RuleEngine | exact / suffix / wildcard 匹配，多规则优先级 | 代码草案 |
| dns-resolver | Security | 恶意/钓鱼/C2/Cryptojacking 拦截 | 代码草案 |
| dns-resolver | Privacy | 跟踪器/遥测/分析域名阻断 | 代码草案 |
| dns-resolver | Parental | 成人内容/安全搜索/YouTube 受限 | 代码草案 |
| dns-resolver | Profile | DoH path / DoT SNI / 来源 IP 识别 | 代码草案 |
| dns-resolver | Cache | 内存缓存、TTL 管理 | 代码草案 |
| dns-resolver | Upstream | 递归 DNS fallback | 代码草案 |
| dns-resolver | Agent | 注册、心跳、拉配置、ACK | 代码草案 |
| dns-resolver | Logs | 查询日志批量上报 | 代码草案 |
| dns-resolver | Metrics | 基础运行指标上报 | 代码草案 |
| dns-resolver | Buffer | 上报失败时本地 buffer 落盘 | 代码草案 |
| geodns | Routing | 权威 DNS 响应、GeoIP 路由、健康路由 | 代码草案 |
| geodns | HTTPHealthView | `GET /health` `GET /health-view` `GET /pick?region=...` 周期刷新控制台健康视图 | 代码草案 |
| geodns | Weight | 节点权重分配、故障回退 | 代码草案 |
| geodns | GrayScale | 灰度调度（Stage 06 完整） | — |

## 1A. 合并计划注(已落地,只影响 §1 / §4 的归属列,不影响实现)

> `dns-console-web` 已于 2026-06-15 至 2026-06-16 之间并入 `portal-web` 的总后台(原 console 域)子命名空间,项目从 4 包缩减为 3 包。本节所有 `dns-console-web` 行的归属列在代码侧已统一为 `portal-web(原 console 域)`,实现位置迁入 `portal-web/app/Http/Controllers/Api/V1/Admin/*` / `Agent/*` / `Internal/*`,路由路径与功能行为完全保留。详见 [`07-CHANGE-LOG.md` 2026-06-15 合并条目](07-CHANGE-LOG.md) 与 [`05-PLANS.md` §8A](05-PLANS.md)。

## 2. 后续功能

| 阶段 | 功能 |
|---|---|
| Stage 02 | 广告规则源自动更新、规则源订阅、设备识别增强 |
| Stage 03 | DoT、TCP 53、配置灰度、自动回滚、Prometheus、NATS JetStream |
| Stage 05 | API Key、Webhook、CSV 导出 |
| Stage 06 | 多区域、GeoDNS 完整调度、自动扩容 |
| Stage 07 | 企业专属节点、SSO / SCIM、Anycast、高级报表 |

## 3. 用户端功能

| 模块 | 功能 | 阶段 |
|---|---|---|
| Dashboard | 查询量、拦截量、活跃设备、Profile 数量 | MVP |
| Profile | 创建、编辑、删除、复制、发布 | MVP |
| 接入设置 | DoH URL、UDP 地址、平台配置指南 | MVP |
| 白名单 | exact / suffix / wildcard 域名 | MVP |
| 黑名单 | exact / suffix / wildcard 域名 | MVP |
| 日志 | 分页、筛选、动作、设备、域名 | MVP |
| 统计 | Top 域名、Top 阻断、基础趋势、按分类（安全/隐私/家长等）聚合、Free 额度进度 | MVP |
| 设备 | 添加、删除、来源 IP 绑定、Device ID | MVP |
| 安全防护 | 恶意、钓鱼、C2、Cryptojacking 基础开关 | MVP Lite |
| 隐私保护 | 跟踪器/遥测阻断、日志模式、IP 匿名化 | MVP Lite |
| 家长监护 | 成人内容、安全搜索、YouTube 受限模式 | MVP Lite |
| 高级分类 | 赌博、游戏、社交、短视频、规则源订阅 | Stage 02 |
| 团队 | 创建团队、邀请成员、角色分配、成员管理、团队切换 | MVP |
| 账单 | 套餐、订单、发票、支付 UI、充值、退款；底层财务表/接口为 MVP 强约束 | MVP |

## 4. 管理后台功能

| 模块 | 功能 | 归属 |
|---|---|---|
| 用户管理 | 列表、详情、禁用、解禁 | portal-web |
| 套餐管理 | 套餐 CRUD、限制项配置 | portal-web |
| 订单管理 | 订单列表、支付状态、退款处理 | portal-web |
| 账单管理 | 发票管理、账务对账、Credit Note、交易流水 | portal-web |
| 服务管理 | 工单处理、退款审核、售后记录 | portal-web |
| 审计日志 | 管理员操作追踪 | portal-web |
| 团队管理 | 团队列表、成员管理、角色分配 | portal-web |
| 节点管理 | 节点列表、心跳、版本、负载 | `portal-web(原 console 域)` |
| 发布任务 | 发布记录、重试、失败原因 | `portal-web(原 console 域)` |
| 健康视图 | online / offline 节点 | `portal-web(原 console 域)` |
| 心跳监控 | 心跳列表、超时判定、负载、配置版本一致性 | `portal-web(原 console 域)` |
| 配置版本管理 | 版本列表、发布进度、ACK 状态、回滚 | `portal-web(原 console 域)` |
| 查询日志检索 | 多维筛选（用户/节点/动作/分类/域名）、下钻、导出 | `portal-web(原 console 域)` |
| 分类统计 | 按安全/隐私/家长等分类维度的查询/拦截/用量聚合 | `portal-web(原 console 域)` |
| 拦截统计 | 命中规则 Top、命中分类占比、节点/时段拦截量 | `portal-web(原 console 域)` |
| 用量监控 | 每用户/团队/节点用量、套餐进度、超额前预警 | portal-web |
| 计费管理 | 用量入账、扣费记录、阶梯定价、自动超额规则 | portal-web |
| 告警中心 | 告警列表、分派/确认/关闭、规则配置 | portal-web |

## 5. 内部能力

| 能力 | 说明 | 阶段 |
|---|---|---|
| 控制面 / 数据面分离 | Web 不参与实时 DNS 查询 | MVP |
| 配置版本化 | `config_version` + `checksum` | MVP |
| 配置热加载 | resolver 原子替换本地配置 | MVP |
| 心跳 | resolver 上报在线/离线状态（Bearer + HMAC），健康度由控制面后置超时判定 | MVP |
| 日志上报 | resolver 批量上报查询日志（Bearer + HMAC） | MVP |
| 本地 buffer | 上报失败时落盘缓冲 | MVP |
| 节点凭据预签发 | Console 签发 (api_key, secret)，resolver 通过 `resolver install` 落 yaml | MVP |
| NATS | 规模化异步链路 | Stage 03 |
| 告警通知 | 用量超额、扣费失败、节点离线、Heartbeat 异常、登录风控、Payment webhook 失败 | MVP |
| ClickHouse | 长期日志分析 | MVP / Stage 02 |
| GeoDNS | 节点入口调度 | MVP 简化 / Stage 06 完整 |

