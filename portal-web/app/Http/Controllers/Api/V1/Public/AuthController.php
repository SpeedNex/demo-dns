<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Domain\Auth\AuthService;
use App\Models\Admin;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;

final class AuthController
{
    public function __construct(
        private readonly AuthService $authService,
    ) {
    }

    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'username' => 'nullable|string|max:100',
            'name' => 'nullable|string|max:100',
            'email' => 'required|email|max:255',
            'password' => ['required', Password::min(8)->mixedCase()],
        ]);

        $username = trim((string) ($validated['username'] ?? $validated['name'] ?? ''));

        $result = $this->authService->register([
            'username' => $username,
            'email' => $validated['email'],
            'password' => $validated['password'],
            'locale' => $request->input('locale', 'zh-CN'),
            'device_name' => $request->input('device_name', 'web'),
        ]);

        return response()->json(['data' => $result]);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|string',
            'password' => 'required|string',
        ]);

        $result = $this->authService->login([
            'name' => $validated['email'],
            'password' => $validated['password'],
            'device_name' => $request->input('device_name', 'web'),
        ]);

        return response()->json(['data' => $result]);
    }

    public function adminLogin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|string',
            'password' => 'required|string',
        ]);

        $credential = (string) $validated['email'];
        $password = (string) $validated['password'];

        if (str_contains($credential, '@')) {
            $admin = Admin::where('email', strtolower($credential))->first();
        } else {
            $admin = Admin::whereRaw('LOWER(username) = ?', [strtolower($credential)])->first();
        }

        if (! $admin || ! Hash::check($password, (string) $admin->password)) {
            throw ValidationException::withMessages(['email' => 'Invalid credentials.']);
        }

        if ($admin->status !== 'active') {
            throw ValidationException::withMessages(['email' => 'Account is not active.']);
        }

        $admin->update(['last_login_at' => now()]);

        // Create Sanctum token
        $deviceName = (string) $request->input('device_name', 'admin-web');
        $token = $admin->createToken($deviceName)->plainTextToken;

        return response()->json([
            'data' => [
                'token' => $token,
                'user' => [
                    'admin_id' => $admin->admin_id,
                    'username' => $admin->username,
                    'email' => $admin->email,
                    'role' => 'admin',
                    'is_super' => (bool) $admin->is_super,
                ],
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['data' => ['ok' => true]]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json(['data' => [
            'uid' => $user->uid,
            'username' => $user->username,
            'email' => $user->email,
            'status' => $user->status,
            'plan_code' => $user->plan_code,
            'locale' => $user->locale,
            'created_at' => $user->created_at?->toIso8601String(),
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
        ]]);
    }
}
