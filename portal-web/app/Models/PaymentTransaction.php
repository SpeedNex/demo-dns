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

    public const STATUS_PENDING = 'created';
    public const STATUS_SUCCESS = 'succeeded';
    public const STATUS_FAILED = 'failed';
    public const STATUS_REFUNDED = 'refunded';

    protected $fillable = [
        'user_id',
        'order_id',
        'provider',
        'provider_session_id',
        'provider_payment_intent_id',
        'provider_charge_id',
        'status',
        'amount_minor',
        'currency',
        'failure_code',
        'failure_message',
        'raw_payload',
    ];

    protected $casts = [
        'amount_minor' => 'int',
        'raw_payload' => 'array',
    ];
}
