<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QueryLogIngestBatch extends Model
{
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'batch_id',
        'node_id',
        'event_count',
        'status',
        'error_message',
        'forwarded_to_clickhouse',
        'received_at',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'event_count' => 'integer',
            'forwarded_to_clickhouse' => 'boolean',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }
}
