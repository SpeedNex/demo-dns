<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * UI.md #57 — 账单明细。
 */
class BillingItem extends Model
{
    public const ITEM_TYPE_PLAN = 'plan';
    public const ITEM_TYPE_USAGE = 'usage';
    public const ITEM_TYPE_ADJUSTMENT = 'adjustment';

    protected $fillable = [
        'billing_id',
        'item_type',
        'item_name',
        'quantity',
        'unit_price_minor',
        'amount_minor',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }
}
