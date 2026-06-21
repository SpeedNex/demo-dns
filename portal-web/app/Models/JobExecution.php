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

    // 2026-06-22 NEW P0#N1: 迁移用 started_at/finished_at 代替 created_at/updated_at。
    // 关闭 Eloquent 自动 timestamps，否则 INSERT 会写 created_at/updated_at → 1054 Column not found。
    public $timestamps = false;

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
