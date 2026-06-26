# OcerDNS 安全功能完整实施方案

## 一、架构原则

> **数据放 Portal，算法放 Resolver**
>
> **新功能无需升级 Resolver，只增数据**

***

## 二、最终菜单结构

```
┌─────────────────────────────────────────────────────────────────────────┐
│ Threat Intelligence（威胁情报）                                          │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  Data Sources（数据源）  ← 现有 RuleLibrary.vue 改名                    │
│  ├── CRUD 数据源                                                        │
│  ├── 同步管理（手动/定时）                                             │
│  ├── 同步历史（内嵌）                                                  │
│  └── 格式支持：domain_list / adblock / hosts / rpz / json              │
│                                                                         │
│  Rules（规则管理）  ← 新增                                              │
│  ├── 规则列表（分类/标签筛选）                                         │
│  ├── 分类管理（rule_categories 表）                                     │
│  ├── 导入/导出                                                         │
│  └── 批量操作                                                          │
│                                                                         │
│  Publish Center（发布中心）  ← 新增                                     │
│  ├── 版本历史                                                          │
│  ├── 状态监控（queued/running/succeeded/failed）                       │
│  ├── 节点进度（applied/failed/total）                                  │
│  ├── 回滚功能                                                          │
│  └── 一键全量发布                                                      │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────────┐
│ Protection（防护配置）                                                  │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  Protection Policies（防护策略）  ← 新增                                 │
│  ├── DNS 安全                                                          │
│  │   ├── DNS Rebinding Protection [开关]                               │
│  │   ├── IDN Homograph Protection [开关]                               │
│  │   ├── Typosquatting Protection [开关]                              │
│  │   │   └── Brand List 选择                                          │
│  │   │   └── Match Threshold [1-2]                                     │
│  │   └── DGA Protection [开关]                                        │
│  │       └── Entropy Threshold [4.0-5.0]                              │
│  │       └── Digit Ratio [0.4-0.8]                                    │
│  │                                                                       │
│  ├── Threat Intelligence                                               │
│  │   ├── Malware [开关] → category=malware                            │
│  │   ├── Phishing [开关] → category=phishing                          │
│  │   ├── Cryptojacking [开关] → category=cryptojacking                │
│  │   ├── Dynamic DNS [开关] → category=dynamic_dns                     │
│  │   ├── Parked Domain [开关] → category=parked                       │
│  │   └── New Registered Domain [开关] → 天数阈值                      │
│  │                                                                       │
│  ├── Privacy Protection                                                │
│  │   ├── Tracker [开关] → category=tracker                            │
│  │   ├── Analytics [开关] → category=analytics                        │
│  │   ├── Telemetry [开关] → category=telemetry                        │
│  │   ├── Disguised Tracker [开关]                                     │
│  │   └── Allow Marketing Links [开关]                                 │
│  │                                                                       │
│  └── Family/Parental                                                   │
│      ├── Adult Content [开关]                                           │
│      ├── Gambling [开关]                                               │
│      └── Safe Search [开关]                                             │
│                                                                         │
│  Security Data（安全数据）  ← 新增父级                                  │
│  ├── Brands（品牌列表）                                                │
│  │   ├── 预置 Top 1000/10000 品牌                                     │
│  │   ├── 添加/删除/导入                                               │
│  │   └── 用于 Typosquatting 检测                                       │
│  │                                                                       │
│  ├── Dynamic DNS（DDNS 提供商）                                       │
│  │   └── duckdns / no-ip / dynu / changeip / afraid                   │
│  │                                                                       │
│  ├── Parked Domains（停放域名）                                        │
│  │   └── ParkingCrew / Sedo / Bodis                                   │
│  │                                                                       │
│  ├── TLD Blacklist（顶级域名黑名单）                                   │
│  │   └── .tk / .ml / .ga / .cf 等                                     │
│  │                                                                       │
│  ├── Allow Lists（白名单）                                             │
│  │                                                                       │
│  └── Block Lists（黑名单）                                             │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘
```

***

## 三、数据库变更

### 3.1 现有表修改

#### `rule_sources` 表

```php
// 修改前
$table->enum('category', ['security','privacy','parental','custom'])->default('custom');

// 修改后
$table->string('category', 60)->default('custom');  // 支持更多分类
$table->string('source_type', 40)->default('threat_feed');  // threat_feed / whois / rdap / enterprise
$table->string('description', 500)->nullable();  // 来源描述
$table->string('homepage', 500)->nullable();  // 来源官网
```

#### `rule_items` 表

