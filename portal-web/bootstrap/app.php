<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands()
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->alias([
            'permission' => \App\Http\Middleware\CheckPermission::class,
            'node.token' => \App\Http\Middleware\AuthenticateNodeToken::class,
            'node.api_key' => \App\Http\Middleware\AuthenticateNodeApiKey::class,
            'node.hmac' => \App\Http\Middleware\VerifyRequestSignature::class,
            'shared.token' => \App\Http\Middleware\RequireSharedToken::class,
            'user.only' => \App\Http\Middleware\UserOnly::class,
            'admin.only' => \App\Http\Middleware\AdminOnly::class,
            // 2026-06-22: 节点 API 请求日志
            'api.log' => \App\Http\Middleware\ApiRequestLog::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // 统一 API JSON 错误响应格式：{ error: { code, message } }
        // 见 api.php 路由 — 所有 API 路由前缀均为 api/*，故统一用 ApiResponse 返回 JSON。
        $exceptions->render(function (ValidationException $e, Request $request): \Illuminate\Http\JsonResponse {
            return \App\Helpers\ApiResponse::error('VALIDATION_FAILED', $e->getMessage(), 422);
        });

        $exceptions->render(function (AuthenticationException $e, Request $request): \Illuminate\Http\JsonResponse {
            return \App\Helpers\ApiResponse::error('UNAUTHENTICATED', $e->getMessage(), 401);
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request): \Illuminate\Http\JsonResponse {
            return \App\Helpers\ApiResponse::error('NOT_FOUND', $e->getMessage() ?: 'Resource not found', 404);
        });

        $exceptions->render(function (ModelNotFoundException $e, Request $request): \Illuminate\Http\JsonResponse {
            return \App\Helpers\ApiResponse::error('NOT_FOUND', 'Resource not found', 404);
        });

        $exceptions->render(function (ThrottleRequestsException $e, Request $request): \Illuminate\Http\JsonResponse {
            return \App\Helpers\ApiResponse::error('RATE_LIMITED', 'Too many requests. Please try again later.', 429);
        });
    })->create();
