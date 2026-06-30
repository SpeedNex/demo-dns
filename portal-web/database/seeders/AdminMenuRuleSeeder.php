<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AdminMenuRuleSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        // 清空并重建菜单数据
        DB::table('admin_menu_rule')->delete();

        $menus = [
            // Dashboard
            ['menu_key' => 'dashboard', 'parent_key' => null, 'title_key' => 'admin.menu.dashboard', 'path' => '/admin/overview', 'icon' => 'Odometer', 'sort_order' => 10, 'visible' => 1, 'permission_code' => 'admin.dashboard.read', 'group_key' => 'overview', 'created_at' => $now, 'updated_at' => $now],

            // User Management
            ['menu_key' => 'users', 'parent_key' => null, 'title_key' => 'admin.menu.users', 'path' => '/admin/users', 'icon' => 'User', 'sort_order' => 20, 'visible' => 1, 'permission_code' => 'admin.users.read', 'group_key' => 'user', 'created_at' => $now, 'updated_at' => $now],
            ['menu_key' => 'user-policy-services', 'parent_key' => 'users', 'title_key' => 'admin.menu.userPolicyServices', 'path' => '/admin/user-policy-services', 'icon' => 'UserFilled', 'sort_order' => 21, 'visible' => 0, 'permission_code' => 'admin.users.read', 'group_key' => 'user', 'created_at' => $now, 'updated_at' => $now],
            ['menu_key' => 'teams', 'parent_key' => 'users', 'title_key' => 'admin.menu.teams', 'path' => '/admin/teams', 'icon' => 'Avatar', 'sort_order' => 22, 'visible' => 1, 'permission_code' => 'admin.teams.read', 'group_key' => 'user', 'created_at' => $now, 'updated_at' => $now],

            // Plan Management
            ['menu_key' => 'plans', 'parent_key' => null, 'title_key' => 'admin.menu.plans', 'path' => '/admin/plans', 'icon' => 'PriceTag', 'sort_order' => 30, 'visible' => 1, 'permission_code' => 'admin.plans.read', 'group_key' => 'user', 'created_at' => $now, 'updated_at' => $now],

            // Node Management
            ['menu_key' => 'nodes', 'parent_key' => null, 'title_key' => 'admin.menu.nodes', 'path' => '/admin/resolver-nodes', 'icon' => 'Monitor', 'sort_order' => 40, 'visible' => 1, 'permission_code' => 'admin.nodes.read', 'group_key' => 'node', 'created_at' => $now, 'updated_at' => $now],
            ['menu_key' => 'geodns-nodes', 'parent_key' => 'nodes', 'title_key' => 'admin.menu.geodnsNodes', 'path' => '/admin/geodns-nodes', 'icon' => 'Position', 'sort_order' => 41, 'visible' => 1, 'permission_code' => 'admin.nodes.read', 'group_key' => 'node', 'created_at' => $now, 'updated_at' => $now],

            // Rule Management
            ['menu_key' => 'rule_sources', 'parent_key' => null, 'title_key' => 'admin.menu.ruleSources', 'path' => '/admin/rule-sources', 'icon' => 'Connection', 'sort_order' => 50, 'visible' => 1, 'permission_code' => 'admin.rules.read', 'group_key' => 'rule', 'created_at' => $now, 'updated_at' => $now],
            ['menu_key' => 'rule_items', 'parent_key' => 'rule_sources', 'title_key' => 'admin.menu.ruleItems', 'path' => '/admin/rule-items', 'icon' => 'List', 'sort_order' => 51, 'visible' => 1, 'permission_code' => 'admin.rules.read', 'group_key' => 'rule', 'created_at' => $now, 'updated_at' => $now],
            ['menu_key' => 'security_items', 'parent_key' => 'rule_sources', 'title_key' => 'admin.menu.securityItems', 'path' => '/admin/security-items', 'icon' => 'Lock', 'sort_order' => 52, 'visible' => 1, 'permission_code' => 'admin.rules.read', 'group_key' => 'rule', 'created_at' => $now, 'updated_at' => $now],
            ['menu_key' => 'rule_categories', 'parent_key' => 'rule_sources', 'title_key' => 'admin.menu.ruleCategories', 'path' => '/admin/rule-categories', 'icon' => 'FolderOpened', 'sort_order' => 53, 'visible' => 1, 'permission_code' => 'admin.rules.read', 'group_key' => 'rule', 'created_at' => $now, 'updated_at' => $now],

            // Analytics
            ['menu_key' => 'query-logs', 'parent_key' => null, 'title_key' => 'admin.menu.queryLogs', 'path' => '/admin/analytics/query-logs', 'icon' => 'DocumentCopy', 'sort_order' => 60, 'visible' => 1, 'permission_code' => 'admin.analytics.read', 'group_key' => 'analytics', 'created_at' => $now, 'updated_at' => $now],
            ['menu_key' => 'usage-stats', 'parent_key' => 'query-logs', 'title_key' => 'admin.menu.usageStats', 'path' => '/admin/analytics/usage-stats', 'icon' => 'DataAnalysis', 'sort_order' => 61, 'visible' => 1, 'permission_code' => 'admin.analytics.read', 'group_key' => 'analytics', 'created_at' => $now, 'updated_at' => $now],

            // Billing / Finance
            ['menu_key' => 'subscriptions', 'parent_key' => null, 'title_key' => 'admin.menu.subscriptions', 'path' => '/admin/subscriptions', 'icon' => 'Ticket', 'sort_order' => 70, 'visible' => 1, 'permission_code' => 'admin.billing.read', 'group_key' => 'finance', 'created_at' => $now, 'updated_at' => $now],
            ['menu_key' => 'bill', 'parent_key' => 'subscriptions', 'title_key' => 'admin.menu.bill', 'path' => '/admin/bill', 'icon' => 'Receipt', 'sort_order' => 71, 'visible' => 1, 'permission_code' => 'admin.billing.read', 'group_key' => 'finance', 'created_at' => $now, 'updated_at' => $now],
            ['menu_key' => 'payment-flows', 'parent_key' => 'subscriptions', 'title_key' => 'admin.menu.paymentFlows', 'path' => '/admin/payment-flows', 'icon' => 'CreditCard', 'sort_order' => 72, 'visible' => 1, 'permission_code' => 'admin.billing.read', 'group_key' => 'finance', 'created_at' => $now, 'updated_at' => $now],

            // System
            ['menu_key' => 'admins', 'parent_key' => null, 'title_key' => 'admin.menu.admins', 'path' => '/admin/admins', 'icon' => 'Avatar', 'sort_order' => 80, 'visible' => 1, 'permission_code' => 'admin.system.read', 'group_key' => 'system', 'created_at' => $now, 'updated_at' => $now],
            ['menu_key' => 'roles', 'parent_key' => 'admins', 'title_key' => 'admin.menu.roles', 'path' => '/admin/roles', 'icon' => 'UserFilled', 'sort_order' => 81, 'visible' => 1, 'permission_code' => 'admin.system.read', 'group_key' => 'system', 'created_at' => $now, 'updated_at' => $now],
            ['menu_key' => 'permissions', 'parent_key' => 'admins', 'title_key' => 'admin.menu.permissions', 'path' => '/admin/permissions', 'icon' => 'Lock', 'sort_order' => 82, 'visible' => 1, 'permission_code' => 'admin.system.read', 'group_key' => 'system', 'created_at' => $now, 'updated_at' => $now],
            ['menu_key' => 'menu-config', 'parent_key' => 'admins', 'title_key' => 'admin.menu.menuConfig', 'path' => '/admin/menu-config', 'icon' => 'Menu', 'sort_order' => 83, 'visible' => 1, 'permission_code' => 'admin.system.read', 'group_key' => 'system', 'created_at' => $now, 'updated_at' => $now],
            ['menu_key' => 'system-config', 'parent_key' => 'admins', 'title_key' => 'admin.menu.systemConfig', 'path' => '/admin/system-config', 'icon' => 'Setting', 'sort_order' => 84, 'visible' => 1, 'permission_code' => 'admin.system.read', 'group_key' => 'system', 'created_at' => $now, 'updated_at' => $now],
            ['menu_key' => 'alerts', 'parent_key' => 'admins', 'title_key' => 'admin.menu.alerts', 'path' => '/admin/alerts', 'icon' => 'Bell', 'sort_order' => 85, 'visible' => 1, 'permission_code' => 'admin.system.read', 'group_key' => 'system', 'created_at' => $now, 'updated_at' => $now],

            // Profile Publish
            ['menu_key' => 'admin.profile_publish', 'parent_key' => null, 'title_key' => 'admin.menu.profilePublish', 'path' => '/admin/profile-publish', 'icon' => 'Document', 'sort_order' => 95, 'visible' => 1, 'permission_code' => 'admin.publishes.read', 'group_key' => 'system', 'created_at' => $now, 'updated_at' => $now],
        ];

        DB::table('admin_menu_rule')->insert($menus);
    }
}
