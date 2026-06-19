<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Node extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'node_name',
        'status',
        'region',
        'country',
        'city',
        'provider',
        'public_ipv4',
        'public_ipv6',
        'hostname',
        'supported_protocols',
        'version',
        'current_config_version',
        'desired_config_version',
        'weight',
        'capacity_qps',
        'approved_at',
        'last_heartbeat_at',
        'labels',
    ];

    protected function casts(): array
    {
        return [
            'supported_protocols' => 'array',
            'labels' => 'array',
            'approved_at' => 'datetime',
            'last_heartbeat_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $node): void {
            if ($node->supported_protocols === null) {
                $node->supported_protocols = ['doh', 'dot'];
            }

            if ($node->labels === null) {
                $node->labels = [];
            }
        });
    }

    public function tokens(): HasMany
    {
        return $this->hasMany(NodeToken::class);
    }

    public function heartbeats(): HasMany
    {
        return $this->hasMany(NodeHeartbeat::class);
    }
}
