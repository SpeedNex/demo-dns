<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 将"黑白名单"（blacklist-whitelist）从 identity 移到 protection（防护配置）
        DB::table('admin_menu_rule')
            ->where('menu_key', 'blacklist-whitelist')
            ->where('parent_key', 'identity')
            ->update([
                'parent_key' => 'protection',
                'updated_at' => now(),
            ]);

        // 将"方案与套餐"（user-policy-services）从 identity 移到 finance（财务管理）
        DB::table('admin_menu_rule')
            ->where('menu_key', 'user-policy-services')
            ->where('parent_key', 'identity')
            ->update([
                'parent_key' => 'finance',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // 还原"黑白名单"回到 identity
        DB::table('admin_menu_rule')
            ->where('menu_key', 'blacklist-whitelist')
            ->where('parent_key', 'protection')
            ->update([
                'parent_key' => 'identity',
                'updated_at' => now(),
            ]);

        // 还原"方案与套餐"回到 identity
        DB::table('admin_menu_rule')
            ->where('menu_key', 'user-policy-services')
            ->where('parent_key', 'finance')
            ->update([
                'parent_key' => 'identity',
                'updated_at' => now(),
            ]);
    }
};
