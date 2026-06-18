<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * UI.md #83 — Job 执行记录。
 */
class JobExecution extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_RUNNING = 'running';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';

    public const FAILURE_THRESHOLD = 3;

    protected $fillable = [
        'job_type',
        'status',
        'started_at',
        'finished_at',
        'duration_ms',
        'error_message',
        'consecutive_failures',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
