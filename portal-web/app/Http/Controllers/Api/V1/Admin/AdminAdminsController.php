<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\Admin;
use App\Models\AdminAuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

/**
 * Admin 账号管理（区别于用户的 User 管理）
 * 提供 list / create / update / delete / enable / disable 完整 CRUD。
 */
final class AdminAdminsController
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 20);
        $perPage = max(1, min($perPage, 100));

        $query = Admin::query()
            ->select(['admin_id', 'username', 'email', 'status', 'is_super', 'last_login_at', 'created_at']);

        if ($username = trim((string) $request->input('username', ''))) {
            $query->where('username', 'like', '%' . $username . '%');
        }
        if ($email = trim((string) $request->input('email', ''))) {
            $query->where('email', 'like', '%' . $email . '%');
        }
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $paginator = $query->orderByDesc('created_at')->paginate($perPage);

        $items = collect($paginator->items())->map(function (Admin $admin) {
            $roleList = DB::table('admin_user_roles as ur')
                ->join('admin_roles as r', 'r.id', '=', 'ur.admin_role_id')
                ->where('ur.admin_id', $admin->admin_id)
                ->select(['r.id', 'r.code', 'r.name'])
                ->get()
                ->map(fn ($r) => ['id' => (int) $r->id, 'code' => $r->code, 'name' => $r->name])
                ->all();

            return [
                'id' => (int) $admin->admin_id,
                'username' => $admin->username,
                'email' => $admin->email,
                'status' => $admin->status ?: 'active',
                'is_super_admin' => (bool) $admin->is_super,
                'last_login_at' => $admin->last_login_at,
                'created_at' => $admin->created_at,
                'role_list' => $roleList,
            ];
        })->values();

        return response()->json([
            'data' => $items,
            'meta' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'page' => $paginator->currentPage(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $actorId = $request->user()?->admin_id;

        $validator = Validator::make($request->all(), [
            'username' => 'required|string|max:100|unique:admins,username',
            'email' => 'required|email|max:191|unique:admins,email',
            'password' => 'required|string|min:8',
            'status' => 'sometimes|in:active,disabled',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => [
                    'code' => 'VALIDATION_FAILED',
                    'message' => $validator->errors()->first(),
                    'details' => $validator->errors()->toArray(),
                ],
            ], 422);
        }

        $admin = Admin::create([
            'username' => $request->input('username'),
            'email' => $request->input('email'),
            'password' => Hash::make($request->input('password')),
            'status' => $request->input('status', 'active'),
            'is_super' => false,
        ]);

        AdminAuditLog::record(
            'admin.create',
            'admin',
            $admin->admin_id,
            ['email' => $admin->email],
            $actorId,
            null,
            $request->ip(),
            $request->userAgent(),
        );

        return response()->json(['data' => [
            'id' => (int) $admin->admin_id,
            'username' => $admin->username,
            'email' => $admin->email,
            'status' => $admin->status,
            'is_super_admin' => (bool) $admin->is_super,
        ]], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $actorId = $request->user()?->admin_id;
        $admin = Admin::find((int) $id);
        if (! $admin) {
            return response()->json(['message' => 'Admin not found.'], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'sometimes|in:active,disabled',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => [
                    'code' => 'VALIDATION_FAILED',
                    'message' => $validator->errors()->first(),
                ],
            ], 422);
        }

        $dirty = [];
        if ($request->has('status')) {
            $admin->status = $request->input('status');
            $dirty['status'] = $admin->status;
        }
        $admin->save();

        AdminAuditLog::record(
            'admin.update',
            'admin',
            $admin->admin_id,
            array_keys($dirty),
            $actorId,
            null,
            $request->ip(),
            $request->userAgent(),
        );

        return response()->json(['data' => [
            'id' => (int) $admin->admin_id,
            'username' => $admin->username,
            'email' => $admin->email,
            'status' => $admin->status,
        ]]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $actorId = $request->user()?->admin_id;
        $admin = Admin::find((int) $id);
        if (! $admin) {
            return response()->json(['message' => 'Admin not found.'], 404);
        }

        if ((int) $actorId === (int) $admin->admin_id) {
            return response()->json(['message' => 'Cannot delete your own account.'], 422);
        }
        if ($admin->is_super) {
            return response()->json(['message' => 'Cannot delete super admin.'], 422);
        }

        DB::transaction(function () use ($admin) {
            DB::table('admin_user_roles')->where('admin_id', $admin->admin_id)->delete();
            $admin->delete();
        });

        AdminAuditLog::record(
            'admin.delete',
            'admin',
            (int) $id,
            ['email' => $admin->email],
            $actorId,
            null,
            $request->ip(),
            $request->userAgent(),
        );

        return response()->json(['data' => ['deleted' => true]]);
    }
}
