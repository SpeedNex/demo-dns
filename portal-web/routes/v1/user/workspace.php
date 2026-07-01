<?php

use App\Http\Controllers\Api\V1\Public\AuthController;
use App\Http\Controllers\Api\V1\User\UserDashboardController;
use App\Http\Controllers\Api\V1\User\UserWorkspaceController;
use Illuminate\Support\Facades\Route;

// 账户 & 退出
Route::get('me', [AuthController::class, 'me']);
Route::post('logout', [AuthController::class, 'logout']);

// 仪表盘 & 工作区
Route::get('dashboard', [UserDashboardController::class, 'overview']);
Route::get('dns-endpoints', [UserWorkspaceController::class, 'dnsEndpoints']);
Route::get('top-domains', [UserWorkspaceController::class, 'topDomains']);
Route::get('devices', [UserWorkspaceController::class, 'devices']);
Route::get('analytics', [UserWorkspaceController::class, 'analytics']);
Route::get('logs', [UserWorkspaceController::class, 'logs']);
Route::get('catalogs', [UserWorkspaceController::class, 'catalogs']);
Route::get('membership', [UserWorkspaceController::class, 'membership']);
Route::get('payment-methods', [UserWorkspaceController::class, 'paymentMethods']);
Route::get('usage', [UserWorkspaceController::class, 'usage']);
Route::get('subscription', [UserWorkspaceController::class, 'subscription']);
Route::get('referral-link', [UserWorkspaceController::class, 'referralLink']);
Route::get('rule-sources', [UserWorkspaceController::class, 'ruleSources']);
Route::put('devices/{device_id}', [UserWorkspaceController::class, 'updateDevice']);
Route::delete('devices/{device_id}', [UserWorkspaceController::class, 'deleteDevice']);

// 用户设置
Route::match(['get', 'put'], 'settings', [UserWorkspaceController::class, 'settings']);
Route::put('settings/security', [UserWorkspaceController::class, 'updateSecurity']);
Route::put('settings/privacy', [UserWorkspaceController::class, 'updatePrivacy']);
Route::put('settings/parental', [UserWorkspaceController::class, 'updateParental']);
Route::match(['get', 'put'], 'security', [UserWorkspaceController::class, 'security']);
Route::match(['get', 'put'], 'privacy', [UserWorkspaceController::class, 'privacy']);
Route::match(['get', 'put'], 'parental', [UserWorkspaceController::class, 'parental']);
Route::put('password', [UserWorkspaceController::class, 'password']);
Route::put('email', [UserWorkspaceController::class, 'email']);

// 白名单 / 黑名单规则
Route::get('allowlist', [UserWorkspaceController::class, 'allowlist']);
Route::post('allowlist', [UserWorkspaceController::class, 'createAllowlistRule']);
Route::put('allowlist/{rule_id}', [UserWorkspaceController::class, 'updateAllowlistRule']);
Route::delete('allowlist/{rule_id}', [UserWorkspaceController::class, 'deleteAllowlistRule']);
Route::post('allowlist/batch-delete', [UserWorkspaceController::class, 'batchDeleteAllowlist']);

Route::get('blocklist', [UserWorkspaceController::class, 'blocklist']);
Route::post('blocklist', [UserWorkspaceController::class, 'createBlocklistRule']);
Route::put('blocklist/{rule_id}', [UserWorkspaceController::class, 'updateBlocklistRule']);
Route::delete('blocklist/{rule_id}', [UserWorkspaceController::class, 'deleteBlocklistRule']);
Route::post('blocklist/batch-delete', [UserWorkspaceController::class, 'batchDeleteBlocklist']);