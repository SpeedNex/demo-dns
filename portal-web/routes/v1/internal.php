<?php

use App\Http\Controllers\Api\V1\Internal\HealthViewController;
use App\Http\Controllers\Api\V1\Internal\ProfilePublishController as InternalProfilePublishController;
use App\Http\Controllers\Api\V1\Internal\QueryLogReadController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Internal Routes - 内部服务间调用
|--------------------------------------------------------------------------
*/
Route::prefix('internal')->middleware(['api.log', 'shared.token:internal'])->group(function (): void {
    Route::post('profile-publishes', [InternalProfilePublishController::class, 'store']);
    Route::get('query-logs', [QueryLogReadController::class, 'logs']);
    Route::get('query-analytics', [QueryLogReadController::class, 'analytics']);

    // 2026-07-06 fix: geodns health-view 改用 geodns.token 鉴权（dns_geodns_tokens）。
    // 2026-06-22 迁移：从 INTERNAL_SHARED_TOKEN 改为 node.token
    // 2026-07-06 迁移：从 node.token（dns_resolver_node_tokens）改为 geodns.token（dns_geodns_tokens）
    // 原因：geodns 是独立实体，不走 resolver 的 token 系统
    Route::get('geodns/health-view', [HealthViewController::class, 'show'])
        ->middleware('geodns.token')
        ->withoutMiddleware('shared.token:internal');
});
