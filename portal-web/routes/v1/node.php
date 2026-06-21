<?php

use App\Http\Controllers\Api\V1\Node\ConfigAckController;
use App\Http\Controllers\Api\V1\Node\ConfigPullController;
use App\Http\Controllers\Api\V1\Node\GeoDNSConfigController;
use App\Http\Controllers\Api\V1\Node\HeartbeatController;
use App\Http\Controllers\Api\V1\Node\QueryLogController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Node Routes - DNS Resolver & GeoDNS 节点 API
|--------------------------------------------------------------------------
*/
Route::prefix('node')->group(function (): void {
    // 2026-06-22: 节点安装注册端点（install 后调用），标记节点已注册。
    // 使用 node.token 中间件：仅校验 Bearer token 鉴权。
    Route::post('nodes/register', [App\Http\Controllers\Api\V1\Node\NodeRegisterController::class, 'register'])
        ->middleware(['node.token']);

    // Token 验证（无中间件，安装时用 token 换取 api_key + secret）
    Route::post('tokens/verify', [App\Http\Controllers\Api\V1\Node\TokenVerifyController::class, 'verify']);

    // Resolver 节点 API
    Route::post('nodes/heartbeat', [HeartbeatController::class, 'store'])->middleware(['node.hmac']);
    Route::get('resolver/config', [ConfigPullController::class, 'show'])->middleware(['node.hmac']);
    Route::post('resolver/config/ack', [ConfigAckController::class, 'store'])->middleware(['node.hmac']);
    Route::post('query-logs/batch', [QueryLogController::class, 'batch'])->middleware(['node.hmac']);
    Route::post('devices/seen', [\App\Http\Controllers\Api\V1\Node\DeviceSeenController::class, 'store'])->middleware(['node.hmac']);

    // GeoDNS 节点 API
    Route::get('geodns/config', [GeoDNSConfigController::class, 'show'])->middleware(['node.hmac']);
});
