<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminAuditLog extends Model
{
    // 表名走 Laravel 默认（snake_case 复数） + config/database.php 的 prefix
    // 不要硬编码表名，否则前缀配置会失效

    public $timestamps = false;

    protected $fillable = [
        'actor_admin_id',
        'actor_username',
        'action',
        'target_type',
        'target_id',
        'ip',
        'user_agent',
        'payload',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public static function record(string $action, ?string $targetType = null, string|int|null $targetId = null, array $payload = [], string|int|null $actorId = null, ?string $actorUsername = null, ?string $ip = null, ?string $userAgent = null): self
    {
        // 如果没有提供 actorUsername 但有 actorId，自动从 Admin 表查询
        if ($actorUsername === null && $actorId !== null) {
            $admin = \App\Models\Admin::find($actorId);
            $actorUsername = $admin?->username;
        }

        return self::create([
            'actor_admin_id' => $actorId !== null ? (int) $actorId : null,
            'actor_username' => $actorUsername,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId !== null ? (int) $targetId : null,
            'ip' => $ip,
            'user_agent' => $userAgent,
            'payload' => $payload ?: null,
            'created_at' => now(),
        ]);
    }
}
