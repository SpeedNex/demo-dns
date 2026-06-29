<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\Admin;
use App\Models\AdminRole;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Admin RBAC management controller.
 * Manages roles, permissions, and admin-role assignments.
 */
final class AdminRbacController
{
    public function roles(): JsonResponse
    {
        $roles = DB::table('admin_roles as r')
            ->select(['r.id', 'r.code', 'r.name', 'r.description', 'r.is_system', 'r.status', 'r.created_at'])
            ->orderBy('r.created_at', 'desc')
            ->get();

        return response()->json(['data' => $roles]);
    }

    public function permissions(): JsonResponse
    {
        $permissions = DB::table('admin_permissions as p')
            ->select(['p.id', 'p.code', 'p.resource', 'p.action', 'p.description', 'p.created_at'])
            ->orderBy('p.resource')
            ->orderBy('p.action')
            ->get();

        return response()->json(['data' => $permissions]);
    }

    public function rolePermissions(string $id): JsonResponse
    {
        $role = AdminRole::find($id);
        if (! $role) {
            return \App\Helpers\ApiResponse::error('NOT_FOUND', 'Role not found.', 404);
        }

        $permissions = DB::table('admin_role_permissions as rp')
            ->join('admin_permissions as p', 'p.id', '=', 'rp.admin_permission_id')
            ->where('rp.admin_role_id', $id)
            ->select(['p.id', 'p.code', 'p.resource', 'p.action', 'p.description'])
            ->get();

        return response()->json(['data' => $permissions]);
    }

