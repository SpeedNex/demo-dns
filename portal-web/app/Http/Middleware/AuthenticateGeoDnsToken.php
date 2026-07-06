<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Auth\GeoDnsTokenService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * GeoDNS 节点 token 鉴权中间件（2026-07-06 新建）
 *
 * 与 AuthenticateNodeToken（用于 dns-resolver）的区别：
 *   - AuthenticateNodeToken      → 查 dns_resolver_node_tokens
 *   - AuthenticateGeoDnsToken    → 查 dns_geodns_tokens
 *
 * 解析成功后 request attribute：
 *   - geodns_node          : DnsGeodns 实例
 *   - geodns_token         : GeoDnsToken 实例
 *   - geodns_token_plain   : 明文 token（仅 register 路由使用，回填到 dns_geodns.api_key）
 */
final class AuthenticateGeoDnsToken
{
    public function __construct(
        private readonly GeoDnsTokenService $tokens = new GeoDnsTokenService(),
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $resolved = $this->tokens->resolveByToken((string) $request->bearerToken());

        if ($resolved === null) {
            return new JsonResponse([
                'message' => 'Invalid or missing geodns token.',
            ], 401);
        }

        $request->attributes->set('geodns_node', $resolved['node']);
        $request->attributes->set('geodns_token', $resolved['token']);
        $request->attributes->set('geodns_token_plain', (string) $request->bearerToken());

        return $next($request);
    }
}
