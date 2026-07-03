<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\User;
use App\Domain\Auth\PermissionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Super admin account for management backend.
        // V2.2: BIGINT auto-increment id, no ULID/string primary key.
        // 重要：admin 也有 password cast，统一用 updateOrCreate + 明文密码避免双重哈希。
        Admin::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'username' => 'admin',
                'email' => 'admin@example.com',
                'password' => '123456',
                'status' => 'active',
                'is_super' => true,
                'locale' => 'zh-CN',
            ]
        );

        // Default user account for member center smoke testing.
        // 重要：User 模型开启了 'password' => 'hashed' cast，
        // firstOrCreate 写入时会再次自动哈希，导致数据库里存的是双重哈希。
        // 这里直接用 updateOrCreate 写入已哈希值，并显式传入原始密码由 cast 处理。
        User::updateOrCreate(
            ['email' => 'user@example.com'],
            [
                'username' => 'user',
                'email' => 'user@example.com',
                'password' => '123456',
                'status' => 'active',
                'plan_code' => 'free',
                'locale' => 'zh-CN',
            ]
        );

        // Seed default permissions and role mappings
        PermissionService::seedDefaults();

        // Seed admin menu rules (so AdminLayout can load them from DB)
        $this->call(AdminMenuRuleSeeder::class);

        // Seed rule categories and brands (Phase 1 data fill)
        $this->call(RuleCategorySeeder::class);
    }
}