    public function admins(Request $request): JsonResponse
    {
        $admins = Admin::query()
            ->select(['admin_id', 'username', 'email', 'status', 'is_super', 'last_login_at', 'created_at'])
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = '%' . (string) $request->input('search') . '%';
                $query->where(function ($inner) use ($search): void {
                    $inner->where('username', 'like', $search)
                        ->orWhere('email', 'like', $search);
                });
            })
            ->orderByDesc('created_at')
            ->get();

        // 一次性加载所有管理员的角色关联，按 admin_id 分组，消除 N+1
        $adminIds = $admins->pluck('admin_id')->all();
        $allRoles = DB::table('admin_user_roles as ur')
            ->join('admin_roles as r', 'r.id', '=', 'ur.admin_role_id')
            ->whereIn('ur.admin_id', $adminIds)
            ->select(['ur.admin_id', 'r.id', 'r.code', 'r.name'])
            ->get()
            ->groupBy('admin_id');

        $admins = $admins
            ->map(function (Admin $admin) use ($allRoles): array {
                $roleList = collect($allRoles->get($admin->admin_id, []))
                    ->map(fn ($r): array => [
                        'id' => (int) $r->id,
                        'code' => (string) $r->code,
                        'name' => (string) $r->name,
                    ])
                    ->all();

                return [
                    'id' => $admin->admin_id,
                    'username' => $admin->username,
                    'email' => $admin->email,
                    'status' => $admin->status,
                    'is_super_admin' => (bool) $admin->is_super,
                    'last_login_at' => $admin->last_login_at,
                    'role_list' => $roleList,
                ];
            })
            ->values();

        return response()->json(['data' => $admins]);
    }

    public function updateRole(Request $request, string $id): JsonResponse
    {
        $role = AdminRole::find($id);
        if (! $role) {
            return response()->json(['message' => 'Role not found.'], 404);
        }

        if ($role->is_system) {
            return \App\Helpers\ApiResponse::error('FORBIDDEN', 'Cannot modify system role.', 422);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'description' => 'sometimes|nullable|string|max:255',
            'status' => 'sometimes|in:active,inactive',
        ]);

        $role->update(array_filter($validated));

        return \App\Helpers\ApiResponse::success($role);
    }

    public function createRole(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'required|string|max:50|unique:admin_roles,code',
            'description' => 'nullable|string|max:255',
        ]);

        $role = AdminRole::create([
            'code' => $validated['code'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?? '',
            'is_system' => false,
            'status' => 'active',
        ]);

        return response()->json(['data' => $role], 201);
    }

    public function deleteRole(string $id): JsonResponse
    {
        $role = AdminRole::find($id);
        if (! $role) {
            return \App\Helpers\ApiResponse::error('NOT_FOUND', 'Role not found.', 404);
        }

        if ($role->is_system) {
            return \App\Helpers\ApiResponse::error('FORBIDDEN', 'Cannot delete system role.', 422);
        }

        DB::table('admin_role_permissions')->where('admin_role_id', $id)->delete();
        DB::table('admin_role_nav_rules')->where('admin_role_id', $id)->delete();
        DB::table('admin_user_roles')->where('admin_role_id', $id)->delete();
        $role->delete();

        return \App\Helpers\ApiResponse::success(['deleted' => true]);
    }

    public function setRolePermissions(Request $request, string $id): JsonResponse
    {
        $role = AdminRole::find($id);
        if (! $role) {
            return \App\Helpers\ApiResponse::error('NOT_FOUND', 'Role not found.', 404);
        }

        $validated = $request->validate([
            'permission_ids' => 'required|array',
            'permission_ids.*' => 'integer|exists:admin_permissions,id',
        ]);

        DB::transaction(function () use ($id, $validated): void {
            DB::table('admin_role_permissions')->where('admin_role_id', $id)->delete();
            foreach ($validated['permission_ids'] as $permissionId) {
                DB::table('admin_role_permissions')->insert([
                    'admin_role_id' => $id,
                    'admin_permission_id' => $permissionId,
                ]);
            }
        });

        return \App\Helpers\ApiResponse::success(['updated' => true]);
    }

    public function setAdminRoles(Request $request, string $adminId): JsonResponse
    {
        $admin = Admin::find($adminId);
        if (! $admin) {
            return \App\Helpers\ApiResponse::error('NOT_FOUND', 'Admin not found.', 404);
        }

        if ($admin->is_super) {
            return \App\Helpers\ApiResponse::error('FORBIDDEN', 'Cannot modify super admin roles.', 422);
        }

        $validated = $request->validate([
            'role_ids' => 'required|array',
            'role_ids.*' => 'integer|exists:admin_roles,id',
        ]);

        DB::transaction(function () use ($adminId, $validated, $request): void {
            DB::table('admin_user_roles')->where('admin_id', $adminId)->delete();
            foreach ($validated['role_ids'] as $roleId) {
                DB::table('admin_user_roles')->insert([
                    'admin_id' => $adminId,
                    'admin_role_id' => $roleId,
                    'assigned_by' => $request->user()?->admin_id,
                    'assigned_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        return \App\Helpers\ApiResponse::success(['updated' => true]);
    }

    /**
     * 角色已配置的菜单规则（nav_key 列表）
     */
    public function menuRules(string $id): JsonResponse
    {
        $role = AdminRole::find($id);
        if (! $role) {
            return \App\Helpers\ApiResponse::error('NOT_FOUND', 'Role not found.', 404);
        }

        $rows = DB::table('admin_role_nav_rules')
            ->where('admin_role_id', $id)
            ->select(['id', 'admin_role_id', 'nav_key', 'visible'])
            ->get();

        return \App\Helpers\ApiResponse::success($rows);
    }

    /**
     * 写入角色的菜单规则（支持 1/2/3 级）
     */
    public function setMenuRules(Request $request, string $id): JsonResponse
    {
        $role = AdminRole::find($id);
        if (! $role) {
            return \App\Helpers\ApiResponse::error('NOT_FOUND', 'Role not found.', 404);
        }
        if ($role->is_system) {
            return \App\Helpers\ApiResponse::error('FORBIDDEN', 'Cannot modify system role.', 422);
        }

        $validated = $request->validate([
            'nav_keys' => 'required|array',
            'nav_keys.*' => 'string|max:100',
        ]);

        DB::transaction(function () use ($id, $validated): void {
            DB::table('admin_role_nav_rules')->where('admin_role_id', $id)->delete();
            $now = now();
            foreach (array_values(array_unique($validated['nav_keys'])) as $navKey) {
                DB::table('admin_role_nav_rules')->insert([
                    'admin_role_id' => (int) $id,
                    'nav_key' => $navKey,
                    'visible' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });

        return \App\Helpers\ApiResponse::success(['updated' => true]);
    }
}
