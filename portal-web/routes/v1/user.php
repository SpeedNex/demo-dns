<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| User Routes - 需要用户认证
|--------------------------------------------------------------------------
|
| 按功能领域拆分到 user/ 子目录：
|   workspace.php     — 仪表盘/工作区/账户设置/黑白名单
|   profiles.php      — Profile 管理/规则/发布
|   teams.php         — 团队管理/成员/邀请
|   api-keys.php      — API Key 管理
|   subscriptions.php — 订阅管理/套餐/支付
|   query-trend.php   — 查询趋势数据
|
*/
Route::prefix('user')->middleware(['auth:api', 'user.only'])->group(function (): void {
    require __DIR__ . '/user/workspace.php';
    require __DIR__ . '/user/profiles.php';
    require __DIR__ . '/user/teams.php';
    require __DIR__ . '/user/api-keys.php';
    require __DIR__ . '/user/subscriptions.php';
    require __DIR__ . '/user/query-trend.php';
});