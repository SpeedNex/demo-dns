# OcerDNS Portal-Web

OcerDNS DNS 控制台与会员中心，基于 Laravel + Vue 3 构建的生产级 SaaS 平台。

## 系统架构

```
用户设备 ──DoH──> dns1.ocerlinkdata.com:443 ──> Resolver 服务器
                                                       │
                                                       │ 心跳/配置拉取
                                                       ▼
                                              portal-web + MySQL + ClickHouse
```

## 核心功能

### 会员中心
- 用户注册/登录（Sanctum Token 认证）
- DNS Profile 管理（创建/编辑/发布）
- 安全设置（恶意软件/钓鱼/C2/挖矿防护）
- 隐私保护（跟踪器/分析服务拦截）
- 家长监护（成人内容/赌博/安全搜索）
- 黑名单/白名单规则管理
- 设备注册与管理
- API Key 管理
- 团队管理与成员邀请

### 订阅计费
- Free/Pro/Team 套餐订阅
- Stripe 支付集成
- 用量统计与配额管理
- 会员中心与账单展示

### 管理后台
- 用户管理（搜索/排序/启用禁用）
- DNS 节点管理（心跳监控/在线状态）
- 团队管理
- 规则库管理
- 发布任务管理
- 防护策略配置
- 安全数据管理
- 告警管理
- 系统配置

### DNS 解析服务
- DNS-over-HTTPS (DoH)
- DNS-over-TLS (DoT)
- DNS-over-QUIC (DoQ)
- 传统 UDP/TCP DNS
- 规则引擎（黑名单/白名单/分类拦截）
- 威胁情报集成
- DNSSEC 验证支持（配置项）

## 技术栈

- **后端**: Laravel 11 + PHP 8.2+
- **前端**: Vue 3 + Vite + Element Plus
- **数据库**: MySQL + Redis
- **分析**: ClickHouse
- **DNS 节点**: Go dns-resolver

## 目录结构

```
portal-web/
├── app/
│   ├── Application/          # 应用服务层
│   ├── Domain/                # 领域层
│   │   ├── Auth/             # 认证
│   │   ├── Billing/          # 计费
│   │   ├── Device/           # 设备
│   │   ├── Node/            # 节点
│   │   ├── Plan/            # 套餐
│   │   ├── Profile/         # DNS 配置
│   │   ├── Publish/         # 发布
│   │   ├── Rule/           # 规则
│   │   └── Usage/          # 用量
│   ├── Http/
│   │   ├── Controllers/Api/V1/
│   │   │   ├── Admin/       # 管理后台 API
│   │   │   ├── User/        # 会员 API
│   │   │   └── Node/        # 节点 API
│   │   └── Middleware/     # 中间件
│   ├── Infrastructure/      # 基础设施
│   │   └── ClickHouse/      # ClickHouse 客户端
│   └── Models/             # Eloquent 模型
├── database/
│   ├── migrations/          # 数据库迁移（dns_ 前缀）
│   └── seeders/            # 数据填充
├── routes/v1/
│   ├── admin/              # 管理后台路由
│   └── user/               # 会员路由
└── web/
    ├── src/
    │   ├── views/          # Vue 页面组件
    │   ├── components/      # 公共组件
    │   ├── composables/    # 组合式函数
    │   ├── locales/        # i18n 翻译（zh-CN/en/ko）
    │   └── api/            # API 客户端
    └── dist/               # 前端构建产物
```

## 本地开发

```bash
# 1. 安装依赖
composer install
cd web && npm install && cd ..

# 2. 环境配置
cp .env.example .env
php artisan key:generate

# 3. 数据库迁移
php artisan migrate

# 4. 启动服务
# 后端 API
php artisan serve --host=0.0.0.0 --port=8080
# 前端开发服务器
cd web && npm run dev

# 5. 运行测试
php artisan test

# 6. 前端构建
cd web && npm run build
```

## API 文档

### 认证 API

| 方法 | 路径 | 描述 |
|------|------|------|
| POST | `/api/v1/auth/register` | 用户注册 |
| POST | `/api/v1/auth/login` | 用户登录 |
| POST | `/api/v1/admin/login` | 管理员登录 |

### 会员 API (`auth:api`)

| 方法 | 路径 | 描述 |
|------|------|------|
| GET | `/api/v1/user/me` | 用户信息 |
| GET | `/api/v1/user/profiles` | Profile 列表 |
| POST | `/api/v1/user/profiles` | 创建 Profile |
| PUT | `/api/v1/user/profiles/{profile_id}` | 更新 Profile |
| DELETE | `/api/v1/user/profiles/{profile_id}` | 删除 Profile |
| GET | `/api/v1/user/security` | 安全设置 |
| PUT | `/api/v1/user/security` | 更新安全设置 |
| GET | `/api/v1/user/privacy` | 隐私设置 |
| PUT | `/api/v1/user/privacy` | 更新隐私设置 |
| GET | `/api/v1/user/parental` | 家长监护 |
| PUT | `/api/v1/user/parental` | 更新家长监护 |
| GET | `/api/v1/user/blocklist` | 黑名单列表 |
| POST | `/api/v1/user/blocklist` | 添加黑名单 |
| DELETE | `/api/v1/user/blocklist/{id}` | 删除黑名单 |
| GET | `/api/v1/user/allowlist` | 白名单列表 |
| POST | `/api/v1/user/allowlist` | 添加白名单 |
| GET | `/api/v1/user/devices` | 设备列表 |
| GET | `/api/v1/user/analytics` | 统计分析 |
| GET | `/api/v1/user/subscription` | 订阅信息 |
| GET | `/api/v1/user/usage` | 用量统计 |
| GET | `/api/v1/user/plans` | 套餐列表 |
| GET | `/api/v1/user/teams` | 团队列表 |
| POST | `/api/v1/user/teams` | 创建团队 |

