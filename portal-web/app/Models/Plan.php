<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends BaseModel
{
    protected $fillable = [
        'code',
        'name',
        'description',
        'status',
        'sort_order',
        'is_featured',
        'badge',
        'features',
        'limits',
    ];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'limits' => 'array',
            'is_featured' => 'boolean',
        ];
    }

    public function prices(): HasMany
    {
        return $this->hasMany(PlanPrice::class)->orderBy('amount_minor');
    }
}
