<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Alert extends Model
{
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'code',
        'level',
        'source',
        'subject_type',
        'subject_id',
        'title',
        'message',
        'payload',
        'status',
        'acknowledged_by',
        'acknowledged_at',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'subject_id' => 'integer',
            'acknowledged_by' => 'integer',
            'acknowledged_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }
}