### 管理员 API (`auth:sanctum`)

| 方法 | 路径 | 描述 |
|------|------|------|
| GET | `/api/v1/admin/overview` | 概览统计 |
| GET | `/api/v1/admin/users` | 用户列表 |
| GET | `/api/v1/admin/nodes` | 节点列表 |
| GET | `/api/v1/admin/alerts` | 告警列表 |
| GET | `/api/v1/admin/rules` | 规则库 |
| GET | `/api/v1/admin/teams` | 团队管理 |

### 节点 API (`node.api_key`)

| 方法 | 路径 | 描述 |
|------|------|------|
| POST | `/api/v1/node/heartbeat` | 节点心跳 |
| GET | `/api/v1/node/config/{node_id}` | 获取配置 |
| POST | `/api/v1/node/query-logs` | 上报查询日志 |

## 数据库

### 主要表（`dns_` 前缀）

| 表名 | 描述 |
|------|------|
| `dns_users` | 用户表 |
| `dns_profiles` | DNS 配置表 |
| `dns_subscriptions` | 订阅表 |
| `dns_devices` | 设备表 |
| `dns_resolver_nodes` | 解析节点表 |
| `dns_config_versions` | 配置版本表 |
| `dns_blocklist` | 黑名单表 |
| `dns_allowlist` | 白名单表 |
| `dns_rule_items` | 规则项表 |
| `dns_rule_sources` | 规则源表 |
| `dns_usage_records` | 用量记录表 |
| `dns_billing_periods` | 计费周期表 |

### ClickHouse 表

| 表名 | 描述 |
|------|------|
| `dns_logs` | DNS 查询日志 |
| `usage_events` | 用量事件 |

## 部署

### 环境要求

- PHP 8.2+
- MySQL 8.0+
- Redis 6+
- Node.js 18+
- Composer 2+

### 部署步骤

```bash
# 1. 克隆代码
git clone <repository>
cd portal-web

# 2. 安装依赖
composer install --optimize-autoloader
cd web && npm install && npm run build && cd ..

# 3. 配置环境
cp .env.example .env
php artisan key:generate
php artisan config:cache

# 4. 数据库迁移
php artisan migrate --force

# 5. 构建前端
cd web && npm run build && cd ..

# 6. 配置 Web 服务器（Nginx）
# 参考 deploy/nginx.conf 示例配置
```

### Cron 任务

```bash
# 用量聚合（每5分钟）
* * * * * cd /path-to-portal-web && php artisan schedule:run >> /dev/null 2>&1

# 配额检测（每5分钟）
* * * * * cd /path-to-portal-web && php artisan quota:check >> /dev/null 2>&1

# ClickHouse 日志重试（每5分钟）
* * * * * cd /path-to-portal-web && php artisan clickhouse:retry-failed-batches >> /dev/null 2>&1
```

## DNS 节点部署

Resolver 二进制安装脚本：

```bash
curl -fsSL https://your-domain.com/build/dns-resolver-install.sh \
  | bash -s -- \
    --server=https://your-domain.com \
    --token=<node_token> \
    --node-id=<node_id>
```

## 测试

```bash
# 运行所有测试
php artisan test

# API 测试
php artisan test --filter=ApiTest

# 工作空间测试
php artisan test --filter=MemberWorkspaceTest

# 前端 lint
cd web && npm run lint
```

## 环境变量

| 变量 | 描述 | 默认值 |
|------|------|--------|
| `APP_URL` | 应用 URL | - |
| `DB_HOST` | 数据库主机 | 127.0.0.1 |
| `DB_DATABASE` | 数据库名 | ocer_dns |
| `REDIS_HOST` | Redis 主机 | 127.0.0.1 |
| `CLICKHOUSE_HOST` | ClickHouse 主机 | 127.0.0.1 |
| `CLICKHOUSE_PORT` | ClickHouse 端口 | 8123 |
| `CLICKHOUSE_DATABASE` | ClickHouse 数据库 | ocer_dns |
| `CLICKHOUSE_ENABLED` | 启用 ClickHouse | true |
| `STRIPE_KEY` | Stripe Key | - |
| `STRIPE_SECRET` | Stripe Secret | - |

## 相关文档

- [项目文档](../ai-doc/)
- [架构文档](../ai-doc/01-ARCHITECTURE.md)
- [数据流文档](../ai-doc/03-DATA-FLOW.md)
- [Resolver 文档](../dns-resolver/)
- [API 契约](../contracts/openapi.yaml)
