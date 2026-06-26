<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfileRule extends BaseModel
{
    protected $table = 'profile_rules';

    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'profile_id',
        'list_type',
        'match_type',
        'domain',
        'normalized_domain',
        'action',
        'category',
        'enabled',
        'note',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
        ];
    }

    public function setListTypeAttribute(?string $value): void
    {
        $this->attributes['list_type'] = match ($value) {
            'allow' => 'allowlist',
            'block' => 'blocklist',
            default => $value,
        };
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }
}