```php
// 增加字段
$table->string('tag', 50)->nullable()->after('category');  // apple/android/windows 等标签
$table->string('source_domain', 255)->nullable();  // 原始域名（用于 CNAME 检测）
$table->timestamp('expires_at')->nullable();  // 过期时间（用于临时规则）
$table->string('confidence', 20)->default('high');  // high/medium/low
```

### 3.2 新增表

#### `rule_categories` 表

```php
Schema::create('rule_categories', function (Blueprint $table) {
    $table->id();
    $table->string('code', 40)->unique();  // tracker / malware / phishing / ads 等
    $table->string('name', 100);  // 显示名称
    $table->string('name_en', 100);  // 英文名
    $table->string('description', 500)->nullable();
    $table->string('icon', 50)->nullable();  // UI 图标
    $table->string('color', 20)->nullable();  // UI 颜色
    $table->string('parent_code', 40)->nullable();  // 父分类
    $table->enum('group', ['threat','privacy','family','custom'])->default('threat');
    $table->boolean('enabled')->default(true);
    $table->boolean('is_system')->default(false);  // 系统分类不可删除
    $table->unsignedInteger('sort_order')->default(0);
    $table->timestamps();
});
```

**预置分类：**

| code          | name  | group   | 说明         |
| :------------ | :---- | :------ | :--------- |
| malware       | 恶意软件  | threat  | 病毒/木马/勒索软件 |
| phishing      | 钓鱼网站  | threat  | 钓鱼欺诈       |
| cryptojacking | 挖矿劫持  | threat  | 加密货币挖矿     |
| tracker       | 跟踪器   | privacy | 广告/分析跟踪    |
| analytics     | 分析跟踪  | privacy | 网站分析       |
| telemetry     | 遥测    | privacy | 系统遥测       |
| ads           | 广告    | privacy | 广告域名       |
| adult         | 成人内容  | family  | 色情内容       |
| gambling      | 赌博    | family  | 博彩网站       |
| social        | 社交媒体  | family  | 社交平台       |
| gaming        | 游戏    | family  | 游戏平台       |
| dynamic\_dns  | 动态DNS | threat  | DDNS 服务商   |
| parked        | 停放域名  | threat  | 广告/出售域名    |
| typosquatting | 误植域名  | threat  | 品牌误植       |
| dga           | DGA域名 | threat  | 算法生成域名     |
| new\_domain   | 新注册域名 | threat  | 30天内注册     |

#### `brands` 表

```php
Schema::create('brands', function (Blueprint $table) {
    $table->id();
    $table->string('domain', 255)->unique();  // google.com
    $table->string('name', 100);  // Google
    $table->string('category', 50)->nullable();  // tech/finance/ecommerce/social
    $table->unsignedInteger('alexa_rank')->nullable();  // 全球排名
    $table->boolean('enabled')->default(true);
    $table->timestamps();
});
```

#### `security_policies` 表（可选，最终用 system\_configs）

```php
// 使用 system_configs 分组存储，key 格式：
// protection.dns_rebind.enabled = true
// protection.dns_rebind.whitelist = ["localhost","*.local"]
// protection.idn.enabled = true
// protection.typo.enabled = true
// protection.typo.brand_list_id = 1
// protection.typo.threshold = 1
// protection.dga.enabled = true
// protection.dga.entropy_threshold = 4.2
// protection.dga.digit_ratio = 0.6
// protection.category.malware.enabled = true
// protection.category.tracker.enabled = true
// ... 其他分类开关
```

***

## 四、后端开发清单

### 4.1 现有控制器增强

| 控制器                      | 现有功能      | 需增强                  |
| :----------------------- | :-------- | :------------------- |
| `AdminRuleController`    | CRUD + 同步 | 改名 DataSources、增来源类型 |
| `AdminPublishController` | 发布任务      | 已有 Publish Center 基础 |

### 4.2 新增控制器

| 控制器                               | 路由                           | 功能                          |
| :-------------------------------- | :--------------------------- | :-------------------------- |
| `AdminRuleItemController`         | `/admin/rules`               | 规则列表/搜索/分类/标签/导入导出          |
| `AdminCategoryController`         | `/admin/rule-categories`     | 分类 CRUD                     |
| `AdminBrandController`            | `/admin/brands`              | 品牌列表/导入导出                   |
| `AdminSecurityDataController`     | `/admin/security-data/*`     | DDNS/Parked/TLD/Allow/Block |
| `AdminProtectionPolicyController` | `/admin/protection-policies` | 防护策略配置                      |
| `AdminPublishCenterController`    | `/admin/publish-center/*`    | 发布中心（增强）                    |

