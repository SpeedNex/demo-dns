<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Node extends Model
{
    protected static function booted(): void
    {
        static::creating(function (self $node): void {
            if (blank($node->node_code)) {
                $node->node_code = 'nd_' . strtolower(\Illuminate\Support\Str::random(10));
            }
        });
    }

    protected $table = 'nodes';
    protected $fillable = [
        'node_code', 'name', 'region', 'country', 'city',
        'public_ipv4', 'public_ipv6', 'supported_protocols',
        'status', 'desired_config_version', 'current_config_version',
        'last_heartbeat_at', 'last_log_flush_at', 'meta', 'created_by_admin_id',
        'node_name',
    ];
    protected $casts = [
        'supported_protocols' => 'array',
        'meta' => 'array',
        'last_heartbeat_at' => 'datetime',
        'last_log_flush_at' => 'datetime',
        'desired_config_version' => 'integer',
        'current_config_version' => 'integer',
    ];

    public function getRouteKeyName(): string
    {
        return 'node_code';
    }

    public function getNodeNameAttribute(): ?string
    {
        return $this->attributes['name'] ?? null;
    }

    public function setNodeNameAttribute(?string $value): void
    {
        $this->attributes['name'] = $value;
    }

    public function tokens(): HasMany
    {
        return $this->hasMany(NodeToken::class, 'node_id');
    }

    public function heartbeats(): HasMany
    {
        return $this->hasMany(NodeHeartbeat::class, 'node_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }
}
