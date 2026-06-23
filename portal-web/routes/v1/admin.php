<?php

use App\Http\Controllers\Api\V1\Admin\AdminMenuConfigController;
use App\Http\Controllers\Api\V1\Admin\AdminAlertController;
use App\Http\Controllers\Api\V1\Admin\AdminAuditLogController;
use App\Http\Controllers\Api\V1\Admin\AdminBillingController;
use App\Http\Controllers\Api\V1\Admin\AdminBillingStatsController;
use App\Http\Controllers\Api\V1\Admin\AdminConsoleAuditLogController;
use App\Http\Controllers\Api\V1\Admin\AdminDeviceController;
use App\Http\Controllers\Api\V1\Admin\AdminFinanceController;
use App\Http\Controllers\Api\V1\Admin\AdminGeoDnsController;
use App\Http\Controllers\Api\V1\Admin\AdminMemberCatalogController;
use App\Http\Controllers\Api\V1\Admin\AdminMemberPolicyController;
use App\Http\Controllers\Api\V1\Admin\AdminBlacklistWhitelistController;
use App\Http\Controllers\Api\V1\Admin\AdminNodeController;
use App\Http\Controllers\Api\V1\Admin\AdminPlanController;
use App\Http\Controllers\Api\V1\Admin\AdminPolicyController;
use App\Http\Controllers\Api\V1\Admin\AdminPublishController;
use App\Http\Controllers\Api\V1\Admin\AdminQueryLogController;
use App\Http\Controllers\Api\V1\Admin\AdminRbacController;
use App\Http\Controllers\Api\V1\Admin\AdminAdminsController;
use App\Http\Controllers\Api\V1\Admin\AdminRegionController;
use App\Http\Controllers\Api\V1\Admin\AdminRuleController;
use App\Http\Controllers\Api\V1\Admin\AdminStatsController;
use App\Http\Controllers\Api\V1\Admin\AdminSystemConfigController;
use App\Http\Controllers\Api\V1\Admin\AdminTeamController;
use App\Http\Controllers\Api\V1\Admin\AdminUserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin Routes - 需要 Sanctum 认证 + admin.access 权限
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->middleware(['auth:sanctum', 'admin.only', 'permission:admin.access'])->group(function (): void {
    Route::get('overview', [AdminStatsController::class, 'overview'])->middleware('permission:admin.dashboard.read');
    Route::get('billing-stats', [AdminBillingStatsController::class, 'overview'])->middleware('permission:admin.dashboard.read');

    // User & Device Management
    Route::middleware('permission:admin.users.read')->group(function (): void {
        Route::get('users', [AdminUserController::class, 'index']);
        Route::get('users/{user_id}', [AdminUserController::class, 'show']);
        Route::get('member-catalogs', [AdminMemberCatalogController::class, 'show']);
        Route::get('member-rules', [AdminMemberCatalogController::class, 'rules']);
    });
    Route::middleware('permission:admin.users.write')->group(function (): void {
        Route::post('users', [AdminUserController::class, 'store']);
        Route::put('users/{user_id}', [AdminUserController::class, 'update']);
        Route::delete('users/{user_id}', [AdminUserController::class, 'destroy']);
        Route::post('users/{user_id}/disable', [AdminUserController::class, 'disable']);
        Route::post('users/{user_id}/enable', [AdminUserController::class, 'enable']);
        Route::put('member-catalogs', [AdminMemberCatalogController::class, 'update']);
        Route::delete('member-rules/{id}', [AdminMemberCatalogController::class, 'destroyRule']);
        Route::post('member-rules/batch-destroy', [AdminMemberCatalogController::class, 'batchDestroyRules']);
    });

    Route::middleware('permission:admin.devices.read')->group(function (): void {
        Route::get('devices', [AdminDeviceController::class, 'index']);
        Route::get('devices/{device_id}', [AdminDeviceController::class, 'show']);
    });
    Route::middleware('permission:admin.devices.write')->group(function (): void {
        Route::delete('devices/{device_id}', [AdminDeviceController::class, 'destroy']);
        Route::post('devices/batch-destroy', [AdminDeviceController::class, 'batchDestroy']);
    });

    // Alerts
    Route::get('alerts', [AdminAlertController::class, 'index'])->middleware('permission:admin.alerts.read');
    Route::post('alerts/batch-destroy', [AdminAlertController::class, 'batchDestroy'])->middleware('permission:admin.alerts.write');
    Route::post('alerts/{alert_id}/acknowledge', [AdminAlertController::class, 'acknowledge'])->middleware('permission:admin.alerts.write');

    // Team Management
    Route::middleware('permission:admin.teams.read')->group(function (): void {
        Route::get('teams', [AdminTeamController::class, 'index']);
        Route::get('teams/{team_id}', [AdminTeamController::class, 'show']);
        Route::get('teams/{team_id}/members', [AdminTeamController::class, 'members']);
    });
    Route::middleware('permission:admin.teams.write')->group(function (): void {
        Route::post('teams/{team_id}/disable', [AdminTeamController::class, 'disable']);
        Route::post('teams/{team_id}/enable', [AdminTeamController::class, 'enable']);
    });

    // Audit Logs
    Route::get('audit-logs', [AdminAuditLogController::class, 'index'])->middleware('permission:admin.audit.read');
    Route::prefix('console')->group(function (): void {
        Route::get('audit-logs', [AdminConsoleAuditLogController::class, 'index'])->middleware('permission:admin.audit.read');
        Route::get('audit-logs/export', [AdminConsoleAuditLogController::class, 'export'])->middleware('permission:admin.audit.read');
        Route::post('audit-logs/batch-destroy', [AdminConsoleAuditLogController::class, 'batchDestroy'])->middleware('permission:admin.audit.read');
        Route::delete('audit-logs/{id}', [AdminConsoleAuditLogController::class, 'destroy'])->middleware('permission:admin.audit.read');
        Route::delete('audit-logs', [AdminConsoleAuditLogController::class, 'clear'])->middleware('permission:admin.audit.read');
    });

    // Billing
    Route::middleware('permission:admin.billing.read')->group(function (): void {
        Route::get('billing/balance/{user_id}', [AdminBillingController::class, 'balance']);
        Route::get('billing/bills', [AdminBillingController::class, 'bills']);
        Route::get('billing/export', [AdminBillingController::class, 'export']);
        Route::get('plans', [AdminPlanController::class, 'index']);
    });
    Route::middleware('permission:admin.billing.write')->group(function (): void {
        Route::post('billing/charge', [AdminBillingController::class, 'charge']);
        Route::post('billing/refund', [AdminBillingController::class, 'refund']);
        Route::post('plans', [AdminPlanController::class, 'store']);
        Route::put('plans/{id}', [AdminPlanController::class, 'update']);
        Route::delete('plans/{id}', [AdminPlanController::class, 'destroy']);
    });

    // Finance
    Route::middleware('permission:admin.finance.read')->group(function (): void {
        Route::get('finance/balances', [AdminFinanceController::class, 'balances']);
        Route::get('finance/recharges', [AdminFinanceController::class, 'recharges']);
        Route::get('finance/recharges/export', [AdminFinanceController::class, 'rechargeExport']);
        Route::get('finance/bills', [AdminFinanceController::class, 'bills']);
        Route::get('finance/bills/export', [AdminFinanceController::class, 'billExport']);
        Route::get('finance/refunds', [AdminFinanceController::class, 'refunds']);
        Route::get('finance/refunds/export', [AdminFinanceController::class, 'refundExport']);
    });
    Route::middleware('permission:admin.finance.write')->group(function (): void {
        Route::post('finance/refunds/{id}/approve', [AdminFinanceController::class, 'approveRefund']);
    });

    // RBAC
    Route::middleware('permission:admin.rbac.read')->group(function (): void {
        Route::get('rbac/roles', [AdminRbacController::class, 'roles']);
        Route::get('rbac/roles/{id}/permissions', [AdminRbacController::class, 'rolePermissions']);
        Route::get('rbac/permissions', [AdminRbacController::class, 'permissions']);
        Route::get('rbac/admins', [AdminRbacController::class, 'admins']);
        Route::get('rbac/roles/{id}/menu-rules', [AdminRbacController::class, 'menuRules']);
    });
    Route::middleware('permission:admin.rbac.write')->group(function (): void {
        Route::post('rbac/roles', [AdminRbacController::class, 'createRole']);
        Route::put('rbac/roles/{id}', [AdminRbacController::class, 'updateRole']);
        Route::delete('rbac/roles/{id}', [AdminRbacController::class, 'deleteRole']);
        Route::put('rbac/roles/{id}/permissions', [AdminRbacController::class, 'setRolePermissions']);
        Route::put('rbac/admins/{adminId}/roles', [AdminRbacController::class, 'setAdminRoles']);
        Route::put('rbac/roles/{id}/menu-rules', [AdminRbacController::class, 'setMenuRules']);
    });

    // Admin 账号管理（区别于 User 管理）
    Route::middleware('permission:admin.rbac.read')->group(function (): void {
        Route::get('admins', [AdminAdminsController::class, 'index']);
    });
    Route::middleware('permission:admin.rbac.write')->group(function (): void {
        Route::post('admins', [AdminAdminsController::class, 'store']);
        Route::put('admins/{id}', [AdminAdminsController::class, 'update']);
        Route::delete('admins/{id}', [AdminAdminsController::class, 'destroy']);
    });

    // Query Logs
    Route::get('query-logs', [AdminQueryLogController::class, 'index'])->middleware('permission:admin.query_logs.read');
    Route::post('query-logs/batch-destroy', [AdminQueryLogController::class, 'batchDestroy'])->middleware('permission:admin.query_logs.delete');
    Route::delete('query-logs', [AdminQueryLogController::class, 'clearAll'])->middleware('permission:admin.query_logs.delete');

    // Admin profile listing for filter dropdowns
    Route::get('profiles', [AdminQueryLogController::class, 'profiles'])->middleware('permission:admin.query_logs.read');

    // System Config
    Route::get('system-config', [AdminSystemConfigController::class, 'show'])->middleware('permission:admin.system_config.read');
    Route::put('system-config', [AdminSystemConfigController::class, 'update'])->middleware('permission:admin.system_config.write');

    // Region Management
    Route::middleware('permission:admin.nodes.read')->group(function (): void {
        Route::get('regions', [AdminRegionController::class, 'index']);
    });
    Route::middleware('permission:admin.nodes.write')->group(function (): void {
        Route::post('regions', [AdminRegionController::class, 'store']);
        Route::put('regions/{id}', [AdminRegionController::class, 'update']);
        Route::delete('regions/{id}', [AdminRegionController::class, 'destroy']);
    });

    // Node Management
    Route::middleware('permission:admin.nodes.read')->group(function (): void {
        Route::get('nodes', [AdminNodeController::class, 'index']);
        Route::get('nodes/{nodeId}', [AdminNodeController::class, 'show']);
    });
    Route::middleware('permission:admin.nodes.write')->group(function (): void {
        Route::post('nodes', [AdminNodeController::class, 'store']);
        Route::put('nodes/{nodeId}', [AdminNodeController::class, 'update']);
        Route::delete('nodes/{nodeId}', [AdminNodeController::class, 'destroy']);
        Route::post('nodes/batch-destroy', [AdminNodeController::class, 'batchDestroy']);

        Route::post('nodes/{nodeId}/tokens', [AdminNodeController::class, 'issueToken']);
        Route::post('nodes/{nodeId}/tokens/{tokenId}/revoke', [AdminNodeController::class, 'revokeToken']);
    });

    // Publish Tasks
    Route::middleware('permission:admin.publishes.read')->group(function (): void {
        Route::get('publishes', [AdminPublishController::class, 'index']);
    });
    Route::middleware('permission:admin.publishes.write')->group(function (): void {
        Route::post('publishes', [AdminPublishController::class, 'store']);
        Route::post('publishes/{taskId}/retry', [AdminPublishController::class, 'retry']);
        Route::post('publishes/{taskId}/cancel', [AdminPublishController::class, 'cancel']);
        Route::post('publishes/batch-retry', [AdminPublishController::class, 'batchRetry']);
        Route::post('publishes/batch-cancel', [AdminPublishController::class, 'batchCancel']);
        Route::post('publishes/cleanup-completed', [AdminPublishController::class, 'cleanupCompleted']);
    });

    // GeoDNS
    Route::middleware('permission:admin.geo_dns.read')->group(function (): void {
        Route::get('geo-dns', [AdminGeoDnsController::class, 'index']);
        Route::get('geo-dns/{id}', [AdminGeoDnsController::class, 'show']);
        Route::post('geo-dns/seed-demo', [AdminGeoDnsController::class, 'seedDemo']);
        Route::post('geo-dns/bind-local', [AdminGeoDnsController::class, 'bindLocalNode']);
    });
    Route::middleware('permission:admin.geo_dns.write')->group(function (): void {
        Route::post('geo-dns', [AdminGeoDnsController::class, 'store']);
        Route::post('geo-dns/{id}/token', [AdminGeoDnsController::class, 'issueToken']);
        Route::put('geo-dns/{id}', [AdminGeoDnsController::class, 'update']);
        Route::delete('geo-dns/{id}', [AdminGeoDnsController::class, 'destroy']);
        Route::post('geo-dns/batch-destroy', [AdminGeoDnsController::class, 'batchDestroy']);
    });

    // Rule Library
    Route::middleware('permission:admin.rules.read')->group(function (): void {
        Route::get('rules', [AdminRuleController::class, 'index']);
        Route::get('rules/{id}', [AdminRuleController::class, 'show']);
    });
    Route::middleware('permission:admin.rules.write')->group(function (): void {
        Route::post('rules', [AdminRuleController::class, 'store']);
        Route::put('rules/{id}', [AdminRuleController::class, 'update']);
        Route::delete('rules/{id}', [AdminRuleController::class, 'destroy']);
        Route::post('rules/{name}/sync', [AdminRuleController::class, 'sync']);
        Route::post('rules/batch-destroy', [AdminRuleController::class, 'batchDestroy']);
    });

    // Policy (UI.md #61/#62/#63) — 策略闭环
    Route::middleware('permission:admin.policy.read')->group(function (): void {
        Route::get('policy/nodes', [AdminPolicyController::class, 'indexNodes']);
        Route::get('policy/plans', [AdminPolicyController::class, 'indexPlans']);
    });
    Route::middleware('permission:admin.policy.write')->group(function (): void {
        Route::post('policy/users/{userId}/snapshot', [AdminPolicyController::class, 'snapshotUser']);
        Route::post('policy/snapshots/{id}/publish', [AdminPolicyController::class, 'publishSnapshot']);
    });

    // 会员策略 + 黑白名单 — UI.md 后台导航
    Route::middleware('permission:admin.users.read')->group(function (): void {
        Route::get('member-policies', [AdminMemberPolicyController::class, 'index']);
        Route::get('blacklist-whitelist', [AdminBlacklistWhitelistController::class, 'index']);
    });

    // Menu Config — 菜单导航配置
    Route::middleware('permission:admin.system_config.read')->group(function (): void {
        Route::get('menu-config', [AdminMenuConfigController::class, 'index']);
    });
    Route::middleware('permission:admin.system_config.write')->group(function (): void {
        Route::put('menu-config', [AdminMenuConfigController::class, 'update']);
        Route::put('menu-config/visibility', [AdminMenuConfigController::class, 'updateVisibility']);
    });
});
