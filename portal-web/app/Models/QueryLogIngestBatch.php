<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QueryLogIngestBatch extends Model
{
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'batch_id',
        'node_id',
        'item_count',
        'event_count',
        'status',
        'error_message',
        'forwarded_to_clickhouse',
        'raw_payload',
        'received_at',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'item_count' => 'integer',
            'event_count' => 'integer',
            'forwarded_to_clickhouse' => 'boolean',
            'raw_payload' => 'array',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }
}
