<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function (): array {
    return [
        'app' => 'portal-web',
        'status' => 'ok',
    ];
});

// SPA fallback - serve index.html for all non-API routes
Route::get('/{any}', function () {
    return file_get_contents(public_path('index.html'));
})->where('any', '^(?!api).*$');
