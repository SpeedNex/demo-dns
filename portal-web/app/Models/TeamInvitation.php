<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamInvitation extends Model
{
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'team_id',
        'email',
        'role_key',
        'role',
        'token_hash',
        'inviter_id',
        'invited_by',
        'expires_at',
        'accepted_at',
        'declined_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'declined_at' => 'datetime',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inviter_id');
    }

    // 数据库列是 role_key，对外 API 暴露为 role
    public function getRoleAttribute(): ?string
    {
        return $this->attributes['role_key'] ?? null;
    }

    public function setRoleAttribute(?string $value): void
    {
        $this->attributes['role_key'] = $value;
    }

    public function getInvitedByAttribute(): ?int
    {
        return isset($this->attributes['inviter_id']) ? (int) $this->attributes['inviter_id'] : null;
    }

    public function setInvitedByAttribute(string|int|null $value): void
    {
        $this->attributes['inviter_id'] = $value !== null ? (int) $value : null;
    }
}
