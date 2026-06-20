<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConfigVersion extends Model
{
    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'version',
        'target_scope',
        'target_node_id',
        'target_profile_id',
        'config_json',
        'checksum',
        'published_by',
        'published_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'config_json' => 'array',
            'published_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function publishTasks(): HasMany
    {
        return $this->hasMany(PublishTask::class);
    }
}
