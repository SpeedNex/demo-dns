<?php

use App\Http\Controllers\Api\V1\Node\ConfigAckController;
use App\Http\Controllers\Api\V1\Node\ConfigPullController;
use App\Http\Controllers\Api\V1\Node\DeviceSeenController;
use App\Http\Controllers\Api\V1\Node\GeoDNSConfigController;
use App\Http\Controllers\Api\V1\Node\GeoDnsRegisterController;
use App\Http\Controllers\Api\V1\Node\HeartbeatController;
use App\Http\Controllers\Api\V1\Node\NodeRegisterController;
use App\Http\Controllers\Api\V1\Node\QueryLogController;
use App\Http\Controllers\Api\V1\Node\TokenVerifyController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Node Routes - DNS Resolver & GeoDNS 节点 API
|--------------------------------------------------------------------------
|
| 路径规范（2026-06-21 重构）：
|   /api/v1/node/                              ← 节点 API 根
|     ├── tokens/verify                        ← 通用：token 验证
|     ├── heartbeat                            ← 通用：心跳上报（dns-resolver + geodns 共用）
|     ├── dns-resolver/                        ← DNS 解析节点
|     │   ├── register                         (POST)  安装注册
|     │   ├── config                           (GET)   拉取配置
|     │   ├── config/ack                       (POST)  确认配置
|     │   ├── query-logs                       (POST)  上报查询日志
|     │   └── devices/seen                     (POST)  上报设备
|     └── geodns/                              ← GeoDNS 节点
|         ├── register                         (POST)  安装注册
|         └── config                           (GET)   拉取 geo 配置
*/
Route::prefix('node')->group(function (): void {

    // === 共用端点（顶层） ===
    Route::post('tokens/verify', [TokenVerifyController::class, 'verify']);
    Route::post('heartbeat', [HeartbeatController::class, 'store'])->middleware(['node.hmac']);

    // === DNS Resolver 节点 ===
    Route::prefix('dns-resolver')->group(function (): void {
        Route::post('register', [NodeRegisterController::class, 'register'])->middleware(['node.token']);
        Route::get('config', [ConfigPullController::class, 'show'])->middleware(['node.hmac']);
        Route::post('config/ack', [ConfigAckController::class, 'store'])->middleware(['node.hmac']);
        Route::post('query-logs', [QueryLogController::class, 'batch'])->middleware(['node.hmac']);
        Route::post('devices/seen', [DeviceSeenController::class, 'store'])->middleware(['node.hmac']);
    });

    // === GeoDNS 节点 ===
    Route::prefix('geodns')->group(function (): void {
        Route::post('register', [GeoDnsRegisterController::class, 'register'])->middleware(['node.token']);
        Route::get('config', [GeoDNSConfigController::class, 'show'])->middleware(['node.hmac']);
    });
});
