<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 所有业务 Model 的基类。
 *
 * 设计原则：Eloquent Model 使用"逻辑表名"（无前缀），
 * Laravel 数据库连接的 prefix 配置（来自 DB_TABLE_PREFIX 环境变量）会在 SQL 构建时自动拼接。
 * 例如：Plan::$table = 'plans' → 连接层 prefix 'dns_' → 实际表名 'dns_plans'。
 *
 * 子类只需设置 $table 为逻辑表名，无需关心前缀。
 */
abstract class BaseModel extends Model
{
}
