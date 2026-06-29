<?php

use App\Http\Controllers\Api\V1\Admin\AdminNodeController;
use App\Http\Controllers\Api\V1\Admin\AdminRegionController;
use App\Http\Controllers\Api\V1\Admin\AdminGeoDnsController;
use Illuminate\Support\Facades\Route;

// Region Management
Route::middleware('permission:admin.nodes.read')->group(function (): void {
    Route::get('regions', [AdminRegionController::class, 'index']);
});
Route::middleware('permission:admin.nodes.write')->group(function (): void {
    Route::post('regions', [AdminRegionController::class, 'store']);
    Route::put('regions/{id}', [AdminRegionController::class, 'update']);
    Route::delete('regions/{id}', [AdminRegionController::class, 'destroy']);
});

// Node Management
Route::middleware('permission:admin.nodes.read')->group(function (): void {
    Route::get('nodes', [AdminNodeController::class, 'index']);
    Route::get('nodes/{nodeId}', [AdminNodeController::class, 'show']);
});
Route::middleware('permission:admin.nodes.write')->group(function (): void {
    Route::post('nodes', [AdminNodeController::class, 'store']);
    Route::put('nodes/{nodeId}', [AdminNodeController::class, 'update']);
    Route::delete('nodes/{nodeId}', [AdminNodeController::class, 'destroy']);
    Route::post('nodes/batch-destroy', [AdminNodeController::class, 'batchDestroy']);
    Route::post('nodes/{nodeId}/tokens', [AdminNodeController::class, 'issueToken']);
    Route::post('nodes/{nodeId}/tokens/{tokenId}/revoke', [AdminNodeController::class, 'revokeToken']);
});

// GeoDNS
Route::middleware('permission:admin.geo_dns.read')->group(function (): void {
    Route::get('geo-dns', [AdminGeoDnsController::class, 'index']);
    Route::get('geo-dns/{id}', [AdminGeoDnsController::class, 'show']);
    Route::post('geo-dns/seed-demo', [AdminGeoDnsController::class, 'seedDemo']);
    Route::post('geo-dns/bind-local', [AdminGeoDnsController::class, 'bindLocalNode']);
});
Route::middleware('permission:admin.geo_dns.write')->group(function (): void {
    Route::post('geo-dns', [AdminGeoDnsController::class, 'store']);
    Route::post('geo-dns/{id}/token', [AdminGeoDnsController::class, 'issueToken']);
    Route::put('geo-dns/{id}', [AdminGeoDnsController::class, 'update']);
    Route::delete('geo-dns/{id}', [AdminGeoDnsController::class, 'destroy']);
    Route::post('geo-dns/batch-destroy', [AdminGeoDnsController::class, 'batchDestroy']);
});