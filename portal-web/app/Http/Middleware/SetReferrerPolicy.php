<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 2026-06-30: 全局设置 Referrer-Policy 响应头。
 *
 * 目的：使用 strict-origin-when-cross-origin 策略
 *   - 同源请求：发送完整 URL
 *   - 跨域请求（HTTPS→HTTPS）：仅发送 origin（无 path / query）
 *   - 降级（HTTPS→HTTP）：不发送 referrer
 *
 * 效果：js.stripe.com / m.stripe.network 等跨域资源加载时，浏览器不会带上
 *   我们站点的完整 URL（避免泄露 token / 用户信息），从而降低 Safari ITP、
 *   Firefox ETP 在控制台打印 "Tracking Prevention blocked access to storage"
 *   警告的频次。该警告本身不影响 Stripe 支付功能，Stripe 官方文档亦明确这是
 *   预期行为。
 */
final class SetReferrerPolicy
{
    public function handle(Request $request, Closure $next): mixed
    {
        $response = $next($request);
        if ($response instanceof Response) {
            $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        }
        return $response;
    }
}
