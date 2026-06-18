<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * UI.md #51 — Order model.
 *
 * Order 状态机: pending → paid → cancelled → refunded
 * 业务层禁止直接修改 status，必须走 OrderService。
 */
class Order extends Model
{
    protected $table = 'orders';

    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REFUNDED = 'refunded';

    protected $fillable = [
        'user_id',
        'order_no',
        'plan_code',
        'status',
        'payable_amount_minor',
        'currency',
        'description',
        'meta',
        'paid_at',
        'cancelled_at',
    ];

    protected $casts = [
        'payable_amount_minor' => 'int',
        'meta' => 'array',
        'paid_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];
}
