<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REFUNDED = 'refunded';

    protected $table = 'orders';
    protected $fillable = [
        'order_no', 'user_id', 'plan_id', 'plan_price_id', 'billing_cycle',
        'currency', 'plan_code_snapshot',
        'original_amount_minor', 'discount_amount_minor', 'payable_amount_minor',
        'idempotency_key', 'status', 'provider', 'paid_at', 'cancelled_at',
        'refunded_at', 'meta',
    ];
    protected $casts = [
        'original_amount_minor' => 'integer',
        'discount_amount_minor' => 'integer',
        'payable_amount_minor' => 'integer',
        'paid_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'refunded_at' => 'datetime',
        'meta' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    public function planPrice(): BelongsTo
    {
        return $this->belongsTo(PlanPrice::class, 'plan_price_id');
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class, 'order_id');
    }

    public function paymentTransactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class, 'order_id');
    }
}
