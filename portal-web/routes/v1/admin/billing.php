<?php

use App\Http\Controllers\Api\V1\Admin\AdminPlanController;
use App\Http\Controllers\Api\V1\Admin\AdminFinanceController;
use App\Http\Controllers\Api\V1\Admin\AdminQueryLogController;
use Illuminate\Support\Facades\Route;

// Plans (套餐管理)
Route::middleware('permission:admin.billing.read')->group(function (): void {
    Route::get('plans', [AdminPlanController::class, 'index']);
});
Route::middleware('permission:admin.billing.write')->group(function (): void {
    Route::post('plans', [AdminPlanController::class, 'store']);
    Route::put('plans/{id}', [AdminPlanController::class, 'update']);
    Route::delete('plans/{id}', [AdminPlanController::class, 'destroy']);
});

// Finance
Route::middleware('permission:admin.finance.read')->group(function (): void {
    Route::get('finance/bills', [AdminFinanceController::class, 'bills']);
    Route::get('finance/bills/export', [AdminFinanceController::class, 'billExport']);
    Route::get('finance/subscriptions', [AdminFinanceController::class, 'subscriptions']);
    Route::get('finance/subscriptions/{id}', [AdminFinanceController::class, 'subscriptionDetail']);
    Route::get('finance/payment-flows', [AdminFinanceController::class, 'paymentFlows']);
    Route::get('finance/payment-flows/summary', [AdminFinanceController::class, 'paymentFlowsSummary']);
    Route::get('finance/payment-flows/export', [AdminFinanceController::class, 'paymentFlowExport']);
});
Route::middleware('permission:admin.finance.write')->group(function (): void {
    Route::post('finance/subscriptions/{id}/cancel', [AdminFinanceController::class, 'subscriptionCancel']);
    Route::post('finance/subscriptions/{id}/resume', [AdminFinanceController::class, 'subscriptionResume']);
});

// Query Logs
Route::get('query-logs', [AdminQueryLogController::class, 'index'])->middleware('permission:admin.query_logs.read');
Route::post('query-logs/batch-destroy', [AdminQueryLogController::class, 'batchDestroy'])->middleware('permission:admin.query_logs.delete');
Route::delete('query-logs', [AdminQueryLogController::class, 'clearAll'])->middleware('permission:admin.query_logs.delete');
Route::get('profiles', [AdminQueryLogController::class, 'profiles'])->middleware('permission:admin.query_logs.read');