<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemConfig extends Model
{
    protected $table = 'system_configs';

    public $incrementing = true;

    protected $primaryKey = 'id';

    protected $keyType = 'int';

    protected $fillable = [
        'config_key',
        'config_value',
        'description',
        'is_secret',
    ];

    protected function casts(): array
    {
        return [
            'config_value' => 'array',
            'is_secret' => 'boolean',
        ];
    }

    /**
     * Alias for config_key — preserves $row->key access patterns
     * from the legacy code path.
     */
    public function getKeyAttribute(): ?string
    {
        return $this->attributes['config_key'] ?? null;
    }

    /**
     * Alias for config_value — preserves $row->value access patterns
     * from the legacy code path.
     */
    public function getValueAttribute(): mixed
    {
        return $this->attributes['config_value'] ?? null;
    }
}
