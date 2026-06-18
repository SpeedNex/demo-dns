<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * UI.md #70 — 账单周期。
 */
class BillingPeriod extends Model
{
    public const STATUS_OPEN = 'open';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_BILLED = 'billed';

    protected $fillable = [
        'user_id',
        'period_start',
        'period_end',
        'status',
        'billing_id',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'datetime',
            'period_end' => 'datetime',
        ];
    }
}
