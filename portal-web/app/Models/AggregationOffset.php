<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * UI.md #68 — 聚合偏移量。
 */
class AggregationOffset extends Model
{
    public const STATUS_IDLE = 'idle';
    public const STATUS_RUNNING = 'running';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'job_type',
        'last_processed_at',
        'last_processed_id',
        'status',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'last_processed_at' => 'datetime',
            'meta' => 'array',
        ];
    }
}
