<?php

use App\Http\Controllers\Api\V1\Admin\AdminPublishController;
use App\Http\Controllers\Api\V1\Admin\AdminPublishCenterController;
use Illuminate\Support\Facades\Route;

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
    Route::post('publishes/clear-cache', [AdminPublishController::class, 'clearCache']);
});

// Profile Publish (配置文件发布管理)
Route::middleware('permission:admin.publishes.read')->group(function (): void {
    Route::get('profile-publish', [AdminPublishController::class, 'profilePublishList']);
});
Route::middleware('permission:admin.publishes.write')->group(function (): void {
    Route::post('profile-publish/{profileId}', [AdminPublishController::class, 'publishProfile']);
    Route::post('profile-publish-all', [AdminPublishController::class, 'syncAll']);
});

// Publish Center
Route::middleware('permission:admin.publishes.read')->group(function (): void {
    Route::get('publish-center/versions', [AdminPublishCenterController::class, 'versions']);
});
Route::middleware('permission:admin.publishes.write')->group(function (): void {
    Route::post('publish-center/sync-all', [AdminPublishCenterController::class, 'syncAll']);
    Route::post('publish-center/rollback/{versionId}', [AdminPublishCenterController::class, 'rollback']);
});