<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NavigationCatalog extends Model
{
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = ['key', 'label_key', 'parent_key', 'group_key', 'path', 'icon', 'sort_order', 'visible'];

    protected function casts(): array
    {
        return [
            'visible' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_key', 'key');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_key', 'key');
    }
}
