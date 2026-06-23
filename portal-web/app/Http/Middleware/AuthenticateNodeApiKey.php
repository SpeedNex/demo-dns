<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Node;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

/**
 * 节点 API Key 鉴权中间件
 *
 * 2026-06-21: register 接口签发 api_key 后，节点用 api_key 调用业务接口。
 * 鉴权方式：
 *   - Authorization: Bearer ak_xxx
 *   - 服务端查 nodes.api_key（存的是 hash(sha256)）进行匹配
 *
 * 优势：
 *   1) 不依赖 Laravel APP_KEY，APP_KEY 轮换不影响节点鉴权
 *   2) hash 查找比解密快 ~5x
 *   3) 每个节点 api_key 可独立轮换/吊销
 *
 * 兼容性：
 *   - 防御性检查：若 api_key 列尚未迁移，自动降级为拒绝（生产应先迁移）
 *   - 与 node.token 中间件并存：register 用 token，业务接口用 api_key
 */
final class AuthenticateNodeApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Schema::hasColumn('resolver_nodes', 'api_key')) {
            return new JsonResponse([
                'error' => ['code' => 'NOT_MIGRATED', 'message' => 'resolver_nodes.api_key column not migrated yet'],
            ], 503);
        }

        $key = (string) $request->bearerToken();
        if ($key === '' || ! str_starts_with($key, 'ak_')) {
            return new JsonResponse([
                'error' => ['code' => 'UNAUTHORIZED', 'message' => 'invalid api_key format'],
            ], 401);
        }

        $node = Node::query()
            ->where('api_key', hash('sha256', $key))
            ->where('install_status', '!=', 'failed')
            ->first();

        if (! $node) {
            return new JsonResponse([
                'error' => ['code' => 'UNAUTHORIZED', 'message' => 'invalid api_key'],
            ], 401);
        }

        $request->attributes->set('node', $node);

        return $next($request);
    }
}
