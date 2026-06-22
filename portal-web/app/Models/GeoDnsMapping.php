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
        return $this->belongsTo(GeoDnsNode::class, 'target_node_id');
    }

    public function getNodeIdAttribute(): string|int|null
    {
        return $this->attributes['target_node_id'] ?? null;
    }

    public function setNodeIdAttribute(string|int|null $value): void
    {
        $this->attributes['target_node_id'] = $value;
    }

    /**
     * 2026-06-22: 单一事实源 — mapping 的"在线/离线"完全由关联节点的 last_heartbeat_at 决定，
     * 没有关联节点时是 not_installed。
     */
    public function runtimeStatus(): string
    {
        if (! $this->target_node_id) {
            return 'not_installed';
        }
        return $this->node?->runtimeStatus() ?? 'offline';
    }
}
