<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 下线 user-policy-services 页面（功能已合并至 /admin/plans）
        DB::table('admin_menu_rule')->where('menu_key', 'user-policy-services')->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 恢复 user-policy-services 菜单项（归属 finance 分组）
        DB::table('admin_menu_rule')->insert([
            'menu_key' => 'user-policy-services',
            'parent_key' => 'finance',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
