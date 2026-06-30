<?php

use Illuminate\Database\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 删除旧的"防护策略"和"套餐目录"菜单项（已合并为"安全目录"）
        DB::table('admin_menu_rule')
            ->whereIn('menu_key', ['protection_policies', 'member_catalogs'])
            ->delete();

        // 添加新的"安全目录"菜单项（group: node 排在 nodes 后面）
        $exists = DB::table('admin_menu_rule')
            ->where('menu_key', 'security_catalog')
            ->exists();

        if (!$exists) {
            DB::table('admin_menu_rule')->insert([
                'menu_key' => 'security_catalog',
                'parent_key' => null,
                'title_key' => '安全目录',
                'path' => '/admin/security-catalog',
                'icon' => 'Lock',
                'sort_order' => 45,
                'visible' => true,
                'permission_code' => 'admin.security.read',
                'group_key' => 'node',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // 删除新的"安全目录"
        DB::table('admin_menu_rule')
            ->where('menu_key', 'security_catalog')
            ->delete();

        // 注意：旧的 protection_policies 和 member_catalogs 数据已丢失，
        // down() 不会自动还原。如需还原请通过 menu-config UI 手动添加。
    }
};
