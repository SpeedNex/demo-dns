<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\AdminAuditLog;
use App\Models\SystemConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

/**
 * AdminSystemConfigController
 *
 * V1 简化版密钥加密：
 *  - 写：sensitive value → Crypt::encryptString
 *  - 读：response 时 → 始终返回掩码（如 sk_live_****abcd），前端无法拿到明文
 *  - 实际使用：业务代码通过 SystemConfigValue::get($key) 解密读取
 */
final class AdminSystemConfigController
{
    private const CACHE_KEY = 'admin.system_config.payload';
    private const MASKED_PLACEHOLDER = '********';
    /** 仅加密这些 key 下的 string 标量值；保留 key 名供前端判断。 */
    private const STRICTLY_ENCRYPTED_KEYS = [
        'stripe_secret_key',
        'stripe_webhook_secret',
    ];

    public function show(): JsonResponse
    {
        $cached = Cache::remember(self::CACHE_KEY, now()->addMinutes(5), function (): array {
            $rows = SystemConfig::query()->get();
            $payload = [];
            foreach ($rows as $row) {
                $payload[$row->key] = $this->isSensitiveKey($row->key)
                    ? $this->maskConfigValue($row->value)
                    : $row->value;
            }

            return [
                'data' => $payload,
                'meta' => [
                    'keys' => array_keys($payload),
                    'updated_at' => $rows->max('updated_at'),
                ],
            ];
        });

        return response()->json($cached);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'configs' => 'sometimes|array',
            'dns' => 'sometimes|array',
            'redis' => 'sometimes|array',
            'clickhouse' => 'sometimes|array',
            'payment' => 'sometimes|array',
            'mail' => 'sometimes|array',
        ]);

        $configs = is_array($validated['configs'] ?? null)
            ? $validated['configs']
            : $request->except(['_token']);

        if (! is_array($configs) || $configs === []) {
            return response()->json(['message' => 'No configuration payload provided.'], 422);
        }

        $actorId = $request->user()?->id ?? 'system';
        $updated = [];
        foreach ($configs as $key => $value) {
            $keyStr = (string) $key;
            $current = SystemConfig::query()->find($keyStr);
            $resolvedValue = $this->restoreMaskedSensitiveValues($value, $current?->value, $keyStr);
            // 加密入库
            $storedValue = $this->isSensitiveKey($keyStr)
                ? $this->encryptSensitive($resolvedValue)
                : $resolvedValue;
            SystemConfig::updateOrCreate(
                ['key' => $keyStr],
                ['value' => $storedValue, 'updated_by' => $actorId],
            );
            $updated[] = $keyStr;
        }

        Cache::forget(self::CACHE_KEY);

        AdminAuditLog::record(
            action: 'system_config.update',
            targetType: 'system_config',
            targetId: null,
            payload: ['updated_keys' => $updated],
            actorId: $actorId,
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return response()->json(['data' => ['updated' => $updated]]);
    }

    private function isSensitiveKey(string $key): bool
    {
        return str_contains($key, 'password')
            || str_contains($key, 'secret')
            || str_contains($key, 'merchant_key')
            || str_contains($key, 'token')
            || in_array($key, self::STRICTLY_ENCRYPTED_KEYS, true);
    }

    /**
     * @param mixed $value
     @return mixed
     */
    private function maskConfigValue(mixed $value): mixed
    {
        if (is_string($value)) {
            if ($value === '') {
                return '';
            }
            // 若为 Crypt 密文（ey开头）→ 已加密，直接显示掩码
            return self::MASKED_PLACEHOLDER;
        }

        if (! is_array($value)) {
            return $value;
        }

        $masked = [];
        foreach ($value as $key => $item) {
            $masked[$key] = $this->isSensitiveKey((string) $key)
                ? (is_string($item) && $item !== '' ? self::MASKED_PLACEHOLDER : $item)
                : $item;
        }

        return $masked;
    }

    private function restoreMaskedSensitiveValues(mixed $incoming, mixed $current, string $contextKey): mixed
    {
        if (is_string($incoming) && $incoming === self::MASKED_PLACEHOLDER && $this->isSensitiveKey($contextKey)) {
            return $current; // 前端掩码 → 保留原值（不解密，避免明文流转）
        }

        if (! is_array($incoming)) {
            return $incoming;
        }

        $resolved = [];
        foreach ($incoming as $key => $value) {
            $resolved[$key] = $this->restoreMaskedSensitiveValues(
                $value,
                is_array($current) ? ($current[$key] ?? null) : null,
                (string) $key,
            );
        }

        return $resolved;
    }

    /**
     * 对敏感标量加密；非标量（数组）原样存。
     */
    private function encryptSensitive(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }
        // 已是密文（前一次 save 的）→ 不重复加密
        if (str_starts_with($value, 'ey')) {
            return $value;
        }
        try {
            return Crypt::encryptString($value);
        } catch (\Throwable) {
            return $value;
        }
    }
}
