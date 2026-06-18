<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdminMenuRule extends Model
{
    protected $table = 'admin_menu_rule';

    protected $fillable = [
        'menu_key',
        'parent_key',
        'title_key',
        'path',
        'icon',
        'sort_order',
        'visible',
        'permission_code',
        'group_key',
    ];

    protected $casts = [
        'visible' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function children(): HasMany
    {
        return $this->hasMany(AdminMenuRule::class, 'parent_key', 'menu_key')
            ->orderBy('sort_order');
    }

    public function parent()
    {
        return $this->belongsTo(AdminMenuRule::class, 'parent_key', 'menu_key');
    }

    public function scopeRoots($query)
    {
        return $query->whereNull('parent_key')->orderBy('sort_order');
    }

    public function scopeVisible($query)
    {
        return $query->where('visible', true);
    }

    public function scopeByGroup($query, string $group)
    {
        return $query->where('group_key', $group);
    }
}
