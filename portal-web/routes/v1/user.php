<?php

use App\Http\Controllers\Api\V1\User\ApiKeyController;
use App\Http\Controllers\Api\V1\Public\AuthController;
use App\Http\Controllers\Api\V1\User\UserDashboardController;
use App\Http\Controllers\Api\V1\User\UserWorkspaceController;
use App\Http\Controllers\Api\V1\User\ProfileController;
use App\Http\Controllers\Api\V1\User\ProfilePublishController;
use App\Http\Controllers\Api\V1\User\ProfileRuleController;
use App\Http\Controllers\Api\V1\User\TeamController;
use App\Http\Controllers\Api\V1\User\OrderController;
use App\Http\Controllers\Api\V1\User\QueryTrendController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| User Routes - 需要用户认证
|--------------------------------------------------------------------------
*/
Route::prefix('user')->middleware(['auth:api', 'user.only'])->group(function (): void {
    Route::get('me', [AuthController::class, 'me']);
    Route::post('logout', [AuthController::class, 'logout']);

    Route::get('dashboard', [UserDashboardController::class, 'overview']);
    Route::get('dns-endpoints', [UserWorkspaceController::class, 'dnsEndpoints']);
    Route::get('top-domains', [UserWorkspaceController::class, 'topDomains']);
    Route::get('devices', [UserWorkspaceController::class, 'devices']);
    Route::prefix('member-center')->group(function (): void {
        Route::get('overview', [UserDashboardController::class, 'overview']);
        Route::get('dns-endpoints', [UserWorkspaceController::class, 'dnsEndpoints']);
        Route::get('devices', [UserWorkspaceController::class, 'devices']);
        Route::get('top-domains', [UserWorkspaceController::class, 'topDomains']);
    });

    Route::match(['get', 'put'], 'settings', [UserWorkspaceController::class, 'settings']);
    Route::put('settings/security', [UserWorkspaceController::class, 'updateSecurity']);
    Route::put('settings/privacy', [UserWorkspaceController::class, 'updatePrivacy']);
    Route::put('settings/parental', [UserWorkspaceController::class, 'updateParental']);
    Route::match(['get', 'put'], 'security', [UserWorkspaceController::class, 'security']);
    Route::match(['get', 'put'], 'privacy', [UserWorkspaceController::class, 'privacy']);
    Route::match(['get', 'put'], 'parental', [UserWorkspaceController::class, 'parental']);
    Route::put('password', [UserWorkspaceController::class, 'password']);
    Route::put('email', [UserWorkspaceController::class, 'email']);

    Route::get('allowlist', [UserWorkspaceController::class, 'allowlist']);
    Route::post('allowlist', [UserWorkspaceController::class, 'createAllowlistRule']);
    Route::put('allowlist/{rule_id}', [UserWorkspaceController::class, 'updateAllowlistRule']);
    Route::delete('allowlist/{rule_id}', [UserWorkspaceController::class, 'deleteAllowlistRule']);
    Route::post('allowlist/batch-delete', [UserWorkspaceController::class, 'batchDeleteAllowlist']);

    Route::get('denylist', [UserWorkspaceController::class, 'denylist']);
    Route::post('denylist', [UserWorkspaceController::class, 'createDenylistRule']);
    Route::put('denylist/{rule_id}', [UserWorkspaceController::class, 'updateDenylistRule']);
    Route::delete('denylist/{rule_id}', [UserWorkspaceController::class, 'deleteDenylistRule']);
    Route::post('denylist/batch-delete', [UserWorkspaceController::class, 'batchDeleteDenylist']);

    Route::get('analytics', [UserWorkspaceController::class, 'analytics']);
    Route::get('logs', [UserWorkspaceController::class, 'logs']);
    Route::get('catalogs', [UserWorkspaceController::class, 'catalogs']);
    Route::get('membership', [UserWorkspaceController::class, 'membership']);
    // upgrade 路由已移除 — 升级必须走订单 + Stripe 支付流程
    Route::get('usage', [UserWorkspaceController::class, 'usage']);
    Route::get('wallet', [UserWorkspaceController::class, 'wallet']);
    Route::post('wallet/recharge', [UserWorkspaceController::class, 'rechargeWallet']);
    Route::get('subscription', [UserWorkspaceController::class, 'subscription']);
    Route::get('referral-link', [UserWorkspaceController::class, 'referralLink']);
    Route::put('devices/{device_id}', [UserWorkspaceController::class, 'updateDevice']);
    Route::delete('devices/{device_id}', [UserWorkspaceController::class, 'deleteDevice']);

    Route::prefix('profiles')->group(function (): void {
        Route::get('', [ProfileController::class, 'index']);
        Route::post('', [ProfileController::class, 'store']);
        Route::post('batch-delete', [ProfileController::class, 'batchDestroy']);
        Route::get('{profile_id}', [ProfileController::class, 'show']);
        Route::put('{profile_id}', [ProfileController::class, 'update']);
        Route::delete('{profile_id}', [ProfileController::class, 'destroy']);
        Route::post('{profile_id}/copy', [ProfileController::class, 'copy']);

        Route::get('{profile_id}/rules', [ProfileRuleController::class, 'index']);
        Route::post('{profile_id}/rules', [ProfileRuleController::class, 'store']);
        Route::post('{profile_id}/rules/batch-delete', [ProfileRuleController::class, 'batchDestroy']);
        Route::put('{profile_id}/rules/{rule_id}', [ProfileRuleController::class, 'update']);
        Route::delete('{profile_id}/rules/{rule_id}', [ProfileRuleController::class, 'destroy']);

        Route::post('{profile_id}/publish', [ProfilePublishController::class, 'store']);
    });

    // Team routes
    Route::get('teams', [TeamController::class, 'index']);
    Route::post('teams', [TeamController::class, 'store']);
    Route::get('teams/{team_id}', [TeamController::class, 'show']);
    Route::put('teams/{team_id}', [TeamController::class, 'update']);
    Route::delete('teams/{team_id}', [TeamController::class, 'destroy']);
    Route::post('teams/{team_id}/leave', [TeamController::class, 'leaveTeam']);
    Route::post('teams/{team_id}/transfer-ownership', [TeamController::class, 'transferOwnership']);
    Route::get('teams/{team_id}/members', [TeamController::class, 'members']);
    Route::put('teams/{team_id}/members/{user_id}/role', [TeamController::class, 'updateMemberRole']);
    Route::delete('teams/{team_id}/members/{user_id}', [TeamController::class, 'removeMember']);
    Route::post('teams/{team_id}/switch', [TeamController::class, 'switchTeam']);
    Route::get('teams/{team_id}/invitations', [TeamController::class, 'invitations']);
    Route::post('teams/{team_id}/invitations', [TeamController::class, 'invite']);
    Route::delete('teams/{team_id}/invitations/{invitation_id}', [TeamController::class, 'cancelInvitation']);
    Route::post('teams/{team_id}/invitations/batch-cancel', [TeamController::class, 'batchCancelInvitations']);
    Route::post('teams/accept-invitation', [TeamController::class, 'acceptInvitation']);
    Route::get('teams/invitations/pending', [TeamController::class, 'pendingInvitations']);

    // API Key routes
    Route::get('api-keys', [ApiKeyController::class, 'index']);
    Route::post('api-keys', [ApiKeyController::class, 'store']);
    Route::delete('api-keys/{key_id}', [ApiKeyController::class, 'destroy']);

    // User Orders + Stripe Checkout (member-facing purchase loop)
    Route::prefix('orders')->group(function (): void {
        Route::get('', [OrderController::class, 'index']);
        Route::post('', [OrderController::class, 'create']);
        Route::get('{id}', [OrderController::class, 'show']);
        Route::post('{id}/checkout', [OrderController::class, 'checkout']);
        Route::post('{id}/pay-with-wallet', [OrderController::class, 'payWithWallet']);
    });

    // 套餐购买入口
    Route::get('plans', [OrderController::class, 'plans']);

    // 查询趋势数据（会员首页 7 天图表）
    Route::get('query-trend', [QueryTrendController::class, 'index']);
});
