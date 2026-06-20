<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// 软删除已迁移到数据库层移除，如需恢复请添加：use Illuminate\Database\Eloquent\SoftDeletes;

class TeamMember extends Model
{
    // SoftDeletes 已移除：成员退出直接 DELETE

    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'team_id',
        'user_id',
        'role_key',
        'role',
        'joined_at',
    ];

    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // 数据库列是 role_key，对外 API 暴露为 role（兼容 TeamController / TeamService 旧字段）
    public function getRoleAttribute(): ?string
    {
        return $this->attributes['role_key'] ?? null;
    }

    public function setRoleAttribute(?string $value): void
    {
        $this->attributes['role_key'] = $value;
    }
}
