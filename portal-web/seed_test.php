<?php
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// Create default admin: admin / 123456
DB::table('admins')->updateOrInsert(
    ['username' => 'admin'],
    [
        'id' => 'adm_default001',
        'username' => 'admin',
        'email' => 'admin@ocer.local',
        'password_hash' => Hash::make('123456'),
        'role' => 'super_admin',
        'status' => 'active',
        'is_super_admin' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]
);
echo "admin: ok (admin / 123456)\n";

// Create default user: test / 123456
try {
    $result = app(\App\Domain\Auth\AuthService::class)->register([
        'username' => 'test',
        'email' => 'test@ocer.local',
        'password' => '123456',
        'timezone' => 'UTC',
        'locale' => 'zh-CN',
        'device_name' => 'test',
    ]);
    echo "user: ok id=" . ($result['user']['id'] ?? '?') . " (test / 123456)\n";
} catch (\Throwable $e) {
    echo "user register error: " . $e->getMessage() . "\n";
}

// Create test admin (existing)
DB::table('admins')->updateOrInsert(
    ['email' => 'admin@ocer.com'],
    [
        'id' => 'adm_test001',
        'username' => 'testadmin',
        'email' => 'admin@ocer.com',
        'password_hash' => Hash::make('Admin@123'),
        'role' => 'super_admin',
        'status' => 'active',
        'is_super_admin' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]
);
echo "admin2: ok (admin@ocer.com / Admin@123)\n";

// Create test member (existing)
try {
    $result2 = app(\App\Domain\Auth\AuthService::class)->register([
        'username' => 'testuser',
        'email' => 'user@ocer.com',
        'password' => 'User@123',
        'timezone' => 'UTC',
        'locale' => 'zh-CN',
        'device_name' => 'test',
    ]);
    echo "user2: ok id=" . ($result2['user']['id'] ?? '?') . " (user@ocer.com / User@123)\n";
} catch (\Throwable $e) {
    echo "user2 register error: " . $e->getMessage() . "\n";
}

// Create another test member (existing)
try {
    $result3 = app(\App\Domain\Auth\AuthService::class)->register([
        'username' => 'alice',
        'email' => 'alice@ocer.com',
        'password' => 'Alice@123',
        'timezone' => 'UTC',
        'locale' => 'en',
        'device_name' => 'test',
    ]);
    echo "user3: ok id=" . ($result3['user']['id'] ?? '?') . " (alice@ocer.com / Alice@123)\n";
} catch (\Throwable $e) {
    echo "user3 register error: " . $e->getMessage() . "\n";
}