<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminAuditLog extends Model
{
    // 表名走 Laravel 默认（snake_case 复数） + config/database.php 的 prefix
    // 不要硬编码表名，否则前缀配置会失效
    protected $table = 'admin_audit_logs';

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
        // UI.md P0#6: 当 actorId 是数字时（真实 admin），从 Admin 表反查 username；
        // 当 actorId 是字符串（如 'system'、'cron'）时，actor_admin_id 留 null，
        // 把字符串填到 actor_username，避免 (int)'system' = 0 误伪造超级管理员。
        $isNumericActor = is_int($actorId) || (is_string($actorId) && ctype_digit($actorId));

        if ($actorUsername === null && $isNumericActor) {
            $admin = \App\Models\Admin::find($actorId);
            $actorUsername = $admin?->username;
        }

        return self::create([
            'actor_admin_id' => $isNumericActor ? (int) $actorId : null,
            'actor_username' => $actorUsername ?? (is_string($actorId) ? $actorId : null),
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
