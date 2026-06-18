<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AdminMenuRuleSeeder extends Seeder
{
    public function run(): void
    {
        $menus = [
            // 服务分组
            ['menu_key' => 'dashboard', 'parent_key' => null, 'title_key' => 'nav.dashboard', 'path' => '/admin/dashboard', 'icon' => 'DataAnalysis', 'sort_order' => 1, 'visible' => true, 'permission_code' => 'admin.dashboard.read', 'group_key' => 'service'],
            ['menu_key' => 'nodes', 'parent_key' => null, 'title_key' => 'nav.nodes', 'path' => '/admin/nodes', 'icon' => 'Monitor', 'sort_order' => 2, 'visible' => true, 'permission_code' => 'admin.nodes.read', 'group_key' => 'service'],
            ['menu_key' => 'geo-dns', 'parent_key' => null, 'title_key' => 'nav.geoDns', 'path' => '/admin/geo-dns', 'icon' => 'Connection', 'sort_order' => 3, 'visible' => true, 'permission_code' => 'admin.geo_dns.read', 'group_key' => 'service'],
            ['menu_key' => 'rules', 'parent_key' => null, 'title_key' => 'nav.ruleLibrary', 'path' => '/admin/rules', 'icon' => 'Collection', 'sort_order' => 4, 'visible' => true, 'permission_code' => 'admin.rules.read', 'group_key' => 'service'],
            ['menu_key' => 'publishes', 'parent_key' => null, 'title_key' => 'nav.publishes', 'path' => '/admin/publishes', 'icon' => 'Upload', 'sort_order' => 5, 'visible' => true, 'permission_code' => 'admin.publishes.read', 'group_key' => 'service'],

            // 监控分组
            ['menu_key' => 'alerts', 'parent_key' => null, 'title_key' => 'admin.alerts', 'path' => '/admin/alerts', 'icon' => 'Message', 'sort_order' => 6, 'visible' => true, 'permission_code' => 'admin.alerts.read', 'group_key' => 'monitor'],
            ['menu_key' => 'query-logs', 'parent_key' => null, 'title_key' => 'admin.queryLogs', 'path' => '/admin/query-logs', 'icon' => 'Document', 'sort_order' => 7, 'visible' => true, 'permission_code' => 'admin.query_logs.read', 'group_key' => 'monitor'],
            ['menu_key' => 'audit-logs', 'parent_key' => null, 'title_key' => 'nav.auditLogs', 'path' => '/admin/audit-logs', 'icon' => 'Tickets', 'sort_order' => 8, 'visible' => true, 'permission_code' => 'admin.audit.read', 'group_key' => 'monitor'],

            // 用户管理分组
            ['menu_key' => 'users', 'parent_key' => null, 'title_key' => 'admin.users', 'path' => '/admin/users', 'icon' => 'User', 'sort_order' => 9, 'visible' => true, 'permission_code' => 'admin.users.read', 'group_key' => 'user'],
            ['menu_key' => 'devices', 'parent_key' => null, 'title_key' => 'admin.devices', 'path' => '/admin/devices', 'icon' => 'Avatar', 'sort_order' => 10, 'visible' => true, 'permission_code' => 'admin.devices.read', 'group_key' => 'user'],
            ['menu_key' => 'member-catalogs', 'parent_key' => null, 'title_key' => 'admin.memberCatalogs.title', 'path' => '/admin/member-catalogs', 'icon' => 'Grid', 'sort_order' => 11, 'visible' => true, 'permission_code' => 'admin.users.read', 'group_key' => 'user'],
            ['menu_key' => 'rbac', 'parent_key' => null, 'title_key' => 'admin.rbac.title', 'path' => '/admin/rbac', 'icon' => 'Lock', 'sort_order' => 12, 'visible' => true, 'permission_code' => 'admin.rbac.read', 'group_key' => 'user'],

            // 财务分组
            ['menu_key' => 'billing', 'parent_key' => null, 'title_key' => 'admin.billing.title', 'path' => '/admin/billing', 'icon' => 'Coin', 'sort_order' => 13, 'visible' => true, 'permission_code' => 'admin.billing.read', 'group_key' => 'finance'],
            ['menu_key' => 'plans', 'parent_key' => null, 'title_key' => 'admin.plans.title', 'path' => '/admin/plans', 'icon' => 'Tickets', 'sort_order' => 14, 'visible' => true, 'permission_code' => 'admin.billing.read', 'group_key' => 'finance'],
            ['menu_key' => 'finance', 'parent_key' => null, 'title_key' => 'admin.finance.menu', 'path' => 'finance', 'icon' => 'Wallet', 'sort_order' => 15, 'visible' => true, 'permission_code' => 'admin.finance.read', 'group_key' => 'finance'],
            // 子菜单
            ['menu_key' => 'balance', 'parent_key' => 'finance', 'title_key' => 'admin.finance.balance', 'path' => '/admin/balance', 'icon' => 'Wallet', 'sort_order' => 1, 'visible' => true, 'permission_code' => 'admin.finance.read', 'group_key' => 'finance'],
            ['menu_key' => 'recharge', 'parent_key' => 'finance', 'title_key' => 'admin.finance.recharge', 'path' => '/admin/recharge', 'icon' => 'Money', 'sort_order' => 2, 'visible' => true, 'permission_code' => 'admin.finance.read', 'group_key' => 'finance'],
            ['menu_key' => 'bill', 'parent_key' => 'finance', 'title_key' => 'admin.finance.bill', 'path' => '/admin/bill', 'icon' => 'CreditCard', 'sort_order' => 3, 'visible' => true, 'permission_code' => 'admin.finance.read', 'group_key' => 'finance'],
            ['menu_key' => 'refund-records', 'parent_key' => 'finance', 'title_key' => 'admin.finance.refundRecords', 'path' => '/admin/refund-records', 'icon' => 'RefreshLeft', 'sort_order' => 4, 'visible' => true, 'permission_code' => 'admin.finance.read', 'group_key' => 'finance'],

            // 设置分组
            ['menu_key' => 'system-config', 'parent_key' => null, 'title_key' => 'nav.systemConfig', 'path' => '/admin/system-config', 'icon' => 'Tools', 'sort_order' => 16, 'visible' => true, 'permission_code' => 'admin.system_config.read', 'group_key' => 'settings'],
            ['menu_key' => 'basic-config', 'parent_key' => null, 'title_key' => 'admin.basicConfig.title', 'path' => '/admin/basic-config', 'icon' => 'Setting', 'sort_order' => 17, 'visible' => true, 'permission_code' => 'admin.system_config.read', 'group_key' => 'settings'],
            ['menu_key' => 'menu-config', 'parent_key' => null, 'title_key' => 'admin.menuConfig.title', 'path' => '/admin/menu-config', 'icon' => 'List', 'sort_order' => 18, 'visible' => true, 'permission_code' => 'admin.system_config.write', 'group_key' => 'settings'],
        ];

        foreach ($menus as $menu) {
            DB::table('admin_menu_rule')->updateOrInsert(
                ['menu_key' => $menu['menu_key']],
                $menu
            );
        }
    }
}
