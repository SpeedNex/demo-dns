<?php

use App\Http\Controllers\Api\V1\Admin\AdminRbacController;
use App\Http\Controllers\Api\V1\Admin\AdminAdminsController;
use Illuminate\Support\Facades\Route;

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