<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PublishTask extends Model
{
    

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'config_version_id',
        'profile_id',
        'status',
        'target_scope',
        'target_filter',
        'target_node_count',
        'applied_node_count',
        'failed_node_count',
        'retry_count',
        'message',
        'latest_error',
        'queued_at',
        'started_at',
        'completed_at',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $task): void {
            if ($task->id === null || $task->id === '') {
                $task->id = 'pub_' . substr(hash('sha256', microtime(true) . random_int(1, PHP_INT_MAX)), 0, 12);
            }

            if ($task->target_filter === null) {
                $task->target_filter = [];
            }
        });
    }

    protected function casts(): array
    {
        return [
            'target_filter' => 'array',
            'queued_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function configVersion(): BelongsTo
    {
        return $this->belongsTo(ConfigVersion::class);
    }

    public function executions(): HasMany
    {
        return $this->hasMany(TaskExecution::class);
    }
}
