<?php

use App\Http\Controllers\Api\V1\User\ProfileController;
use App\Http\Controllers\Api\V1\User\ProfileRuleController;
use App\Http\Controllers\Api\V1\User\ProfilePublishController;
use Illuminate\Support\Facades\Route;

// Profile 管理
Route::prefix('profiles')->group(function (): void {
    Route::get('', [ProfileController::class, 'index']);
    Route::post('', [ProfileController::class, 'store']);
    Route::post('batch-delete', [ProfileController::class, 'batchDestroy']);
    Route::get('{profile_id}', [ProfileController::class, 'show']);
    Route::put('{profile_id}', [ProfileController::class, 'update']);
    Route::delete('{profile_id}', [ProfileController::class, 'destroy']);
    Route::post('{profile_id}/copy', [ProfileController::class, 'copy']);

    // Profile 规则
    Route::get('{profile_id}/rules', [ProfileRuleController::class, 'index']);
    Route::post('{profile_id}/rules', [ProfileRuleController::class, 'store']);
    Route::post('{profile_id}/rules/batch-delete', [ProfileRuleController::class, 'batchDestroy']);
    Route::put('{profile_id}/rules/{rule_id}', [ProfileRuleController::class, 'update']);
    Route::delete('{profile_id}/rules/{rule_id}', [ProfileRuleController::class, 'destroy']);

    // Profile 发布
    Route::post('{profile_id}/publish', [ProfilePublishController::class, 'store']);
});