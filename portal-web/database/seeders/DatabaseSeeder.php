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
        Admin::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'username' => 'admin',
                'email' => 'admin@example.com',
                'password' => Hash::make('123456'),
                'status' => 'active',
                'is_super' => true,
                'locale' => 'zh-CN',
            ]
        );

        // Default user account for member center smoke testing.
        User::firstOrCreate(
            ['email' => 'user@example.com'],
            [
                'username' => 'user',
                'email' => 'user@example.com',
                'password' => Hash::make('123456'),
                'status' => 'active',
                'plan_code' => 'free',
                'locale' => 'zh-CN',
            ]
        );

        // Seed default permissions and role mappings
        PermissionService::seedDefaults();

        // Seed admin menu rules (so AdminLayout can load them from DB)
        $this->call(AdminMenuRuleSeeder::class);
    }
}
