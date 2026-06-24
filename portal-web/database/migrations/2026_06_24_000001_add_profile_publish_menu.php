<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 2026-06-24: 添加"配置文件发布"菜单项到后台导航
        $exists = DB::table('admin_menu_rule')
            ->where('menu_key', 'admin.profile_publish')
            ->exists();

        if (!$exists) {
            DB::table('admin_menu_rule')->insert([
                'menu_key' => 'admin.profile_publish',
                'parent_key' => null,
                'title_key' => '配置文件发布',
                'path' => '/admin/profile-publish',
                'icon' => 'Document',
                'sort_order' => 95,
                'visible' => true,
                'permission_code' => 'admin.publishes.read',
                'group_key' => 'system',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('admin_menu_rule')
            ->where('menu_key', 'admin.profile_publish')
            ->delete();
    }
};
