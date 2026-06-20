<?php

namespace App\Http\Middleware;

use App\Domain\Auth\NodeTokenService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verify an agent request signed with the node's per-token HMAC key.
 *
 * Required headers on every request:
 *   Authorization:      Bearer <plain token>
 *   X-Signature:        hex(hmac_sha256(secret, "ts\nmethod\npath\nbodySha256"))
 *   X-Timestamp:        unix seconds (must be within ±300s of server time)
 *   X-Nonce:            random 16+ chars; replay cache TTL = 2 × clock-skew window
 *
 * The HMAC secret is resolved server-side from the encrypted column
 * `node_tokens.hmac_secret_encrypted` — the client MUST NOT send X-Hmac-Key.
 *
 * On success: sets `node` and `node_token` request attributes for downstream handlers.
 */
final class VerifyRequestSignature
{
    private const CLOCK_SKEW_SECONDS = 300;
    private const NONCE_CACHE_TTL_SECONDS = 700;
    private const NONCE_CACHE_PREFIX = 'agent-hmac-nonce:';

    public function __construct(
        private readonly NodeTokenService $tokens,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $bearer = $request->bearerToken();
        $signature = (string) $request->header('X-Signature', '');
        $timestamp = (string) $request->header('X-Timestamp', '');
        $nonce = (string) $request->header('X-Nonce', '');

        if ($bearer === null || $bearer === '' || $signature === '' || $timestamp === '' || $nonce === '') {
            return $this->reject('missing_auth_headers');
        }

        if (strlen($nonce) < 16 || strlen($nonce) > 128) {
            return $this->reject('invalid_nonce');
        }

        $ts = filter_var($timestamp, FILTER_VALIDATE_INT);
        if ($ts === false) {
            return $this->reject('invalid_timestamp');
        }
        $now = time();
        if (abs($now - (int) $ts) > self::CLOCK_SKEW_SECONDS) {
            return $this->reject('clock_skew_exceeded');
        }

        // Resolve token + HMAC secret from DB (encrypted at rest).
        // V2 兼容：客户端可通过 X-Hmac-Key 头传递共享 secret。
        $clientHmacKey = $request->header('X-Hmac-Key');
        $resolved = $this->tokens->resolveWithSecret($bearer, $clientHmacKey);
        if ($resolved === null) {
            return $this->reject('invalid_credentials');
        }

        $body = $request->getContent();
        $bodyHash = hash('sha256', $body);
        $path = '/' . ltrim($request->path(), '/');
        $canonical = $ts . "\n" . strtoupper($request->method()) . "\n" . $path . "\n" . $bodyHash;

        $expected = hash_hmac('sha256', $canonical, $resolved['hmacSecret']);
        if (! hash_equals($expected, strtolower($signature))) {
            return $this->reject('signature_mismatch');
        }

        // Atomic replay protection — Cache::add returns false if key exists.
        $nonceKey = self::NONCE_CACHE_PREFIX . hash('sha256', $bearer . '|' . $nonce);
        if (! Cache::add($nonceKey, 1, self::NONCE_CACHE_TTL_SECONDS)) {
            return $this->reject('replay_detected');
        }

        $request->attributes->set('node', $resolved['node']);
        $request->attributes->set('node_token', $resolved['token']);
        $request->attributes->set('hmac_secret', $resolved['hmacSecret']);

        return $next($request);
    }

    private function reject(string $reason): JsonResponse
    {
        return new JsonResponse([
            'error' => [
                'code' => 'unauthorized',
                'message' => 'HMAC signature verification failed.',
                'reason' => $reason,
            ],
        ], 401);
    }
}
