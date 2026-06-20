<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AdminRbacSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            ['code' => 'admin.dashboard.read', 'resource' => 'dashboard', 'action' => 'read'],
            ['code' => 'admin.nodes.read', 'resource' => 'nodes', 'action' => 'read'],
            ['code' => 'admin.nodes.write', 'resource' => 'nodes', 'action' => 'write'],
            ['code' => 'admin.users.read', 'resource' => 'users', 'action' => 'read'],
            ['code' => 'admin.users.write', 'resource' => 'users', 'action' => 'write'],
            ['code' => 'admin.devices.read', 'resource' => 'devices', 'action' => 'read'],
            ['code' => 'admin.devices.write', 'resource' => 'devices', 'action' => 'write'],
            ['code' => 'admin.teams.read', 'resource' => 'teams', 'action' => 'read'],
            ['code' => 'admin.teams.write', 'resource' => 'teams', 'action' => 'write'],
            ['code' => 'admin.billing.read', 'resource' => 'billing', 'action' => 'read'],
            ['code' => 'admin.billing.write', 'resource' => 'billing', 'action' => 'write'],
            ['code' => 'admin.finance.read', 'resource' => 'finance', 'action' => 'read'],
            ['code' => 'admin.finance.write', 'resource' => 'finance', 'action' => 'write'],
            ['code' => 'admin.system_config.read', 'resource' => 'system_config', 'action' => 'read'],
            ['code' => 'admin.system_config.write', 'resource' => 'system_config', 'action' => 'write'],
            ['code' => 'admin.audit.read', 'resource' => 'audit', 'action' => 'read'],
            ['code' => 'admin.query_logs.read', 'resource' => 'query_logs', 'action' => 'read'],
            ['code' => 'admin.alerts.read', 'resource' => 'alerts', 'action' => 'read'],
            ['code' => 'admin.alerts.write', 'resource' => 'alerts', 'action' => 'write'],
            ['code' => 'admin.rules.read', 'resource' => 'rules', 'action' => 'read'],
            ['code' => 'admin.rules.write', 'resource' => 'rules', 'action' => 'write'],
            ['code' => 'admin.publishes.read', 'resource' => 'publishes', 'action' => 'read'],
            ['code' => 'admin.publishes.write', 'resource' => 'publishes', 'action' => 'write'],
            ['code' => 'admin.geo_dns.read', 'resource' => 'geo_dns', 'action' => 'read'],
            ['code' => 'admin.geo_dns.write', 'resource' => 'geo_dns', 'action' => 'write'],
            ['code' => 'admin.rbac.read', 'resource' => 'rbac', 'action' => 'read'],
            ['code' => 'admin.rbac.write', 'resource' => 'rbac', 'action' => 'write'],
        ];

        $permissionIds = [];
        foreach ($permissions as $perm) {
            $id = \Illuminate\Support\Facades\DB::table('admin_permissions')->insertGetId([
                'code' => $perm['code'],
                'resource' => $perm['resource'],
                'action' => $perm['action'],
                'description' => ucfirst($perm['action']) . ' ' . str_replace('_', ' ', ucfirst($perm['resource'])),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $permissionIds[$perm['code']] = $id;
        }

        $roles = [
            ['code' => 'super_admin', 'name' => 'Super Admin', 'description' => 'Full access to all admin functions', 'is_builtin' => true],
            ['code' => 'ops_admin', 'name' => 'Ops Admin', 'description' => 'Node and config operations', 'is_builtin' => true],
            ['code' => 'billing_admin', 'name' => 'Billing Admin', 'description' => 'Plans, wallets, refunds', 'is_builtin' => true],
        ];

        $roleIds = [];
        foreach ($roles as $role) {
            $id = \Illuminate\Support\Facades\DB::table('admin_roles')->insertGetId([
                'code' => $role['code'],
                'name' => $role['name'],
                'description' => $role['description'],
                'is_builtin' => $role['is_builtin'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $roleIds[$role['code']] = $id;
        }

        // Super admin gets all permissions
        foreach ($permissionIds as $permCode => $permId) {
            if (isset($roleIds['super_admin'])) {
                \Illuminate\Support\Facades\DB::table('admin_role_permissions')->insertOrIgnore([
                    'admin_role_id' => $roleIds['super_admin'],
                    'admin_permission_id' => $permId,
                ]);
            }
        }

        // Ops admin gets node, system_config, rules permissions
        $opsPerms = ['admin.nodes.read', 'admin.nodes.write', 'admin.system_config.read', 'admin.system_config.write', 'admin.rules.read', 'admin.rules.write', 'admin.dashboard.read', 'admin.query_logs.read', 'admin.publishes.read', 'admin.publishes.write', 'admin.geo_dns.read', 'admin.geo_dns.write', 'admin.alerts.read', 'admin.alerts.write'];
        foreach ($opsPerms as $permCode) {
            if (isset($permissionIds[$permCode], $roleIds['ops_admin'])) {
                \Illuminate\Support\Facades\DB::table('admin_role_permissions')->insertOrIgnore([
                    'admin_role_id' => $roleIds['ops_admin'],
                    'admin_permission_id' => $permissionIds[$permCode],
                ]);
            }
        }

        // Billing admin gets billing permissions
        $billingPerms = ['admin.billing.read', 'admin.billing.write', 'admin.finance.read', 'admin.finance.write', 'admin.dashboard.read'];
        foreach ($billingPerms as $permCode) {
            if (isset($permissionIds[$permCode], $roleIds['billing_admin'])) {
                \Illuminate\Support\Facades\DB::table('admin_role_permissions')->insertOrIgnore([
                    'admin_role_id' => $roleIds['billing_admin'],
                    'admin_permission_id' => $permissionIds[$permCode],
                ]);
            }
        }

        $this->command->info('Admin RBAC seeded: ' . count($permissions) . ' permissions, ' . count($roles) . ' roles');
    }
}
