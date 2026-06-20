<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdminRole extends Model
{
    // 表名走默认 + config/database.php 的 `prefix`。

    protected $fillable = ["code","name","description","is_system","status"];

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            AdminPermission::class,
            // pivot 表走默认 + config/database.php 的 `prefix`
            "admin_role_permissions",
            "admin_role_id",
            "admin_permission_id"
        );
    }

    public function navRules(): HasMany
    {
        return $this->hasMany(AdminRoleNavRule::class, "admin_role_id");
    }

    public function admins(): BelongsToMany
    {
        return $this->belongsToMany(
            Admin::class,
            // pivot 表走默认 + config/database.php 的 `prefix`
            "admin_user_roles",
            "admin_role_id",
            "admin_id"
        )->withPivot("assigned_by","assigned_at");
    }
}
