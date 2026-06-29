<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\User;
use App\Models\AdminAuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

final class AdminUserController
{
    public function index(Request $request): JsonResponse
    {
        $query = User::query()
            ->from('users as u')
            ->select([
                'u.uid',
                'u.username',
                'u.email',
                'u.plan_code',
                'u.status',
                'u.current_team_id',
                'u.locale',
                'u.email_verified_at',
                'u.created_at',
                'u.updated_at',
            ]);

        if ($email = $request->input('email')) {
            $query->where('u.email', 'like', "%{$email}%");
        }

        if ($name = $request->input('name', $request->input('username'))) {
            $query->where('u.username', 'like', "%{$name}%");
        }

        if ($planCode = $request->input('plan_code')) {
            $query->where('u.plan_code', $planCode);
        }

        if ($status = $request->input('status')) {
            $query->where('u.status', $status);
        }

        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $allowedSorts = ['uid', 'created_at'];
        if (!in_array($sortBy, $allowedSorts)) {
            $sortBy = 'created_at';
        }
        $sortOrder = $sortOrder === 'asc' ? 'asc' : 'desc';
        $perPage = (int) $request->input('per_page', 20);
        $paginator = $query->orderBy($sortBy, $sortOrder)->paginate(min($perPage, 100));

        $items = collect($paginator->items())->map(fn ($row): array => [
            'id' => (int) $row->uid,
            'uid' => (int) $row->uid,
            'username' => (string) $row->username,
            'email' => (string) $row->email,
            'plan_code' => $row->plan_code,
            'status' => $row->status,
            'role' => 'member',
            'current_team_id' => $row->current_team_id,
            'locale' => $row->locale,
            'email_verified_at' => $row->email_verified_at,
            'last_login_at' => null,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ])->all();

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
        $username = trim((string) $request->input('username', $request->input('name', '')));
        $validator = Validator::make($request->all(), [
            'username' => 'nullable|string|max:100',
            'name' => 'nullable|string|max:100',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails() || $username === '') {
            return response()->json(['error' => ['code' => 'VALIDATION_FAILED', 'message' => $validator->errors()->first(), 'details' => $validator->errors()->toArray()]], 422);
        }

        $user = User::create([
            'username' => $username,
            'email' => $request->input('email'),
            'password' => Hash::make($request->input('password')),
            'status' => 'active',
        ]);

        AdminAuditLog::record('user.create', 'user', $user->uid, ['email' => $user->email], $actorId, null, $request->ip(), $request->userAgent());

        return response()->json(['data' => $user->toArray()], 201);
    }

    public function show(string $userId): JsonResponse
    {
        $user = User::query()->findOrFail($userId);
        return response()->json(['data' => [
            'id' => (int) $user->uid,
            'uid' => (int) $user->uid,
            'username' => (string) $user->username,
            'email' => (string) $user->email,
            'plan_code' => $user->plan_code,
            'status' => $user->status,
            'role' => 'member',
            'current_team_id' => $user->current_team_id,
            'locale' => $user->locale,
            'email_verified_at' => $user->email_verified_at,
            'last_login_at' => null,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ]]);
    }

    public function update(Request $request, string $userId): JsonResponse
    {
        $actorId = $request->user()?->admin_id;
        $user = User::query()->findOrFail($userId);
        $username = $request->has('username') || $request->has('name')
            ? trim((string) $request->input('username', $request->input('name', '')))
            : null;

        $validator = Validator::make($request->all(), [
            'username' => 'nullable|string|max:100',
            'name' => 'nullable|string|max:100',
            'email' => 'sometimes|email|unique:users,email,' . $userId . ',uid',
            'password' => 'sometimes|string|min:8|confirmed',
        ]);

        if ($validator->fails() || ($username !== null && $username === '')) {
            return response()->json(['error' => ['code' => 'VALIDATION_FAILED', 'message' => $validator->errors()->first(), 'details' => $validator->errors()->toArray()]], 422);
        }

        $updateData = [];
        if ($username !== null) $updateData['username'] = $username;
        if ($request->has('email')) $updateData['email'] = $request->input('email');
        if ($request->has('password')) $updateData['password'] = Hash::make($request->input('password'));

        if (!empty($updateData)) {
            $user->update($updateData);
        }

        AdminAuditLog::record('user.update', 'user', $user->uid, array_keys($updateData), $actorId, null, $request->ip(), $request->userAgent());

        return response()->json(['data' => $user->fresh()->toArray()]);
    }

    public function destroy(Request $request, string $userId): JsonResponse
    {
        $actorId = $request->user()?->admin_id;
        $user = User::query()->findOrFail($userId);

        // Prevent self-delete
        if ($actorId == $userId) {
            return response()->json(['error' => ['code' => 'CANNOT_DELETE_SELF', 'message' => 'Cannot delete your own account']], 422);
        }

        try {
            DB::transaction(function () use ($user) {
                $user->delete();
            });
        } catch (QueryException $e) {
            if ((int) $e->getCode() === 23000) {
                return response()->json(['error' => ['code' => 'USER_HAS_DEPENDENCIES', 'message' => 'User has related data (team/order/profile). Remove dependencies first.']], 409);
            }
            throw $e;
        }

        AdminAuditLog::record('user.delete', 'user', $user->uid, ['email' => $user->email], $actorId, null, $request->ip(), $request->userAgent());

        return response()->json(['data' => ['deleted' => true]]);
    }

    public function disable(string $userId): JsonResponse
    {
        $user = User::query()->findOrFail($userId);
        $user->update(['status' => 'suspended']);

        return response()->json(['data' => ['ok' => true]]);
    }

    public function enable(string $userId): JsonResponse
    {
        $user = User::query()->findOrFail($userId);
        $user->update(['status' => 'active']);

        return response()->json(['data' => ['ok' => true]]);
    }
}
