<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 2026-06-29: 添加"团队管理"菜单项到后台导航（挂载在 用户管理 分组下）
        $exists = DB::table('admin_menu_rule')
            ->where('menu_key', 'teams')
            ->exists();

        if (!$exists) {
            DB::table('admin_menu_rule')->insert([
                'menu_key' => 'teams',
                'parent_key' => 'identity',
                'title_key' => '团队管理',
                'path' => '/admin/teams',
                'icon' => 'Avatar',
                'sort_order' => 2,
                'visible' => true,
                'permission_code' => 'admin.teams.read',
                'group_key' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 将原 sort_order >= 2 的菜单项往后挪，为新菜单腾出位置
            DB::table('admin_menu_rule')
                ->where('parent_key', 'identity')
                ->where('menu_key', '!=', 'teams')
                ->where('sort_order', '>=', 2)
                ->increment('sort_order');
        }
    }

    public function down(): void
    {
        DB::table('admin_menu_rule')
            ->where('menu_key', 'teams')
            ->delete();

        // 恢复 sort_order
        DB::table('admin_menu_rule')
            ->where('parent_key', 'identity')
            ->where('sort_order', '>=', 3)
            ->decrement('sort_order');
    }
};
