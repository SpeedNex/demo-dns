<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin Routes - 需要 Sanctum 认证 + admin.access 权限
|--------------------------------------------------------------------------
|
| 按功能领域拆分到 admin/ 子目录：
|   stats.php       — 仪表盘统计
|   users.php       — 用户/设备/会员策略管理
|   billing.php     — 套餐/财务/查询日志
|   nodes.php       — 节点/区域/GeoDNS 管理
|   rbac.php        — 角色权限/管理员管理
|   publishes.php   — 发布任务/配置发布/发布中心
|   rules.php       — 规则库/分类/品牌/安全数据
|   policy.php      — 策略闭环（快照/发布）
|   settings.php    — 系统配置/告警/团队/审计日志
|
*/
Route::prefix('admin')->middleware(['auth:sanctum', 'admin.only', 'permission:admin.access'])->group(function (): void {
    require __DIR__ . '/admin/stats.php';
    require __DIR__ . '/admin/users.php';
    require __DIR__ . '/admin/billing.php';
    require __DIR__ . '/admin/nodes.php';
    require __DIR__ . '/admin/rbac.php';
    require __DIR__ . '/admin/publishes.php';
    require __DIR__ . '/admin/rules.php';
    require __DIR__ . '/admin/settings.php';
    require __DIR__ . '/admin/policy.php';
});