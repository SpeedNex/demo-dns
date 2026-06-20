<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * UI.md #57 — 账单明细。
 */
class BillingItem extends Model
{
    public const ITEM_TYPE_PLAN = 'subscription';
    public const ITEM_TYPE_USAGE = 'usage';
    public const ITEM_TYPE_ADJUSTMENT = 'adjustment';

    protected $fillable = [
        'billing_id',
        'item_type',
        'source_type',
        'source_id',
        'description',
        'quantity',
        'unit_price_minor',
        'amount_minor',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_price_minor' => 'integer',
            'amount_minor' => 'integer',
        ];
    }
}
