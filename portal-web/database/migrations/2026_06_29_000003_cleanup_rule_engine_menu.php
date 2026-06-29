<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. 发布中心（publish-center）从 规则引擎 移到 监控中心
        //    监控中心现有子菜单: alerts(1), query-logs(2), audit-logs(3)
        //    发布中心排在最后: sort_order = 4
        DB::table('admin_menu_rule')
            ->where('menu_key', 'publish-center')
            ->where('parent_key', 'rule-engine')
            ->update([
                'parent_key' => 'monitoring',
                'sort_order' => 4,
                'updated_at' => now(),
            ]);

        // 2. 品牌管理（brands）从 规则引擎 移到 DNS 服务
        //    DNS 服务现有子菜单: nodes(1), region-manage(2)
        //    品牌管理排在最后: sort_order = 3
        DB::table('admin_menu_rule')
            ->where('menu_key', 'brands')
            ->where('parent_key', 'rule-engine')
            ->update([
                'parent_key' => 'dns-service',
                'sort_order' => 3,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // 还原发布中心回到规则引擎（原 sort_order = 5）
        DB::table('admin_menu_rule')
            ->where('menu_key', 'publish-center')
            ->where('parent_key', 'monitoring')
            ->update([
                'parent_key' => 'rule-engine',
                'sort_order' => 5,
                'updated_at' => now(),
            ]);

        // 还原品牌管理回到规则引擎（原 sort_order = 4）
        DB::table('admin_menu_rule')
            ->where('menu_key', 'brands')
            ->where('parent_key', 'dns-service')
            ->update([
                'parent_key' => 'rule-engine',
                'sort_order' => 4,
                'updated_at' => now(),
            ]);
    }
};
