<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Admin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * admin:create — 创建或重置管理员账号。
 *
 * 用法:
 *   php artisan admin:create --username=admin --email=admin@example.com --password=SecurePass123
 *   php artisan admin:create --reset-password --password=NewSecurePass
 *
 * 主要用于生产环境初始化或密码恢复。密码必填且至少8字符。
 */
final class AdminCreateCommand extends Command
{
    protected $signature = 'admin:create
        {--username=admin : 管理员用户名}
        {--email=admin@example.com : 管理员邮箱}
        {--password= : 管理员密码（必填，至少8字符）}
        {--reset-password : 仅重置已有管理员密码}';

    protected $description = 'Create or reset the admin account';

    public function handle(): int
    {
        $username = (string) $this->option('username');
        $email = (string) $this->option('email');
        $password = (string) $this->option('password');
        $resetOnly = (bool) $this->option('reset-password');

        if ($password === '' || strlen($password) < 8) {
            $this->error('Password must be at least 8 characters.');
            return self::FAILURE;
        }

        $existing = Admin::where('email', $email)->orWhere('username', $username)->first();

        if ($existing) {
            // 更新密码
            $existing->password = $password;
            $existing->status = 'active';
            $existing->save();

            $this->info("Admin updated: username={$existing->username} email={$existing->email} admin_id={$existing->admin_id}");

            return 0;
        }

        if ($resetOnly) {
            $this->warn("No admin found to reset. Use without --reset-password to create one.");

            return 1;
        }

        // 创建新管理员
        $admin = Admin::create([
            'username' => $username,
            'email' => $email,
            'password' => $password,
            'status' => 'active',
            'is_super' => true,
            'locale' => 'zh-CN',
        ]);

        $this->info("Admin created: username={$admin->username} email={$admin->email} admin_id={$admin->admin_id}");

        return 0;
    }
}