### 4.3 API 路由设计

```php
// Data Sources
GET    /admin/data-sources           // 列表
POST   /admin/data-sources           // 创建
PUT    /admin/data-sources/{id}      // 更新
DELETE /admin/data-sources/{id}      // 删除
POST   /admin/data-sources/{id}/sync // 同步
GET    /admin/data-sources/{id}/history // 同步历史

// Rules
GET    /admin/rules                  // 规则列表（分页/筛选）
DELETE /admin/rules/{id}             // 删除规则
POST   /admin/rules/batch-delete     // 批量删除
POST   /admin/rules/import          // 导入
GET    /admin/rules/export          // 导出

// Rule Categories
GET    /admin/rule-categories       // 分类列表
POST   /admin/rule-categories       // 创建分类
PUT    /admin/rule-categories/{id}  // 更新分类
DELETE /admin/rule-categories/{id}  // 删除分类

// Brands
GET    /admin/brands                // 品牌列表
POST   /admin/brands                // 添加品牌
PUT    /admin/brands/{id}           // 更新品牌
DELETE /admin/brands/{id}           // 删除品牌
POST   /admin/brands/import        // 批量导入
GET    /admin/brands/export         // 导出

// Security Data
GET    /admin/security-data/dynamic-dns     // DDNS 列表
POST   /admin/security-data/dynamic-dns     // 添加
DELETE /admin/security-data/dynamic-dns/{id} // 删除

GET    /admin/security-data/parked-domains  // 停放域名列表
POST   /admin/security-data/parked-domains  // 添加
DELETE /admin/security-data/parked-domains/{id} // 删除

GET    /admin/security-data/tld-blacklist   // TLD 黑名单
POST   /admin/security-data/tld-blacklist   // 添加
DELETE /admin/security-data/tld-blacklist/{id} // 删除

GET    /admin/security-data/allow-lists     // 白名单
POST   /admin/security-data/allow-lists     // 添加
DELETE /admin/security-data/allow-lists/{id} // 删除

GET    /admin/security-data/block-lists     // 黑名单
POST   /admin/security-data/block-lists     // 添加
DELETE /admin/security-data/block-lists/{id} // 删除

// Protection Policies
GET    /admin/protection-policies          // 获取所有策略
PUT    /admin/protection-policies          // 保存策略
GET    /admin/protection-policies/export   // 导出配置
POST   /admin/protection-policies/import   // 导入配置

// Publish Center
GET    /admin/publish-center/tasks         // 发布任务列表
POST   /admin/publish-center/tasks        // 创建发布任务
POST   /admin/publish-center/tasks/{id}/retry  // 重试
POST   /admin/publish-center/tasks/{id}/cancel  // 取消
GET    /admin/publish-center/versions      // 版本历史
POST   /admin/publish-center/rollback/{version_id} // 回滚
POST   /admin/publish-center/sync-all     // 一键全量发布
```

***

## 五、前端开发清单

### 5.1 页面组件

| 页面                       | 路由                                    | 说明                  |
| :----------------------- | :------------------------------------ | :------------------ |
| `DataSources.vue`        | `/admin/data-sources`                 | 改名自 RuleLibrary.vue |
| `Rules.vue`              | `/admin/rules`                        | 新增                  |
| `RuleCategories.vue`     | `/admin/rule-categories`              | 新增                  |
| `Brands.vue`             | `/admin/brands`                       | 新增                  |
| `SecurityData.vue`       | `/admin/security-data`                | 新增（父级菜单）            |
| `DDNSList.vue`           | `/admin/security-data/dynamic-dns`    | 新增                  |
| `ParkedDomains.vue`      | `/admin/security-data/parked-domains` | 新增                  |
| `TLDBlacklist.vue`       | `/admin/security-data/tld-blacklist`  | 新增                  |
| `AllowLists.vue`         | `/admin/security-data/allow-lists`    | 新增                  |
| `BlockLists.vue`         | `/admin/security-data/block-lists`    | 新增                  |
| `ProtectionPolicies.vue` | `/admin/protection-policies`          | 新增                  |
| `PublishCenter.vue`      | `/admin/publish-center`               | 新增（增强）              |

### 5.2 页面布局设计

#### ProtectionPolicies.vue

