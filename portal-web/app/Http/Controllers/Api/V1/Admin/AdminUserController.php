<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\User;
use App\Models\AdminAuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

final class AdminUserController
{
    public function index(Request $request): JsonResponse
    {
        $query = User::query();

        if ($email = $request->input('email')) {
            $query->where('email', 'like', "%{$email}%");
        }

        if ($name = $request->input('name', $request->input('username'))) {
            $query->where('username', 'like', "%{$name}%");
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $perPage = (int) $request->input('per_page', 20);
        $paginator = $query->orderByDesc('created_at')->paginate(min($perPage, 100));

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'page' => $paginator->currentPage(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $actorId = $request->user()?->id;
        $username = trim((string) $request->input('username', $request->input('name', '')));
        $validator = Validator::make($request->all(), [
            'username' => 'nullable|string|max:100',
            'name' => 'nullable|string|max:100',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'sometimes|string|in:member,admin',
        ]);

        if ($validator->fails() || $username === '') {
            return response()->json(['error' => ['code' => 'VALIDATION_FAILED', 'message' => $validator->errors()->first(), 'details' => $validator->errors()->toArray()]], 422);
        }

        $user = User::create([
            'username' => $username,
            'email' => $request->input('email'),
            'password' => Hash::make($request->input('password')),
            'role' => $request->input('role', 'member'),
            'status' => 'active',
        ]);

        AdminAuditLog::record('user.create', 'user', $user->id, ['email' => $user->email, 'role' => $user->role], $actorId, null, $request->ip(), $request->userAgent());

        return response()->json(['data' => $user->toArray()], 201);
    }

    public function show(string $userId): JsonResponse
    {
        $user = User::query()->findOrFail($userId);
        return response()->json(['data' => $user->toArray()]);
    }

    public function update(Request $request, string $userId): JsonResponse
    {
        $actorId = $request->user()?->id;
        $user = User::query()->findOrFail($userId);
        $username = $request->has('username') || $request->has('name')
            ? trim((string) $request->input('username', $request->input('name', '')))
            : null;

        $validator = Validator::make($request->all(), [
            'username' => 'nullable|string|max:100',
            'name' => 'nullable|string|max:100',
            'email' => 'sometimes|email|unique:users,email,' . $userId,
            'password' => 'sometimes|string|min:8|confirmed',
            'role' => 'sometimes|string|in:member,admin',
        ]);

        if ($validator->fails() || ($username !== null && $username === '')) {
            return response()->json(['error' => ['code' => 'VALIDATION_FAILED', 'message' => $validator->errors()->first(), 'details' => $validator->errors()->toArray()]], 422);
        }

        $updateData = [];
        if ($username !== null) $updateData['username'] = $username;
        if ($request->has('email')) $updateData['email'] = $request->input('email');
        if ($request->has('password')) $updateData['password'] = Hash::make($request->input('password'));
        if ($request->has('role')) $updateData['role'] = $request->input('role');

        if (!empty($updateData)) {
            $user->update($updateData);
        }

        AdminAuditLog::record('user.update', 'user', $user->id, array_keys($updateData), $actorId, null, $request->ip(), $request->userAgent());

        return response()->json(['data' => $user->fresh()->toArray()]);
    }

    public function destroy(Request $request, string $userId): JsonResponse
    {
        $actorId = $request->user()?->id;
        $user = User::query()->findOrFail($userId);

        // Prevent self-delete
        if ($actorId === $userId) {
            return response()->json(['error' => ['code' => 'CANNOT_DELETE_SELF', 'message' => 'Cannot delete your own account']], 422);
        }

        AdminAuditLog::record('user.delete', 'user', $user->id, ['email' => $user->email], $actorId, null, $request->ip(), $request->userAgent());

        $user->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    public function disable(string $userId): JsonResponse
    {
        $user = User::query()->findOrFail($userId);
        $user->update(['status' => 'disabled']);

        return response()->json(['data' => ['ok' => true]]);
    }

    public function enable(string $userId): JsonResponse
    {
        $user = User::query()->findOrFail($userId);
        $user->update(['status' => 'active']);

        return response()->json(['data' => ['ok' => true]]);
    }
}
