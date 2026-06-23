<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Node;

use App\Models\DnsGeodns;
use App\Models\Node;
use Illuminate\Http\JsonResponse;

/**
 * GeoDNS 配置查询接口（2026-06-23 重构）
 *
 * Resolver 节点从 dns_resolver_nodes 表读取。
 * GeoDNS 节点从 dns_geodns 表读取。
 */
final class GeoDNSConfigController
{
    public function show(): JsonResponse
    {
        // 获取所有 resolver 节点
        $resolvers = Node::query()
            ->where('region', 'like', 'resolver-%')
            ->online()
            ->select([
                'node_code',
                'region',
                'country',
                'city',
                'public_ipv4',
                'public_ipv6',
                'weight',
            ])
            ->get();

        // 获取所有 geodns 节点
        $geodnsNodes = DnsGeodns::query()
            ->where('install_status', 'installed')
            ->whereNotNull('domain')
            ->whereNotNull('last_heartbeat_at')
            ->where('last_heartbeat_at', '>', now()->subSeconds(90))
            ->select(['domain', 'public_ipv4', 'public_ipv6'])
            ->get();

        $domains = $geodnsNodes->pluck('domain')->filter()->unique()->values()->all();

        return response()->json([
            'data' => [
                'resolvers' => $resolvers,
                'domains' => $domains,
                'generated_at' => gmdate(DATE_ATOM),
                'ttl_seconds' => 30,
            ],
        ]);
    }
}
