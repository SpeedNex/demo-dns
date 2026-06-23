<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NodeHeartbeat extends Model
{
    protected $table = 'resolver_node_heartbeats';

    protected $fillable = [
        'node_id',
        'status',
        'uptime_seconds',
        'version',
        'current_config_version',
        'profiles_loaded',
        'last_config_pull_at',
        'last_log_flush_at',
        'reported_at',
        'created_at',
    ];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'last_config_pull_at' => 'datetime',
            'last_log_flush_at' => 'datetime',
            'reported_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }
}
