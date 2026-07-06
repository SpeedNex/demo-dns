<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Node;

use App\Models\DnsGeodns;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GeoDNS 安装节点心跳上报（2026-07-06 重构）
 *
 * 业务语义：geodns 进程装在 resolver 节点上。
 *   - 鉴权：geodns.token（独立于 resolver，2026-07-06 拆分）
 *   - 状态归属：dns_resolver_nodes（出现在 /admin/nodes 页面）
 *   - 同时同步 dns_geodns 调度器配置（按 region）
 */
final class GeoDnsHeartbeatController
{
    public function store(Request $request): JsonResponse
    {
        /** @var \App\Models\Node|null $node */
        $node = $request->attributes->get('geodns_node');
        if ($node === null) {
            return response()->json([
                'error' => ['code' => 'UNAUTHORIZED', 'message' => 'invalid or missing geodns token'],
            ], 401);
        }

        $now = now();
        $node->forceFill(['last_heartbeat_at' => $now])->saveQuietly();

        // 同步 dns_geodns 调度器心跳（按 region 匹配）
        $geodns = DnsGeodns::query()->where('region', $node->region)->first();
        if ($geodns !== null) {
            $geodns->forceFill(['last_heartbeat_at' => $now])->saveQuietly();
        }

        return response()->json([
            'data' => [
                'node_code' => $node->node_code,
                'last_heartbeat_at' => $now->toIso8601String(),
            ],
        ]);
    }
}
