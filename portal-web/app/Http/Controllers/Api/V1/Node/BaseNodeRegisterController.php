<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Node;

use App\Models\Node;
use App\Support\SystemConfigValue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * 节点安装注册端点（统一实现）
 *
 * 2026-06-23 改造：删除 node_type 字段，节点类型由 region 字段区分。
 * 节点身份由 bearer token（AuthenticateNodeToken 中间件）解析得到。
 *
 * 用途：
 *   1) 在 console 节点列表中标记「已注册 / 已安装」
 *   2) **签发并返回 api_key**（明文，仅此一次），节点应缓存到 configs/api_key
 *      之后所有业务请求（heartbeat / config / ...）用 api_key 鉴权。
 */
abstract class BaseNodeRegisterController
{
    /**
     * 子类可覆盖：限定节点 region 前缀（如 'resolver-' / 'geodns-'）。
     * 返回 null 表示不限制。
     */
    protected function expectedRegionPrefix(): ?string
    {
        return null;
    }

    public function register(Request $request): JsonResponse
    {
        $start = microtime(true);

        $validated = $request->validate([
            'installed_at' => 'nullable|date',
            'listen_addr' => 'nullable|string|max:80',
        ]);

        $node = $request->attributes->get('node');
        if (! $node) {
            $this->logError($request, 'node token required', 401, $start);
            return response()->json(['error' => ['code' => 'UNAUTHORIZED', 'message' => 'node token required']], 401);
        }

        $expectedPrefix = $this->expectedRegionPrefix();
        if ($expectedPrefix !== null && ! str_starts_with($node->region ?? '', $expectedPrefix)) {
            $msg = "region prefix mismatch: expected={$expectedPrefix}";
            $this->logError($request, $msg, 400, $start, $node);
            return response()->json([
                'error' => [
                    'code' => 'TYPE_MISMATCH',
                    'message' => "this endpoint only accepts nodes with region prefix '{$expectedPrefix}'",
                ],
            ], 400);
        }

        $updateData = [
            'last_installed_at' => $validated['installed_at'] ?? now(),
            'last_listen_addr' => $validated['listen_addr'] ?? null,
            'install_status' => 'installed',
        ];

        // 签发 api_key
        $apiKeyPlain = null;
        if (Schema::hasColumn('resolver_nodes', 'api_key')) {
            $apiKeyPlain = 'ak_' . Str::random(40);
            $updateData['api_key'] = hash('sha256', $apiKeyPlain);
            $updateData['api_key_issued_at'] = now();
        }

        $node->update($updateData);

        $dnsDomain = SystemConfigValue::field('dns', 'dns_domain');
        if (empty($dnsDomain)) {
            $dnsDomain = $node->domain ?? '';
        }

        $response = [
            'data' => [
                'node_id' => $node->node_code,
                'dns_domain' => $dnsDomain,
                'region' => $node->region,
                'install_status' => $node->install_status,
                'last_installed_at' => $node->last_installed_at?->toIso8601String(),
            ],
        ];

        if ($apiKeyPlain !== null) {
            $response['data']['api_key'] = $apiKeyPlain;
            $response['data']['api_key_path'] = 'configs/api_key';
        }

        $latencyMs = (int) ((microtime(true) - $start) * 1000);
        $this->logInfo($request, $node, 200, $latencyMs, $apiKeyPlain !== null);

        return response()->json($response);
    }

    protected function logInfo(Request $request, Node $node, int $status, int $latencyMs, bool $apiKeyIssued): void
    {
        Log::channel('node_api')->info('register', [
            'method' => $request->method(),
            'path' => $request->path(),
            'node_code' => $node->node_code,
            'region' => $node->region,
            'token_prefix' => $this->tokenPrefix($request),
            'status' => $status,
            'latency_ms' => $latencyMs,
            'api_key_issued' => $apiKeyIssued,
            'remote_addr' => $request->ip(),
        ]);
    }

    protected function logError(Request $request, string $message, int $status, float $start, ?Node $node = null): void
    {
        $latencyMs = (int) ((microtime(true) - $start) * 1000);
        Log::channel('node_api')->error('register', [
            'method' => $request->method(),
            'path' => $request->path(),
            'node_code' => $node?->node_code,
            'region' => $node?->region,
            'token_prefix' => $this->tokenPrefix($request),
            'status' => $status,
            'latency_ms' => $latencyMs,
            'error' => $message,
            'remote_addr' => $request->ip(),
        ]);
    }

    private function tokenPrefix(Request $request): ?string
    {
        $bearer = $request->bearerToken();
        if (! $bearer) {
            return null;
        }
        return substr($bearer, 0, 8) . '***';
    }
}
