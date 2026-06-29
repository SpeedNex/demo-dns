<?php

use App\Http\Controllers\Api\V1\User\TeamController;
use Illuminate\Support\Facades\Route;

// Team 管理
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