<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Node;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * 节点 API 请求/错误日志中间件（2026-06-23 改造）
 *
 * 记录所有 /api/v1/node/* 和 /api/v1/internal/* 请求：
 *   - method / path / status / latency_ms
 *   - token 前缀（脱敏） / node_code / region
 *   - 异常时记录 error 堆栈
 *
 * 输出到独立 channel `node_api`（daily 滚动，storage/logs/node-api-{date}.log）
 */
final class ApiRequestLog
{
    public function handle(Request $request, Closure $next): Response
    {
        // 只记录 node/* 和 internal/* 路径
        if (! $this->shouldLog($request)) {
            return $next($request);
        }

        $start = microtime(true);

        try {
            /** @var Response $response */
            $response = $next($request);
        } catch (\Throwable $e) {
            $latencyMs = (int) ((microtime(true) - $start) * 1000);
            Log::channel('node_api')->error('api_exception', [
                'method' => $request->method(),
                'path' => $request->path(),
                'token_prefix' => $this->tokenPrefix($request),
                'node_code' => $this->nodeCode($request),
                'status' => 500,
                'latency_ms' => $latencyMs,
                'exception' => get_class($e),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'remote_addr' => $request->ip(),
            ]);
            throw $e;
        }

        $latencyMs = (int) ((microtime(true) - $start) * 1000);
        $status = $response->getStatusCode();

        $context = [
            'method' => $request->method(),
            'path' => $request->path(),
            'token_prefix' => $this->tokenPrefix($request),
            'node_code' => $this->nodeCode($request),
            'region' => $this->nodeRegion($request),
            'status' => $status,
            'latency_ms' => $latencyMs,
            'remote_addr' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 100),
        ];

        // 4xx/5xx 用 error 级别；2xx/3xx 用 info 级别
        if ($status >= 500) {
            Log::channel('node_api')->error('api_request', $context);
        } elseif ($status >= 400) {
            Log::channel('node_api')->warning('api_request', $context);
        } else {
            Log::channel('node_api')->info('api_request', $context);
        }

        return $response;
    }

    private function shouldLog(Request $request): bool
    {
        $path = $request->path();
        return str_starts_with($path, 'api/v1/node/') || str_starts_with($path, 'api/v1/internal/');
    }

    private function tokenPrefix(Request $request): ?string
    {
        $bearer = $request->bearerToken();
        if (! $bearer) {
            return null;
        }
        return substr($bearer, 0, 8) . '***';
    }

    private function nodeCode(Request $request): ?string
    {
        $node = $request->attributes->get('node');
        if ($node instanceof Node) {
            return $node->node_code;
        }
        return null;
    }

    private function nodeRegion(Request $request): ?string
    {
        $node = $request->attributes->get('node');
        if ($node instanceof Node) {
            return $node->region;
        }
        return null;
    }
}
