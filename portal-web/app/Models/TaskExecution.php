<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class TaskExecution extends Model
{
    protected $table = 'task_executions';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
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

    protected static function booted(): void
    {
        static::creating(function (self $execution): void {
            if (! is_string($execution->id) || $execution->id === '') {
                $execution->id = (string) Str::ulid();
            }
        });
    }

    public function publishTask(): BelongsTo
    {
        return $this->belongsTo(PublishTask::class, 'publish_task_id');
    }

    public function task(): BelongsTo
    {
        return $this->publishTask();
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class, 'node_id');
    }
}
