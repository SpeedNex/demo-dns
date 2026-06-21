<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RuleSource extends Model
{
    protected $table = 'rule_sources';

    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'code',
        'name',
        'url',
        'format',
        'category',
        'enabled',
        'item_count',
        'last_sync_at',
        'last_sync_status',
        'last_sync_message',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'last_sync_at' => 'datetime',
        ];
    }

    public function fill(array $attributes)
    {
        if (isset($attributes['type']) && ! isset($attributes['format'])) {
            $attributes['format'] = $this->mapTypeToFormat((string) $attributes['type']);
        }

        if (isset($attributes['rule_count']) && ! isset($attributes['item_count'])) {
            $attributes['item_count'] = $attributes['rule_count'];
        }

        if (isset($attributes['last_synced_at']) && ! isset($attributes['last_sync_at'])) {
            $attributes['last_sync_at'] = $attributes['last_synced_at'];
        }

        unset($attributes['type'], $attributes['rule_count'], $attributes['last_synced_at']);

        if (empty($attributes['code'])) {
            $attributes['code'] = 'rs_' . substr(hash('sha256', microtime(true) . random_int(1, PHP_INT_MAX)), 0, 12);
        }

        if (empty($attributes['category'])) {
            $attributes['category'] = 'custom';
        }

        return parent::fill($attributes);
    }

    protected static function booted(): void
    {
        static::creating(function (self $source): void {
            if (empty($source->code)) {
                $source->code = 'rs_' . substr(hash('sha256', microtime(true) . random_int(1, PHP_INT_MAX)), 0, 12);
            }

            if (empty($source->format)) {
                $source->format = 'domains';
            }

            if (empty($source->category)) {
                $source->category = 'custom';
            }
        });
    }

    public function getTypeAttribute(): string
    {
        return match ($this->attributes['format'] ?? 'domains') {
            'domains' => 'domain_list',
            default => (string) ($this->attributes['format'] ?? 'domain_list'),
        };
    }

    public function getRuleCountAttribute(): int
    {
        return (int) ($this->attributes['item_count'] ?? 0);
    }

    public function getLastSyncedAtAttribute(): mixed
    {
        return $this->last_sync_at;
    }

    private function mapTypeToFormat(string $type): string
    {
        return match ($type) {
            'domain_list' => 'domains',
            'rpz' => 'json',
            default => $type,
        };
    }
}
