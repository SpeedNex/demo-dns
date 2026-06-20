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
        'node_id',
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

    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class, 'target_node_id');
    }

    public function getNodeIdAttribute(): ?int
    {
        return isset($this->attributes['target_node_id']) ? (int) $this->attributes['target_node_id'] : null;
    }

    public function setNodeIdAttribute(string|int|null $value): void
    {
        $this->attributes['target_node_id'] = $value !== null ? (int) $value : null;
    }
}
