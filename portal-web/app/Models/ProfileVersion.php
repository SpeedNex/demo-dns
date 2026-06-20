<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfileVersion extends Model
{
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'profile_id',
        'version',
        'status',
        'checksum',
        'config_json',
        'rule_count',
        'message',
        'published_by',
        'external_publish_id',
        'published_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'config_json' => 'array',
            'rule_count' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }
}
