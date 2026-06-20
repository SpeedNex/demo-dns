<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\SystemConfig;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * SystemConfigValue — runtime configuration helper.
 *
 * 优先从 `system_configs` 表读取，缺失时回退到 `config()`。
 * 缓存 60s；`flush()` 在 AdminSystemConfigController::update 中调用。
 *
 * 用途：让 redis / clickhouse / dns 域名等运行时配置从后台 SystemConfig 页面
 * 真正生效，而不是只能从 .env 启动期注入。
 */
final class SystemConfigValue
{
    private const CACHE_KEY = 'support.system_config.snapshot';
    private const CACHE_TTL_SECONDS = 60;

    /**
     * 读取某个 key 的值（SystemConfig 表存储为 JSON 对象）。
     *
     * @return array<string, mixed>
     */
    public static function get(string $key, array $default = []): array
    {
        $snapshot = self::snapshot();
        if (! isset($snapshot[$key]) || ! is_array($snapshot[$key])) {
            return $default;
        }
        return $snapshot[$key];
    }

    /**
     * 读取某个 key 中的标量字段。
     */
    public static function field(string $key, string $field, mixed $default = null): mixed
    {
        $data = self::get($key);
        return $data[$field] ?? $default;
    }

    /**
     * Redis 运行时配置（合并 .env 默认值）。
     *
     * @return array<string, mixed>
     */
    public static function redis(?array $envDefault = null): array
    {
        $base = $envDefault ?? (array) config('database.redis.default');
        $override = self::get('redis');
        return self::mergeConfig($base, $override, [
            'host', 'port', 'password', 'database', 'timeout', 'read_timeout', 'persistent',
        ]);
    }

    /**
     * ClickHouse 运行时配置（合并 .env 默认值）。
     *
     * @return array<string, mixed>
     */
    public static function clickhouse(?array $envDefault = null): array
    {
        $base = $envDefault ?? (array) config('clickhouse');
        $override = self::get('clickhouse');
        return self::mergeConfig($base, $override, [
            'host', 'port', 'database', 'username', 'password', 'password_file',
            'timeout_seconds', 'connect_timeout_seconds', 'enabled',
        ]);
    }

    /**
     * 强制刷新缓存（保存配置后调用）。
     */
    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function snapshot(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function (): array {
            if (! self::tableReady()) {
                return [];
            }
            $payload = [];
            foreach (SystemConfig::query()->get() as $row) {
                $payload[$row->config_key] = $row->config_value;
            }
            return $payload;
        });
    }

    private static function tableReady(): bool
    {
        try {
            return Schema::hasTable('system_configs');
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param array<string, mixed> $base
     * @param array<string, mixed> $override
     * @param array<int, string>   $allowed
     * @return array<string, mixed>
     */
    private static function mergeConfig(array $base, array $override, array $allowed): array
    {
        foreach ($allowed as $field) {
            if (! array_key_exists($field, $override)) {
                continue;
            }
            $value = $override[$field];
            // 跳过空字符串，避免覆盖 .env 中的有效值
            if ($value === '' || $value === null) {
                continue;
            }
            $base[$field] = $value;
        }
        return $base;
    }
}
