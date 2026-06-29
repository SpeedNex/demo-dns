<?php

use App\Http\Controllers\Api\V1\Admin\AdminUserController;
use App\Http\Controllers\Api\V1\Admin\AdminMemberCatalogController;
use App\Http\Controllers\Api\V1\Admin\AdminMemberPolicyController;
use App\Http\Controllers\Api\V1\Admin\AdminBlacklistWhitelistController;
use App\Http\Controllers\Api\V1\Admin\AdminDeviceController;
use Illuminate\Support\Facades\Route;

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

// 会员策略 + 黑白名单 — UI.md 后台导航
Route::middleware('permission:admin.users.read')->group(function (): void {
    Route::get('member-policies', [AdminMemberPolicyController::class, 'index']);
    Route::get('blacklist-whitelist', [AdminBlacklistWhitelistController::class, 'index']);
});