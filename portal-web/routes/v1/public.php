<?php

use App\Http\Controllers\Api\V1\Public\AuthController;
use App\Http\Controllers\Api\V1\Public\PublicConfigController;
use App\Http\Controllers\Api\V1\StripeWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Routes - 无需认证
|--------------------------------------------------------------------------
*/
Route::prefix('public/auth')->group(function (): void {
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('login');
});

Route::post('admin/login', [AuthController::class, 'adminLogin'])->middleware('throttle:10,1');

// 公开 DNS 配置（会员端获取 DNS 域名）
Route::get('dns-config', [PublicConfigController::class, 'dnsConfig']);

// UI.md #74/#75 — Stripe Webhook 入口（无需 Sanctum，靠签名校验）
Route::post('stripe/webhook', [StripeWebhookController::class, 'handle'])->middleware('throttle:120,1');
