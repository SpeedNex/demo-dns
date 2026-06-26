<?php

namespace App\Domain\Auth;

use App\Models\Admin;
use App\Models\AdminPermission;
use App\Models\AdminRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class PermissionService
{
    /**
     * Check if the given actor (User or Admin) has a specific permission.
     *
     * Admin actors are dispatched to {@see self::hasAdminPermission()} so the
     * User permission tables are never touched on admin API calls. Super
     * admins short-circuit and always pass.
     */
    public function hasPermission(User|Admin $user, string $permissionCode): bool
    {
        if ($user instanceof Admin) {
            return $this->hasAdminPermission($user, $permissionCode);
        }

        // V2.3: User 端 RBAC 已下线，所有会员侧 API 仅按 auth + plan 判定
        return true;
    }

    /**
     * Check if a user has a specific role.
     */
    public function hasRole(User $user, string $role): bool
    {
        // V2.3: User.role 字段已移除，会员侧不再使用 role 概念
        return false;
    }

    /**
     * Assign a role to a user.
     */
    public function assignRole(User $user, string $role): void
    {
        // V2.3: User.role 字段已移除，本方法保留为 no-op 兼容旧调用方
    }

    /**
     * Get all permissions for a user's role.
     *
     * @return array<int, string>
     */
    public function getUserPermissions(User $user): array
    {
        // V2.3: User 端 RBAC 已下线，返回所有 AdminPermission 不可取（user 视角无意义）
        return [];
    }

    /**
     * Check if an admin has a specific admin permission code.
     *
     * Looks up the permission through the admin's assigned roles. Super
     * admins always pass. Inactive admins are denied regardless of role.
     */
    public function hasAdminPermission(Admin $admin, string $permissionCode): bool
    {
        if (! $this->isActiveAdmin($admin)) {
            return false;
        }

        if ((int) $admin->is_super === 1) {
            return true;
        }

        return AdminRole::query()
            ->whereHas('admins', function ($query) use ($admin): void {
                $query->where('admin_user_roles.admin_id', $admin->admin_id);
            })
            ->whereHas('permissions', function ($query) use ($permissionCode): void {
                $query->where('admin_permissions.code', $permissionCode);
            })
            ->exists();
    }

    /**
     * Get all permission codes assigned to an admin via their roles.
     *
     * @return array<int, string>
     */
    public function getAdminPermissions(Admin $admin): array
    {
        if (! $this->isActiveAdmin($admin)) {
            return [];
        }

        if ((int) $admin->is_super === 1) {
            return \App\Models\AdminPermission::pluck('code')->toArray();
        }

        return AdminRole::query()
            ->whereHas('admins', function ($query) use ($admin): void {
                $query->where('admin_user_roles.admin_id', $admin->admin_id);
            })
            ->with('permissions:id,code')
            ->get()
            ->pluck('permissions')
            ->flatten()
            ->pluck('code')
            ->unique()
            ->values()
            ->all();
    }

    private function isActiveAdmin(Admin $admin): bool
    {
        return $admin->status === 'active';
    }

    /**
     * Seed default admin permissions and assign them to the super admin role.
     */
    public static function seedDefaults(): void
    {
        $permissions = [
            ['code' => 'admin.access', 'name' => 'Admin Access', 'description' => 'Access admin panel', 'group_name' => 'admin'],
            ['code' => 'users.manage', 'name' => 'Manage Users', 'description' => 'View and manage users', 'group_name' => 'admin'],
            ['code' => 'teams.manage', 'name' => 'Manage Teams', 'description' => 'View and manage all teams', 'group_name' => 'admin'],
            ['code' => 'audit.view', 'name' => 'View Audit Logs', 'description' => 'View audit logs', 'group_name' => 'admin'],
            ['code' => 'plans.manage', 'name' => 'Manage Plans', 'description' => 'CRUD plans and prices', 'group_name' => 'admin'],
            ['code' => 'subscriptions.view', 'name' => 'View Subscriptions', 'description' => 'View subscriptions and bills', 'group_name' => 'admin'],
        ];

        foreach ($permissions as $perm) {
            // V2.3: 改用 AdminPermission 表 (admin_permissions)，不再写 user 端 RBAC
            AdminPermission::firstOrCreate(
                ['code' => $perm['code']],
                [
                    'resource'    => $perm['group_name'],
                    'action'      => strtok($perm['code'], '.') ?: 'manage',
                    'description' => $perm['description'],
                ]
            );
        }
    }
}
