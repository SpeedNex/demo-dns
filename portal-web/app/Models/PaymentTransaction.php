<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * UI.md #53 — Payment transaction log.
 * 状态机: pending → success → failed → refunded
 */
class PaymentTransaction extends Model
{
    protected $table = 'payment_transactions';

    public const STATUS_PENDING = 'pending';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';
    public const STATUS_REFUNDED = 'refunded';

    protected $fillable = [
        'user_id',
        'order_id',
        'provider',
        'provider_session_id',
        'provider_payment_intent_id',
        'status',
        'amount_minor',
        'currency',
        'meta',
        'completed_at',
    ];

    protected $casts = [
        'amount_minor' => 'int',
        'meta' => 'array',
        'completed_at' => 'datetime',
    ];
}
