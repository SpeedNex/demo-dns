<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * UI.md #61 — Resolver 节点注册表。
 */
class ResolverNode extends Model
{
    public const STATUS_ONLINE = 'online';
    public const STATUS_OFFLINE = 'offline';
    public const STATUS_ERROR = 'error';

    protected $fillable = [
        'node_id',
        'node_name',
        'region',
        'policy_version',
        'last_sync_at',
        'status',
        'ip_address',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'last_sync_at' => 'datetime',
        ];
    }
}
