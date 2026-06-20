<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Device extends Model
{
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'user_id',
        'profile_id',
        'device_uid',
        'name',
        'fingerprint',
        'source',
        'protocol',
        'user_agent',
        'sni',
        'ip_hash',
        'country',
        'first_seen_at',
        'last_seen_at',
        'last_query_at',
        'query_count',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'last_query_at' => 'datetime',
            'query_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }
}
