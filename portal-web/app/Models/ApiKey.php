<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiKey extends Model
{
    protected $table = 'api_keys';

    protected $fillable = [
        'user_id',
        'name',
        'key_hash',
        'key_prefix',
        'status',
        'scopes',
        'expires_at',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'scopes' => 'array',
            'expires_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $apiKey): void {
            if ($apiKey->scopes === null) {
                $apiKey->scopes = ['dns:read'];
            }

            if (blank($apiKey->status)) {
                $apiKey->status = 'active';
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
