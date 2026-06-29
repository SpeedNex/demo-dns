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
Route::prefix('auth')->group(function (): void {
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('login');
});

Route::post('admin/login', [AuthController::class, 'adminLogin'])->middleware('throttle:10,1');

// 公开 DNS 配置（会员端获取 DNS 域名）
Route::get('dns-config', [PublicConfigController::class, 'dnsConfig']);

// UI.md #74/#75 — Stripe Webhook 入口（无需 Sanctum，靠签名校验）
Route::post('stripe/webhook', [StripeWebhookController::class, 'handle'])->middleware('throttle:120,1');

// Build artifacts (installer binaries)
Route::get('build/{path}', function ($path) {
    $baseDir = realpath(public_path('build'));
    if ($baseDir === false) {
        abort(404);
    }
    // 禁止 .. 路径穿越
    $cleanPath = str_replace(['..', "\0"], '', $path);
    $filePath = $baseDir . DIRECTORY_SEPARATOR . $cleanPath;
    // 确保最终路径仍在 build 目录内
    if (! is_file($filePath) || str_starts_with(realpath($filePath), $baseDir) === false) {
        abort(404);
    }
    $mime = mime_content_type($filePath);
    return response()->file($filePath, ['Content-Type' => $mime]);
})->where('path', '[^/].*');
