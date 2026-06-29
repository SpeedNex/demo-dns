<?php

use App\Http\Controllers\Api\V1\User\ApiKeyController;
use Illuminate\Support\Facades\Route;

// API Key 管理
Route::get('api-keys', [ApiKeyController::class, 'index']);
Route::post('api-keys', [ApiKeyController::class, 'store']);
Route::delete('api-keys/{key_id}', [ApiKeyController::class, 'destroy']);