```
┌─────────────────────────────────────────────────────────────────────────┐
│ 防护策略配置                                                       [保存] │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  ┌─ DNS 安全 ──────────────────────────────────────────────────────┐   │
│  │                                                                   │   │
│  │  DNS Rebinding Protection    [====○    ] Enabled                │   │
│  │    ├─ Whitelist: [输入框，支持通配符 *.local]                   │   │
│  │                                                                   │   │
│  │  IDN Homograph Protection    [====●    ] Enabled                │   │
│  │                                                                   │   │
│  │  Typosquatting Protection    [====●    ] Enabled                │   │
│  │    ├─ Brand List: [Dropdown: All Brands ▼]                     │   │
│  │    └─ Match Threshold: [1] (1=exact, 2=fuzzy)                 │   │
│  │                                                                   │   │
│  │  DGA Protection             [====●    ] Enabled                │   │
│  │    ├─ Entropy Threshold: [4.2] (4.0-5.0)                      │   │
│  │    └─ Digit Ratio: [0.6] (0.4-0.8)                             │   │
│  │                                                                   │   │
│  └─────────────────────────────────────────────────────────────────┘   │
│                                                                         │
│  ┌─ 威胁情报 ──────────────────────────────────────────────────────┐   │
│  │                                                                   │   │
│  │  [●] Malware         [●] Phishing       [●] Cryptojacking     │   │
│  │  [○] Dynamic DNS     [●] Parked Domain  [○] New Domain (30天)  │   │
│  │                                                                   │   │
│  └─────────────────────────────────────────────────────────────────┘   │
│                                                                         │
│  ┌─ 隐私保护 ──────────────────────────────────────────────────────┐   │
│  │                                                                   │   │
│  │  [●] Tracker       [●] Analytics      [●] Telemetry           │   │
│  │  [●] Disguised Tracker  [○] Allow Marketing Links              │   │
│  │                                                                   │   │
│  └─────────────────────────────────────────────────────────────────┘   │
│                                                                         │
│  ┌─ 家长控制 ──────────────────────────────────────────────────────┐   │
│  │                                                                   │   │
│  │  [○] Adult Content    [○] Gambling       [○] Safe Search      │   │
│  │                                                                   │   │
│  └─────────────────────────────────────────────────────────────────┘   │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘
```

#### PublishCenter.vue

```
┌─────────────────────────────────────────────────────────────────────────┐
│ 发布中心                                        [一键全量发布] [清理历史] │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  ┌─ 统计概览 ──────────────────────────────────────────────────────┐   │
│  │  Pending: 2  |  Running: 1  |  Succeeded: 156  |  Failed: 3     │   │
│  └─────────────────────────────────────────────────────────────────┘   │
│                                                                         │
│  ┌─ 发布任务 ──────────────────────────────────────────────────────┐   │
│  │                                                                   │   │
│  │  Version  │ Status      │ Nodes        │ Progress │ Actions      │   │
│  │  ──────────────────────────────────────────────────────────────  │   │
│  │  2026062601 │ succeeded  │ 10/10       │ 100%    │ [详情][回滚] │   │
│  │  2026062602 │ running    │ 7/10        │  70%    │ [详情]       │   │
│  │  2026062603 │ queued     │ 0/10        │   0%    │ [详情][取消]  │   │
│  │  2026062604 │ failed     │ 2/10        │  20%    │ [详情][重试]  │   │
│  │                                                                   │   │
│  └─────────────────────────────────────────────────────────────────┘   │
│                                                                         │
│  [分页: 1 2 3 ... 15]                                                   │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘
```

***

## 六、Resolver 算法实现清单

| 算法            | 位置                            | 输入                  | 输出          | 说明                                    |
| :------------ | :---------------------------- | :------------------ | :---------- | :------------------------------------ |
| DNS Rebinding | `resolver/handler.go`         | DNS Response IP     | BLOCK/ALLOW | RFC1918/4193/Loopback/LinkLocal       |
| IDN Homograph | `rules/idn.go` (新建)           | Domain              | BLOCK/ALLOW | UTS #39 Skeleton                      |
| Typosquatting | `rules/typosquatting.go` (新建) | Domain + Brand List | BLOCK/ALLOW | Levenshtein Distance ≤ threshold      |
| DGA Detection | `rules/dga.go` (新建)           | Domain              | BLOCK/ALLOW | Entropy + NGram + Length + DigitRatio |
| CNAME Tracker | `resolver/handler.go`         | CNAME Chain         | BLOCK/ALLOW | 已知 tracker 域名列表                       |
| SafeSearch    | `resolver/resolver.go` (已有)   | Search Domain       | REWRITE     | Google/Bing/YouTube/DuckDuckGo        |

***

## 七、实施优先级

### Phase 1：数据层（1-2天）

