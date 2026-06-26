<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * SaaS 订阅模型。
 * 替代旧的 Order 模型，直接管理订阅生命周期。
 */
class Subscription extends BaseModel
{
    public const STATUS_PENDING   = 'pending';
    public const STATUS_ACTIVE    = 'active';
    public const STATUS_PAST_DUE  = 'past_due';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED   = 'expired';

    public const QUOTA_NORMAL   = 'normal';
    public const QUOTA_EXCEEDED = 'exceeded';

    protected $table = 'subscriptions';

    protected $fillable = [
        'subscription_no',
        'user_id',
        'plan_id',
        'plan_code',
        'billing_cycle',
        'amount_minor',
        'currency',
        'provider',
        'provider_session_id',
        'status',
        'quota_status',
        'auto_renew',
        'cancel_at_period_end',
        'started_at',
        'current_period_start',
        'current_period_end',
        'cancelled_at',
        'expired_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'int',
            'auto_renew' => 'bool',
            'cancel_at_period_end' => 'bool',
            'started_at' => 'datetime',
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'cancelled_at' => 'datetime',
            'expired_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    public function paymentTransactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class, 'subscription_id');
    }
}