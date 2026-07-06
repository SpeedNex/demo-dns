<?php

declare(strict_types=1);

namespace App\Domain\Auth;

use App\Models\GeoDnsToken;
use App\Models\Node;

/**
 * GeoDNS 安装 token 解析服务（2026-07-06 新建）
 *
 * 与 NodeTokenService 互不干扰：
 *   - NodeTokenService  → 查 dns_resolver_node_tokens（dns-resolver 进程）
 *   - GeoDnsTokenService → 查 dns_geodns_tokens（geodns 进程，2026-07-06 拆分）
 *
 * 解析后返回 Node 实例（装 geodns 程序的 resolver 节点）。
 */
final class GeoDnsTokenService
{
    /**
     * Resolve a geodns entity from a bearer token. Returns null if token is
     * missing, revoked, expired, or the geodns entity is missing.
     *
     * @return array{node:DnsGeodns, token:GeoDnsToken}|null
     */
    public function resolveByToken(string $bearerToken): ?array
    {
        if ($bearerToken === '') {
            return null;
        }

        $hash = hash('sha256', $bearerToken);
        $token = GeoDnsToken::query()
            ->where('token_hash', $hash)
            ->where('status', 'active')
            ->where(function ($q): void {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->first();

        if ($token === null) {
            return null;
        }

        $geodns = $token->geodnsNode;
        if ($geodns === null) {
            return null;
        }

        // best-effort last_used_at 更新 — 不影响主流程
        $token->forceFill(['last_used_at' => now()])->saveQuietly();

        return ['node' => $geodns, 'token' => $token];
    }
}
