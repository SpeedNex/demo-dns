<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * UI.md #67 — 聚合后的 usage 记录。
 */
class UsageRecord extends Model
{
    protected $fillable = [
        'user_id',
        'profile_id',
        'device_id',
        'billing_category',
        'period_start',
        'period_end',
        'query_count',
        'amount_minor',
        'billing_period_id',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'datetime',
            'period_end' => 'datetime',
        ];
    }
}
