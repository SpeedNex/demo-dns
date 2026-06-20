<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskExecution extends Model
{
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'publish_task_id',
        'node_id',
        'config_version',
        'status',
        'checksum',
        'error_code',
        'error_message',
        'pulled_at',
        'applied_at',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'pulled_at' => 'datetime',
            'applied_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function publishTask(): BelongsTo
    {
        return $this->belongsTo(PublishTask::class);
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }
}
