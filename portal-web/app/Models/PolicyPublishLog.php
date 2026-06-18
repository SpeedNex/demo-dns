<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * UI.md #62 — Policy 发布日志。
 */
class PolicyPublishLog extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACKED = 'acked';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'snapshot_id',
        'node_id',
        'status',
        'ack_at',
        'error_message',
    ];

    protected function casts(): array
    {
        return ['ack_at' => 'datetime'];
    }
}
