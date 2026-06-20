<?php

namespace App\Domain\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class AuthService
{
    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function register(array $payload): array
    {
        $email = strtolower((string) ($payload['email'] ?? ''));
        $username = trim((string) ($payload['username'] ?? ''));
        $password = (string) ($payload['password'] ?? '');

        if (User::where('email', $email)->exists()) {
            throw ValidationException::withMessages(['email' => 'Email already registered.']);
        }

        if ($username === '') {
            $username = $this->buildUsernameFromEmail($email);
        }

        $user = User::create([
            'username' => $username,
            'email' => $email,
            'password' => Hash::make($password),
            'locale' => $payload['locale'] ?? 'zh-CN',
            'status' => 'active',
            'plan_code' => 'free',
        ]);

        // V2.4: 新用户注册即自动创建一个默认 Profile「第一个配置文件」，
        // 确保用户进入控制台后立即拥有可管理的 DNS 策略。
        $this->createDefaultProfile($user->uid, $user->username);

        $deviceName = (string) ($payload['device_name'] ?? 'web');
        $token = $user->createToken($deviceName)->plainTextToken;

        return [
            'user' => [
                'uid' => $user->uid,
                'username' => $user->username,
                'email' => $user->email,
                'locale' => $user->locale,
                'plan_code' => $user->plan_code,
                'status' => $user->status,
            ],
            'token' => $token,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function login(array $payload): array
    {
        $credential = (string) ($payload['name'] ?? '');
        $password = (string) ($payload['password'] ?? '');

        // Support login by email or username (case-insensitive)
        if (str_contains($credential, '@')) {
            $user = User::where('email', strtolower($credential))->first();
        } else {
            $user = User::whereRaw('LOWER(username) = ?', [strtolower($credential)])->first();
        }

        if (! $user || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages(['name' => 'Invalid credentials.']);
        }

        if ($user->status !== 'active') {
            throw ValidationException::withMessages(['name' => 'Account is not active.']);
        }

        $user->update(['last_login_at' => now()]);

        $deviceName = (string) ($payload['device_name'] ?? 'web');
        $token = $user->createToken($deviceName)->plainTextToken;

        return [
            'token' => $token,
            'user' => [
                'uid' => $user->uid,
                'username' => $user->username,
                'email' => $user->email,
                'plan_code' => $user->plan_code,
                'status' => $user->status,
            ],
        ];
    }

    private function buildUsernameFromEmail(string $email): string
    {
        $base = strtolower((string) Str::before($email, '@'));
        $normalized = preg_replace('/[^a-z0-9._-]+/', '-', $base) ?: 'user';
        $normalized = trim($normalized, '-._');
        $candidate = $normalized !== '' ? $normalized : 'user';

        if (! User::where('username', $candidate)->exists()) {
            return $candidate;
        }

        do {
            $candidate = $candidate . '-' . Str::lower(Str::random(4));
        } while (User::where('username', $candidate)->exists());

        return $candidate;
    }

    /**
     * V2.4: 新用户注册时自动创建默认 Profile「第一个配置文件」。
     * 该 Profile 作为 is_default=true 的种子策略，所有统计、规则、设备都将按 Profile 隔离。
     */
    private function createDefaultProfile(int $userId, ?string $username): \App\Models\Profile
    {
        return \App\Models\Profile::create([
            'user_id' => $userId,
            'name' => $username ? ($username . ' 的第一个配置文件') : '第一个配置文件',
            'description' => '系统自动创建',
            'default_action' => 'allow',
            'block_response' => 'nxdomain',
            'is_default' => true,
            'status' => 'active',
            'security_enabled' => true,
            'privacy_enabled' => true,
            'parental_enabled' => false,
            'safe_search_enabled' => false,
            'log_mode' => 'full',
            'log_retention_days' => 24,
            'version' => 1,
        ]);
    }
}
