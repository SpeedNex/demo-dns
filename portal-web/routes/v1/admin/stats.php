<?php

use App\Http\Controllers\Api\V1\Admin\AdminStatsController;
use App\Http\Controllers\Api\V1\Admin\AdminBillingStatsController;
use Illuminate\Support\Facades\Route;

// Dashboard Statistics
Route::get('overview', [AdminStatsController::class, 'overview'])->middleware('permission:admin.dashboard.read');
Route::get('billing-stats', [AdminBillingStatsController::class, 'overview'])->middleware('permission:admin.dashboard.read');