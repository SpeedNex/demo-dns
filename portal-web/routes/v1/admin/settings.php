<?php

use App\Http\Controllers\Api\V1\Admin\AdminSystemConfigController;
use App\Http\Controllers\Api\V1\Admin\AdminProtectionPolicyController;
use App\Http\Controllers\Api\V1\Admin\AdminMenuConfigController;
use App\Http\Controllers\Api\V1\Admin\AdminAlertController;
use App\Http\Controllers\Api\V1\Admin\AdminTeamController;
use App\Http\Controllers\Api\V1\Admin\AdminAuditLogController;
use App\Http\Controllers\Api\V1\Admin\AdminConsoleAuditLogController;
use Illuminate\Support\Facades\Route;

// System Config
Route::get('system-config', [AdminSystemConfigController::class, 'show'])->middleware('permission:admin.system_config.read');
Route::put('system-config', [AdminSystemConfigController::class, 'update'])->middleware('permission:admin.system_config.write');

// Protection Policies
Route::middleware('permission:admin.system_config.read')->group(function (): void {
    Route::get('protection-policies', [AdminProtectionPolicyController::class, 'show']);
    Route::get('protection-policies/export', [AdminProtectionPolicyController::class, 'export']);
});
Route::middleware('permission:admin.system_config.write')->group(function (): void {
    Route::put('protection-policies', [AdminProtectionPolicyController::class, 'update']);
    Route::post('protection-policies/import', [AdminProtectionPolicyController::class, 'import']);
});

// Menu Config
Route::middleware('permission:admin.system_config.read')->group(function (): void {
    Route::get('menu-config', [AdminMenuConfigController::class, 'index']);
});
Route::middleware('permission:admin.system_config.write')->group(function (): void {
    Route::put('menu-config', [AdminMenuConfigController::class, 'update']);
    Route::put('menu-config/visibility', [AdminMenuConfigController::class, 'updateVisibility']);
    Route::post('menu-config', [AdminMenuConfigController::class, 'store']);
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