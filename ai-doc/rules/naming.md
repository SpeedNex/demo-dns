# 命名规范（Naming Conventions）

## 文件命名

### 文档文件
- 使用 `-` 分隔小写（如 `api-endpoints.md`）
- ADR 文件：`adr-{编号}-{简短描述}.md`（如 `adr-001-laravel.md`）
- 计划文件：`plans-{阶段}.md`（如 `plans-stage-01.md`）

### 代码文件
- PHP 类：`PascalCase.php`（如 `ProfileController.php`）
- Vue 组件：`PascalCase.vue`（如 `ProfileList.vue`）
- Trait：`PascalCase.php`（如 `DeviceTrait.php`）
- Interface：`IPascalCase.php`（如 `ICache.php`）
- Enum：`PascalCase.php`（如 `ProfileStatus.php`）

## 变量命名

### PHP
| 类型 | 规范 | 示例 |
|------|------|------|
| 普通变量 | `$camelCase` | `$profileList` |
| 类私有属性 | `$_camelCase` | `$_cachePool` |
| 常量 | `SCREAMING_SNAKE_CASE` | `MAX_RETRY_COUNT` |
| 布尔变量 | `is/has/can` 前缀 | `$isValid`、`$hasPermission` |
| 数组/集合 | 复数名词 | `$profiles`、`$users` |

### JavaScript/Vue
| 类型 | 规范 | 示例 |
|------|------|------|
| 普通变量 | `camelCase` | `profileList` |
| 常量 | `SCREAMING_SNAKE_CASE` | `MAX_PROFILE_COUNT` |
| 组件 ref | `{name}Ref` | `profileListRef` |
| 布尔变量 | `is/has/can` 前缀 | `isLoading`、`hasError` |

## 函数/方法命名

### 动宾结构
| 操作 | 命名 |
|------|------|
| 获取单个 | `get{名词}` | `getProfile`、`getUserById` |
| 获取多个 | `get{名词}List` | `getProfileList` |
| 创建 | `create{名词}` | `createProfile` |
| 更新 | `update{名词}` | `updateProfile` |
| 删除 | `delete{名词}` | `deleteProfile` |
| 检查 | `check{名词}` / `is{状态}` | `checkProfileValid`、`isValid` |
| 验证 | `validate{名词}` | `validateInput` |

## 数据库表/字段命名

### 表名
- 使用单数名词
- 小写下划线分隔
- 示例：`profile`、`api_log`、`device`

### 字段名
- 小写下划线分隔
- 时间字段：`{action}_at`（如 `created_at`、`updated_at`）
- 外键：`{table_singular}_id`（如 `user_id`、`profile_id`）
- 布尔字段：`is_{状态}` 或 `has_{特性}`（如 `is_valid`、`is_active`）

## API 命名

### 端点
- RESTful 风格
- 资源用复数名词
- 示例：`/api/v1/profiles`、`/api/v1/dns-logs`

### 请求参数
- 小写下划线分隔
- 示例：`profile_id`、`device_id`、`region_code`

## 目录命名

- 使用小写下划线或小写连字符
- 推荐小写连字符：`project-doc`、`api-endpoints`
- prompts 目录保持小写
