<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Node;

use App\Models\DnsGeodns;
use App\Models\Node;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * GeoDNS 节点注册端点（2026-06-23 重构）
 *
 * GeoDNS 节点注册到独立的 dns_geodns 表中。
 * 通过 bearer token 鉴权（复用 resolver 的 token 系统）。
 * URL: POST /api/v1/node/geodns/register
 */
final class GeoDnsRegisterController
{
    public function register(Request $request): JsonResponse
    {
        $start = microtime(true);

        $validated = $request->validate([
            'installed_at' => 'nullable|date',
            'listen_addr' => 'nullable|string|max:80',
        ]);

        // 从中间件获取已解析的节点（用于鉴权和提取 node_code）
        $resolvedNode = $request->attributes->get('node');
        if (! $resolvedNode || ! $resolvedNode instanceof Node) {
            return response()->json(['error' => ['code' => 'UNAUTHORIZED', 'message' => 'node token required']], 401);
        }

        // 查找或创建 geodns 节点记录
        // region 来自 resolver token 对应的节点（如 "kr", "geodns-kr"）
        $regionCode = strtolower(str_replace('geodns-', '', $resolvedNode->region ?? 'unknown'));
        $node = DnsGeodns::updateOrCreate(
            ['node_code' => $resolvedNode->node_code],
            [
                'node_alias' => DnsGeodns::generateAlias($regionCode),
                'region' => $resolvedNode->region,
                'install_status' => 'installed',
                'last_installed_at' => $validated['installed_at'] ?? now(),
                'last_listen_addr' => $validated['listen_addr'] ?? null,
            ]
        );

        // 签发 api_key
        $apiKeyPlain = null;
        if (Schema::hasColumn('geodns', 'api_key')) {
            $apiKeyPlain = 'ak_' . Str::random(40);
            $node->update([
                'api_key' => hash('sha256', $apiKeyPlain),
                'api_key_issued_at' => now(),
            ]);
        }

        $response = [
            'data' => [
                'node_id' => $node->node_code,
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
        Log::channel('node_api')->info('geodns_register', [
            'method' => $request->method(),
            'path' => $request->path(),
            'node_code' => $node->node_code,
            'region' => $node->region,
            'status' => 200,
            'latency_ms' => $latencyMs,
            'api_key_issued' => $apiKeyPlain !== null,
            'remote_addr' => $request->ip(),
        ]);

        return response()->json($response);
    }
}
