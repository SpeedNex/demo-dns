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
        'name',
        'type',
        'url',
        'enabled',
        'rule_count',
        'last_synced_at',
        'last_sync_status',
        'last_sync_message',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'last_synced_at' => 'datetime',
        ];
    }
}
