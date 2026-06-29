<?php

use App\Http\Controllers\Api\V1\Admin\AdminPolicyController;
use Illuminate\Support\Facades\Route;

// Policy (UI.md #61/#62/#63) — 策略闭环
Route::middleware('permission:admin.policy.read')->group(function (): void {
    Route::get('policy/nodes', [AdminPolicyController::class, 'indexNodes']);
    Route::get('policy/plans', [AdminPolicyController::class, 'indexPlans']);
});
Route::middleware('permission:admin.policy.write')->group(function (): void {
    Route::post('policy/users/{userId}/snapshot', [AdminPolicyController::class, 'snapshotUser']);
    Route::post('policy/snapshots/{id}/publish', [AdminPolicyController::class, 'publishSnapshot']);
});