1. 创建 `rule_categories` 表和迁移
2. 创建 `brands` 表和迁移
3. 修改 `rule_sources` 表（category 改为 string）
4. 修改 `rule_items` 表（增加 tag 字段）
5. 创建 `AdminCategoryController` + `RuleCategories.vue`
6. 创建 `AdminBrandController` + `Brands.vue`
7. 导入 Top 1000 品牌数据

### Phase 2：核心功能（2-3天）

1. 创建 `AdminRuleItemController` + `Rules.vue`
2. 创建 `AdminSecurityDataController` + 各子页面
3. 创建 `AdminProtectionPolicyController` + `ProtectionPolicies.vue`
4. 在 `system_configs` 增加 protection 配置分组

### Phase 3：发布中心（1-2天）

1. 增强 `AdminPublishController`
2. 创建 `PublishCenter.vue`
3. 增加回滚功能

### Phase 4：Resolver 算法（2-3天）

1. 实现 DNS Rebinding 检测
2. 实现 IDN Homograph 检测（UTS #39）
3. 实现 Typosquatting 检测
4. 实现 DGA 检测
5. 实现 CNAME Tracker 检测
6. 集成到 `matching.Engine`

### Phase 5：数据填充（1天）

1. 配置预置分类
2. 导入主流 Threat Feed 数据源
3. 配置 DDNS/Parked/TLD 数据

***

## 八、预计工时

| Phase   | 内容          | 预计工时      |
| :------ | :---------- | :-------- |
| Phase 1 | 数据层         | 2 人天      |
| Phase 2 | 核心功能        | 3 人天      |
| Phase 3 | 发布中心        | 2 人天      |
| Phase 4 | Resolver 算法 | 3 人天      |
| Phase 5 | 数据填充        | 1 人天      |
| **合计**  | <br />      | **11 人天** |

***

## 九、验收标准

1. ✅ 后台可管理所有数据源（增删改查/同步）
2. ✅ 规则可按分类/标签筛选和操作
3. ✅ 分类可配置，新增无需改 Resolver
4. ✅ 防护策略全部可配置
5. ✅ 发布中心支持版本/回滚
6. ✅ Resolver 正确执行所有算法
7. ✅ 端到端测试：配置 → 发布 → 解析 → 拦截

***

## 十、文件清单

### 新增文件

```
后端（Controller）：
- app/Http/Controllers/Api/V1/Admin/AdminRuleItemController.php
- app/Http/Controllers/Api/V1/Admin/AdminCategoryController.php
- app/Http/Controllers/Api/V1/Admin/AdminBrandController.php
- app/Http/Controllers/Api/V1/Admin/AdminSecurityDataController.php
- app/Http/Controllers/Api/V1/Admin/AdminProtectionPolicyController.php
- app/Http/Controllers/Api/V1/Admin/AdminPublishCenterController.php

Model：
- app/Models/RuleCategory.php
- app/Models/Brand.php

前端（Vue）：
- web/src/views/admin/Rules.vue
- web/src/views/admin/RuleCategories.vue
- web/src/views/admin/Brands.vue
- web/src/views/admin/SecurityData.vue
- web/src/views/admin/DDNSList.vue
- web/src/views/admin/ParkedDomains.vue
- web/src/views/admin/TLDBlacklist.vue
- web/src/views/admin/AllowLists.vue
- web/src/views/admin/BlockLists.vue
- web/src/views/admin/ProtectionPolicies.vue
- web/src/views/admin/PublishCenter.vue
- web/src/views/admin/DataSources.vue（改名自 RuleLibrary.vue）

Resolver（Go）：
- dns-resolver/internal/rules/idn.go
- dns-resolver/internal/rules/typosquatting.go
- dns-resolver/internal/rules/dga.go

数据库迁移：
- database/migrations/2026_06_xx_create_rule_categories_table.php
- database/migrations/2026_06_xx_create_brands_table.php
- database/migrations/2026_06_xx_modify_rule_sources_table.php
- database/migrations/2026_06_xx_modify_rule_items_table.php
```

### 修改文件

```
后端：
- app/Http/Controllers/Api/V1/Admin/AdminRuleController.php
- app/Http/Controllers/Api/V1/Admin/AdminPublishController.php
- app/Models/RuleSource.php
- app/Models/RuleItem.php

前端：
- web/src/views/admin/RuleLibrary.vue → DataSources.vue
- web/src/router/index.js（增加路由）
- web/src/i18n/zh-CN.json（增加翻译）
- web/src/i18n/en.json
- web/src/i18n/ko.json

Resolver：
- dns-resolver/internal/resolver/handler.go
- dns-resolver/internal/matching/engine.go
```

