<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Node;

use App\Models\DnsGeodns;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GeoDNS 调度器心跳上报（2026-06-23 新增）
 *
 * 与 resolver 节点心跳（HeartbeatController）不同：
 *   - 鉴权：node.token（node_token 表），非 node.api_key
 *   - 更新表：dns_geodns.last_heartbeat_at，非 dns_resolver_nodes
 *
 * 关联方式：token → resolver_node → region → geodns_node
 */
final class GeoDnsHeartbeatController
{
    public function store(Request $request): JsonResponse
    {
        /** @var \App\Models\Node|null $resolverNode */
        $resolverNode = $request->attributes->get('node');
        if ($resolverNode === null || empty($resolverNode->region)) {
            return response()->json([
                'error' => ['code' => 'UNAUTHORIZED', 'message' => 'invalid token or missing region'],
            ], 401);
        }

        // 通过 region 匹配对应的 geodns 调度器
        $geodns = DnsGeodns::query()
            ->where('region', $resolverNode->region)
            ->first();

        if ($geodns === null) {
            return response()->json([
                'error' => ['code' => 'NOT_FOUND', 'message' => 'no geodns found for region: ' . $resolverNode->region],
            ], 404);
        }

        $now = now();
        $geodns->forceFill(['last_heartbeat_at' => $now])->saveQuietly();

        return response()->json([
            'data' => [
                'node_code' => $geodns->node_code,
                'last_heartbeat_at' => $now->toIso8601String(),
            ],
        ]);
    }
}
