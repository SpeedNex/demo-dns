<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * UI.md #63 — Policy Snapshot。
 */
class PolicySnapshot extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';

    protected $fillable = [
        'user_id',
        'version',
        'payload_json',
        'status',
        'published_at',
        'published_by',
    ];

    protected function casts(): array
    {
        return [
            'payload_json' => 'array',
            'published_at' => 'datetime',
        ];
    }
}
