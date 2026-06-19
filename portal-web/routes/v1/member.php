<?php

use App\Http\Controllers\Api\V1\Member\ApiKeyController;
use App\Http\Controllers\Api\V1\Public\AuthController;
use App\Http\Controllers\Api\V1\Member\MemberCenterController;
use App\Http\Controllers\Api\V1\Member\MemberWorkspaceController;
use App\Http\Controllers\Api\V1\Member\ProfileController;
use App\Http\Controllers\Api\V1\Member\ProfilePublishController;
use App\Http\Controllers\Api\V1\Member\ProfileRuleController;
use App\Http\Controllers\Api\V1\Member\TeamController;
use App\Http\Controllers\Api\V1\User\OrderController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Member Routes - 需要用户认证
|--------------------------------------------------------------------------
*/
Route::prefix('member')->middleware('auth:api')->group(function (): void {
    Route::get('me', [AuthController::class, 'me']);
    Route::post('logout', [AuthController::class, 'logout']);

    Route::get('member-center/overview', [MemberCenterController::class, 'overview']);
    Route::get('member-center/dns-endpoints', [MemberWorkspaceController::class, 'dnsEndpoints']);
    Route::get('member-center/top-domains', [MemberWorkspaceController::class, 'topDomains']);
    Route::get('member-center/devices', [MemberWorkspaceController::class, 'devices']);

    Route::match(['get', 'put'], 'settings', [MemberWorkspaceController::class, 'settings']);
    Route::put('settings/security', [MemberWorkspaceController::class, 'updateSecurity']);
    Route::put('settings/privacy', [MemberWorkspaceController::class, 'updatePrivacy']);
    Route::put('settings/parental', [MemberWorkspaceController::class, 'updateParental']);
    Route::match(['get', 'put'], 'security', [MemberWorkspaceController::class, 'security']);
    Route::match(['get', 'put'], 'privacy', [MemberWorkspaceController::class, 'privacy']);
    Route::match(['get', 'put'], 'parental', [MemberWorkspaceController::class, 'parental']);
    Route::put('password', [MemberWorkspaceController::class, 'password']);

    Route::get('allowlist', [MemberWorkspaceController::class, 'allowlist']);
    Route::post('allowlist', [MemberWorkspaceController::class, 'createAllowlistRule']);
    Route::put('allowlist/{rule_id}', [MemberWorkspaceController::class, 'updateAllowlistRule']);
    Route::delete('allowlist/{rule_id}', [MemberWorkspaceController::class, 'deleteAllowlistRule']);
    Route::post('allowlist/batch-delete', [MemberWorkspaceController::class, 'batchDeleteAllowlist']);

    Route::get('denylist', [MemberWorkspaceController::class, 'denylist']);
    Route::post('denylist', [MemberWorkspaceController::class, 'createDenylistRule']);
    Route::put('denylist/{rule_id}', [MemberWorkspaceController::class, 'updateDenylistRule']);
    Route::delete('denylist/{rule_id}', [MemberWorkspaceController::class, 'deleteDenylistRule']);
    Route::post('denylist/batch-delete', [MemberWorkspaceController::class, 'batchDeleteDenylist']);

    Route::get('analytics', [MemberWorkspaceController::class, 'analytics']);
    Route::get('logs', [MemberWorkspaceController::class, 'logs']);
    Route::get('catalogs', [MemberWorkspaceController::class, 'catalogs']);
    Route::get('membership', [MemberWorkspaceController::class, 'membership']);
    Route::post('upgrade', [MemberWorkspaceController::class, 'upgrade']);
    Route::get('usage', [MemberWorkspaceController::class, 'usage']);
    Route::get('wallet', [MemberWorkspaceController::class, 'wallet']);
    Route::post('wallet/recharge', [MemberWorkspaceController::class, 'rechargeWallet']);
    Route::get('subscription', [MemberWorkspaceController::class, 'subscription']);
    Route::get('referral-link', [MemberWorkspaceController::class, 'referralLink']);
    Route::put('devices/{device_id}', [MemberWorkspaceController::class, 'updateDevice']);
    Route::delete('devices/{device_id}', [MemberWorkspaceController::class, 'deleteDevice']);

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
    Route::prefix('user/orders')->group(function (): void {
        Route::get('', [OrderController::class, 'index']);
        Route::post('', [OrderController::class, 'create']);
        Route::get('{id}', [OrderController::class, 'show']);
        Route::post('{id}/checkout', [OrderController::class, 'checkout']);
    });
});

Route::prefix('user')->middleware('auth:api')->group(function (): void {
    Route::prefix('orders')->group(function (): void {
        Route::get('', [OrderController::class, 'index']);
        Route::post('', [OrderController::class, 'create']);
        Route::get('{id}', [OrderController::class, 'show']);
        Route::post('{id}/checkout', [OrderController::class, 'checkout']);
    });
});
