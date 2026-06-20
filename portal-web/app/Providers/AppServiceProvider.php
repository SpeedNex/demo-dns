<?php

namespace App\Providers;

use App\Support\SystemConfigValue;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->applyRuntimeSystemConfig();
    }

    /**
     * 让 Redis 连接参数从后台 SystemConfig 运行时覆盖 .env。
     */
    private function applyRuntimeSystemConfig(): void
    {
        try {
            $override = SystemConfigValue::get('redis');
        } catch (\Throwable) {
            return;
        }
        if ($override === []) {
            return;
        }

        foreach (['default', 'cache'] as $connection) {
            $base = (array) config("database.redis.{$connection}");
            $merged = $base;
            foreach (['host', 'port', 'password', 'database', 'persistent', 'read_timeout', 'timeout'] as $field) {
                if (! array_key_exists($field, $override)) {
                    continue;
                }
                $value = $override[$field];
                if ($value === '' || $value === null) {
                    continue;
                }
                $merged[$field] = $value;
            }
            config(["database.redis.{$connection}" => $merged]);
        }
    }
}
