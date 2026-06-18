<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * UI.md #73 — 套餐功能矩阵。
 */
class PlanFeature extends Model
{
    protected $table = 'plan_features';

    protected $fillable = [
        'plan_code',
        'features',
        'monthly_query_limit',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
