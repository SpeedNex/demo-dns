<?php

namespace App\Domain\Auth;

use App\Models\Node;
use App\Models\NodeToken;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

final class NodeTokenService
{
    private const HMAC_KEY_BYTES = 32;

    /**
     * Issue (or rotate) a node token + a per-node HMAC signing key.
     *
     * The HMAC secret is encrypted at rest and decrypted only at signature
     * verification time in VerifyRequestSignature. The client receives the
     * plaintext secret only once — on issuance — and MUST NOT send it back
     * in subsequent requests. X-Hmac-Key header is no longer accepted.
     *
     * @return array{plain:string,hash:string,hmac_key:string,hmac_key_hash:string}
     */
    public function issueToken(Node $node, string $name = 'default'): array
    {
        $plain = 'ntk_' . Str::lower(Str::random(32));
        $hmacKey = 'hmk_' . Str::lower(Str::random(self::HMAC_KEY_BYTES));

        $hash = hash('sha256', $plain);
        $hmacKeyHash = hash('sha256', $hmacKey);

        NodeToken::updateOrCreate(
            [
                'node_id' => $node->id,
                'name' => $name,
            ],
            [
                'token_hash' => $hash,
                'hmac_key_hash' => $hmacKeyHash,
                'hmac_secret_encrypted' => Crypt::encryptString($hmacKey),
                'revoked_at' => null,
                'expires_at' => null,
                'last_used_at' => null,
                'created_at' => now(),
            ],
        );

        return [
            'plain' => $plain,
            'hash' => $hash,
            'hmac_key' => $hmacKey,
            'hmac_key_hash' => $hmacKeyHash,
        ];
    }

    /**
     * Resolve a node from a bearer token (SHA-256 indexed lookup).
     */
    public function resolveNodeFromBearer(?string $bearerToken): ?Node
    {
        if ($bearerToken === null || $bearerToken === '') {
            return null;
        }

        $hash = hash('sha256', $bearerToken);
        $token = NodeToken::with('node')
            ->where('token_hash', $hash)
            ->whereNull('revoked_at')
            ->first();

        return $token?->node;
    }

    /**
     * 2026-06-22 NEW: 纯 Token 鉴权解析，不再要求 HMAC secret。
     * 替代 resolveWithSecret 供 VerifyRequestSignature 中间件使用。
     *
     * @return array{node:Node, token:NodeToken}|null
     */
    public function resolveByToken(string $bearerToken): ?array
    {
        $hash = hash('sha256', $bearerToken);
        $token = NodeToken::with('node')
            ->where('token_hash', $hash)
            ->whereNull('revoked_at')
            ->first();

        if ($token === null) {
            return null;
        }

        // 更新 last_used_at（best-effort，不影响主流程）
        $token->forceFill(['last_used_at' => now()])->saveQuietly();

        return ['node' => $token->node, 'token' => $token];
    }

    /**
     * Resolve a node token record from a bearer token.
     * Returns null if not found or revoked.
     *
     * V2.3 适配：V2 schema 删除了 hmac_secret_encrypted 列，
     * 改由 resolver 通过 X-Hmac-Key 头传递共享 secret（HMAC-SHA256 验签仍然要求）。
     *
     * @return array{node:Node, token:NodeToken, hmacSecret:string}|null
     */
    public function resolveWithSecret(string $bearerToken, ?string $clientHmacKey = null): ?array
    {
        $hash = hash('sha256', $bearerToken);
        $token = NodeToken::with('node')
            ->where('token_hash', $hash)
            ->whereNull('revoked_at')
            ->first();

        if ($token === null) {
            return null;
        }

        // 优先级：V1 加密列 > V2 X-Hmac-Key 头
        $hmacSecret = null;
        if (! empty($token->hmac_secret_encrypted)) {
            try {
                $hmacSecret = Crypt::decryptString($token->hmac_secret_encrypted);
            } catch (\Exception) {
                $hmacSecret = null;
            }
        }
        if ($hmacSecret === null && $clientHmacKey !== null && $clientHmacKey !== '') {
            $hmacSecret = $clientHmacKey;
        }

        if ($hmacSecret === null) {
            return null;
        }

        return ['node' => $token->node, 'token' => $token, 'hmacSecret' => $hmacSecret];
    }

    /**
     * Legacy method kept for backward compat — no longer used by middleware.
     */
    public function resolveWithHmac(string $bearerToken, string $hmacKey): ?array
    {
        $hash = hash('sha256', $bearerToken);
        $token = NodeToken::with('node')
            ->where('token_hash', $hash)
            ->whereNull('revoked_at')
            ->first();
        if ($token === null || $token->hmac_key_hash === null) {
            return null;
        }

        $expected = hash('sha256', $hmacKey);
        if (! hash_equals((string) $token->hmac_key_hash, $expected)) {
            return null;
        }

        return ['node' => $token->node, 'token' => $token];
    }
}
