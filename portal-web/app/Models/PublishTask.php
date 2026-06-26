<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PublishTask extends Model
{
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'profile_version_id',
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

    protected function casts(): array
    {
        return [
            'target_filter' => 'array',
            'queued_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function profileVersion(): BelongsTo
    {
        return $this->belongsTo(ProfileVersion::class);
    }

    public function executions(): HasMany
    {
        return $this->hasMany(TaskExecution::class);
    }
}
