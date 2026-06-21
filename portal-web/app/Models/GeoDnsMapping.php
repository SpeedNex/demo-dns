<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeoDnsMapping extends Model
{
    protected $table = 'geo_dns_mappings';

    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'domain',
        'country',
        'region',
        'target_node_id',
        'node_name',
        'public_ipv4',
        'node_alias',
        'target_endpoint',
        'priority',
        'weight',
        'enabled',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
        ];
    }

    public function fill(array $attributes)
    {
        if (array_key_exists('node_id', $attributes) && ! array_key_exists('target_node_id', $attributes)) {
            $attributes['target_node_id'] = $attributes['node_id'];
        }

        unset($attributes['node_id']);

        if (empty($attributes['domain'])) {
            $attributes['domain'] = 'resolver.ocerlink.com';
        }

        return parent::fill($attributes);
    }

    protected static function booted(): void
    {
        static::creating(function (self $mapping): void {
            if (empty($mapping->domain)) {
                $mapping->domain = 'resolver.ocerlink.com';
            }
        });
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class, 'target_node_id');
    }

    public function getNodeIdAttribute(): string|int|null
    {
        return $this->attributes['target_node_id'] ?? null;
    }

    public function setNodeIdAttribute(string|int|null $value): void
    {
        $this->attributes['target_node_id'] = $value;
    }
}
