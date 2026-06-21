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

    public function fill(array $attributes)
    {
        if (isset($attributes['profile_id']) && ! isset($attributes['target_profile_id'])) {
            $attributes['target_profile_id'] = $attributes['profile_id'];
        }

        if (isset($attributes['generated_at']) && ! isset($attributes['created_at'])) {
            $attributes['created_at'] = $attributes['generated_at'];
        }

        unset($attributes['profile_id'], $attributes['profile_version'], $attributes['generated_at']);

        if (isset($attributes['id']) && ! is_numeric((string) $attributes['id'])) {
            unset($attributes['id']);
        }

        if (empty($attributes['target_scope'])) {
            $attributes['target_scope'] = isset($attributes['target_profile_id']) ? 'profile' : 'global';
        }

        return parent::fill($attributes);
    }

    public function getProfileIdAttribute(): mixed
    {
        return $this->attributes['target_profile_id'] ?? null;
    }

    public function publishTasks(): HasMany
    {
        return $this->hasMany(PublishTask::class);
    }
}
