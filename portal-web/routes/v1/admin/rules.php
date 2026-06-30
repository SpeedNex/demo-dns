<?php

use App\Http\Controllers\Api\V1\Admin\AdminRuleController;
use App\Http\Controllers\Api\V1\Admin\AdminCategoryController;
use App\Http\Controllers\Api\V1\Admin\AdminBrandController;
use App\Http\Controllers\Api\V1\Admin\AdminRuleItemController;
use App\Http\Controllers\Api\V1\Admin\AdminSecurityDataController;
use Illuminate\Support\Facades\Route;

// Rule Library
Route::middleware('permission:admin.rules.read')->group(function (): void {
    Route::get('rules', [AdminRuleController::class, 'index']);
    Route::get('rules/items', [AdminRuleItemController::class, 'index']);
    Route::get('rules/{id}', [AdminRuleController::class, 'show'])->whereNumber('id');
    Route::get('rule-categories', [AdminCategoryController::class, 'index']);
    Route::get('rule-categories/options', [AdminCategoryController::class, 'options']);
    Route::get('brands', [AdminBrandController::class, 'index']);
    Route::get('brands/export', [AdminBrandController::class, 'export']);
});
Route::middleware('permission:admin.rules.write')->group(function (): void {
    Route::post('rules', [AdminRuleController::class, 'store']);
    Route::put('rules/{id}', [AdminRuleController::class, 'update'])->whereNumber('id');
    Route::delete('rules/{id}', [AdminRuleController::class, 'destroy'])->whereNumber('id');
    Route::post('rules/{name}/sync', [AdminRuleController::class, 'sync']);
    Route::post('rules/batch-destroy', [AdminRuleController::class, 'batchDestroy']);
    Route::delete('rules/items/{id}', [AdminRuleItemController::class, 'destroy']);
    Route::post('rules/items/batch-destroy', [AdminRuleItemController::class, 'batchDestroy']);
    Route::post('rules/items/import', [AdminRuleItemController::class, 'import']);
    Route::post('rule-categories', [AdminCategoryController::class, 'store']);
    Route::put('rule-categories/{id}', [AdminCategoryController::class, 'update']);
    Route::delete('rule-categories/{id}', [AdminCategoryController::class, 'destroy']);
    Route::post('rule-categories/batch-destroy', [AdminCategoryController::class, 'batchDestroy']);
    Route::post('brands', [AdminBrandController::class, 'store']);
    Route::put('brands/{id}', [AdminBrandController::class, 'update']);
    Route::delete('brands/{id}', [AdminBrandController::class, 'destroy']);
    Route::post('brands/import', [AdminBrandController::class, 'import']);
});

// Security Data (DDNS / Parked / TLD / AllowList / BlockList)
Route::middleware('permission:admin.rules.read')->group(function (): void {
    Route::get('security-data/summary', [AdminSecurityDataController::class, 'summary']);
    Route::get('security-data/{group}', [AdminSecurityDataController::class, 'index']);
});
Route::middleware('permission:admin.rules.write')->group(function (): void {
    Route::post('security-data/{group}', [AdminSecurityDataController::class, 'store']);
    Route::delete('security-data/{group}/{id}', [AdminSecurityDataController::class, 'destroy']);
    Route::post('security-data/{group}/import', [AdminSecurityDataController::class, 'batchImport']);
});