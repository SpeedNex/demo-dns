<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanPrice extends BaseModel
{
    protected $table = 'plan_prices';
    protected $fillable = [
        'plan_id',
        'billing_cycle',
        'currency',
        'amount_minor',
        'original_amount_minor',
        'status',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
