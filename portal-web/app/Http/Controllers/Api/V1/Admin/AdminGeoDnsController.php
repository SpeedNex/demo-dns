<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\AdminAuditLog;
use App\Models\DnsGeodns;
use App\Models\Node;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * GeoDNS 调度解析器管理（2026-06-23 重构）
 *
 * 【强约束】GeoDNS 是调度解析器（Scheduler / Resolver），不是节点。
 * GeoDNS 存放在独立的 dns_geodns 表中。
 * Resolver 节点存放在 dns_resolver_nodes 表中。
 * 关联通过 region 地区精确匹配。
 */
final class AdminGeoDnsController
{
    public function index(Request $request): JsonResponse
    {
        $query = DnsGeodns::query();

        if ($request->filled('country')) {
            $query->where('country', strtoupper((string) $request->input('country')));
        }

        if ($request->filled('region')) {
            $query->where('region', 'like', '%' . $request->input('region') . '%');
        }

        $nodes = $query->orderBy('region', 'desc')->get()->map(function (DnsGeodns $node): array {
            return [
                'id' => $node->id,
                'node_code' => $node->node_code,
                'node_alias' => $node->node_alias,
                'region' => $node->region,
                'country' => $node->country,
                'city' => $node->city,
                'domain' => $node->domain,
                'public_ipv4' => $node->public_ipv4,
                'public_ipv6' => $node->public_ipv6,
                'weight' => $node->weight,
                'status' => $node->install_status === 'installed' ? 'online' : 'offline',
                'node_count' => 1,
                'node_status' => $node->install_status === 'installed' ? 'online' : 'offline',
                'node_last_heartbeat_at' => $node->last_heartbeat_at?->toIso8601String(),
                'node_last_seen_ago' => $node->last_heartbeat_at?->diffForHumans(now(), ['short' => true]),
                'install_status' => $node->install_status,
                'node_heartbeat_stale' => ! ($node->install_status === 'installed' && $node->last_heartbeat_at?->gt(now()->subSeconds(90))),
                'created_at' => $node->created_at?->toIso8601String(),
                'updated_at' => $node->updated_at?->toIso8601String(),
                'is_orphan' => false,
            ];
        })->all();

        // 统计 resolver 节点数按地区分组
        $resolverCounts = Node::query()
            ->where('region', 'like', 'resolver-%')
            ->whereNotNull('region')
            ->groupBy('region')
            ->selectRaw('region, COUNT(*) as count')
            ->pluck('count', 'region')
            ->all();

        // 添加 resolver 统计
        foreach ($nodes as &$row) {
            $row['dns_node_count'] = $resolverCounts[$row['region']] ?? 0;
        }

        return response()->json([
            'data' => $nodes,
            'meta' => [
                'total' => count($nodes),
                'enabled' => count(array_filter($nodes, fn (array $m): bool => $m['status'] === 'online')),
            ],
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $node = DnsGeodns::query()->findOrFail($id);

        return response()->json(['data' => $this->presentNode($node)]);
    }

    public function store(Request $request): JsonResponse
    {
        $actorId = $request->user()?->admin_id;
        $validated = $request->validate([
            'country' => 'nullable|string|size:2',
            'region' => 'required|string|max:80',
            'node_alias' => 'nullable|string|max:100',
            'domain' => 'nullable|string|max:255',
            'public_ipv4' => 'nullable|string|max:45',
            'public_ipv6' => 'nullable|string|max:64',
            'weight' => 'integer|min:0|max:10000',
            'enabled' => 'boolean',
        ]);

        // 确保 region 以 'geodns-' 开头
        $region = $validated['region'];
        if (! str_starts_with($region, 'geodns-')) {
            $region = 'geodns-' . $region;
        }

        // 别名留空时自动按 geodns-{6位随机} 生成
        $nodeAlias = $validated['node_alias'] ?? ('geodns-' . Str::lower(Str::random(6)));

        $node = DnsGeodns::create([
            'node_code' => 'nd_' . Str::lower(Str::random(10)),
            'node_alias' => $nodeAlias,
            'region' => $region,
            'country' => isset($validated['country']) ? strtoupper($validated['country']) : null,
            'domain' => $validated['domain'] ?? null,
            'public_ipv4' => $validated['public_ipv4'] ?? null,
            'public_ipv6' => $validated['public_ipv6'] ?? null,
            'weight' => $validated['weight'] ?? 100,
            'install_status' => 'pending',
            'desired_config_version' => 1,
            'current_config_version' => 0,
        ]);

        AdminAuditLog::record('geo_dns.create', 'node', (string) $node->id, $this->presentNode($node), $actorId, null, $request->ip(), $request->userAgent());

        return response()->json(['data' => $this->presentNode($node)], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $actorId = $request->user()?->admin_id;
        $node = DnsGeodns::query()->findOrFail($id);

        $validated = $request->validate([
            'country' => 'nullable|string|size:2',
            'region' => 'nullable|string|max:80',
            'node_alias' => 'nullable|string|max:100',
            'domain' => 'nullable|string|max:255',
            'public_ipv4' => 'nullable|string|max:45',
            'public_ipv6' => 'nullable|string|max:64',
            'weight' => 'nullable|integer|min:0|max:10000',
            'enabled' => 'boolean',
        ]);

        $payload = [];
        if (isset($validated['region'])) {
            $region = $validated['region'];
            if (! str_starts_with($region, 'geodns-')) {
                $region = 'geodns-' . $region;
            }
            $payload['region'] = $region;
        }
        if (array_key_exists('country', $validated)) {
            $payload['country'] = $validated['country'] !== null ? strtoupper($validated['country']) : null;
        }
        if (array_key_exists('node_alias', $validated)) {
            $payload['node_alias'] = $validated['node_alias'];
        }
        if (array_key_exists('domain', $validated)) {
            $payload['domain'] = $validated['domain'];
        }
        if (array_key_exists('public_ipv4', $validated)) {
            $payload['public_ipv4'] = $validated['public_ipv4'];
        }
        if (array_key_exists('public_ipv6', $validated)) {
            $payload['public_ipv6'] = $validated['public_ipv6'];
        }
        if (array_key_exists('weight', $validated)) {
            $payload['weight'] = $validated['weight'];
        }

        $node->update($payload);

        AdminAuditLog::record('geo_dns.update', 'node', (string) $node->id, $this->presentNode($node->fresh()), $actorId, null, $request->ip(), $request->userAgent());

        return response()->json(['data' => $this->presentNode($node->fresh())]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $actorId = $request->user()?->admin_id;
        $node = DnsGeodns::query()->findOrFail($id);

        $node->delete();

        AdminAuditLog::record('geo_dns.delete', 'node', $id, [], $actorId, null, $request->ip(), $request->userAgent());

        return response()->json(['data' => ['id' => $id, 'deleted' => true]]);
    }

    public function batchDestroy(Request $request): JsonResponse
    {
        $actorId = $request->user()?->admin_id;
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'required',
        ]);

        $count = DnsGeodns::query()
            ->whereIn('id', $validated['ids'])
            ->delete();

        AdminAuditLog::record('geo_dns.batch_delete', 'node', null, ['ids' => $validated['ids'], 'count' => $count], $actorId, null, $request->ip(), $request->userAgent());

        return response()->json(['data' => ['deleted' => $count]]);
    }

    /**
     * 一键插入 GeoDNS 演示数据：4 个调度解析器。
     */
    public function seedDemo(Request $request): JsonResponse
    {
        $actorId = $request->user()?->admin_id;

        $demoNodes = [
            ['region' => 'geodns-local', 'country' => 'CN', 'city' => 'Shanghai', 'alias' => 'Local Mac'],
            ['region' => 'geodns-cn', 'country' => 'CN', 'city' => 'Shanghai', 'alias' => 'CN Shanghai'],
            ['region' => 'geodns-us', 'country' => 'US', 'city' => 'Silicon Valley', 'alias' => 'US Silicon'],
            ['region' => 'geodns-eu', 'country' => 'DE', 'city' => 'Frankfurt', 'alias' => 'EU Frankfurt'],
        ];

        $created = [];
        foreach ($demoNodes as $demo) {
            $node = DnsGeodns::updateOrCreate(
                ['node_alias' => $demo['alias']],
                [
                    'node_code' => 'nd_' . Str::lower(Str::random(8)),
                    'region' => $demo['region'],
                    'country' => $demo['country'],
                    'city' => $demo['city'],
                    'domain' => 'resolver.ocerlink.com',
                    'public_ipv4' => '127.0.0.1',
                    'install_status' => 'installed',
                    'desired_config_version' => 1,
                    'current_config_version' => 1,
                    'last_heartbeat_at' => now(),
                ],
            );
            $created[] = $node;
        }

        AdminAuditLog::record('geo_dns.seed_demo', 'node', null, [
            'nodes' => count($created),
        ], $actorId, null, $request->ip(), $request->userAgent());

        return response()->json([
            'data' => [
                'nodes' => array_map(fn (DnsGeodns $n) => ['id' => $n->id, 'node_code' => $n->node_code, 'region' => $n->region], $created),
            ],
        ]);
    }

    /**
     * 创建或获取「本地」调度解析器（用于本地调试 / GeoDNS 路由）。
     */
    public function bindLocalNode(Request $request): JsonResponse
    {
        $actorId = $request->user()?->admin_id;
        $validated = $request->validate([
            'public_ipv4' => 'nullable|string|max:45',
            'public_ipv6' => 'nullable|string|max:64',
        ]);

        $node = DnsGeodns::updateOrCreate(
            ['node_code' => 'nd_local_mac'],
            [
                'node_alias' => 'Local Mac',
                'region' => 'geodns-local',
                'country' => 'CN',
                'city' => 'Shanghai',
                'domain' => 'resolver.ocerlink.com',
                'public_ipv4' => $validated['public_ipv4'] ?? '127.0.0.1',
                'public_ipv6' => $validated['public_ipv6'] ?? null,
                'supported_protocols' => ['doh', 'dot', 'udp'],
                'install_status' => 'installed',
                'current_config_version' => 1,
                'desired_config_version' => 1,
                'last_heartbeat_at' => now(),
            ],
        );

        // 同步 IPv4/IPv6
        $updates = [];
        if (! empty($validated['public_ipv4'])) $updates['public_ipv4'] = $validated['public_ipv4'];
        if (! empty($validated['public_ipv6'])) $updates['public_ipv6'] = $validated['public_ipv6'];
        if ($updates) $node->update($updates);

        AdminAuditLog::record('geo_dns.bind_local_node', 'node', (string) $node->id, [
            'node_code' => $node->node_code,
            'region' => $node->region,
        ], $actorId, null, $request->ip(), $request->userAgent());

        return response()->json(['data' => $this->presentNode($node->fresh())]);
    }

    private function presentNode(DnsGeodns $node): array
    {
        return [
            'id' => $node->id,
            'node_code' => $node->node_code,
            'node_alias' => $node->node_alias,
            'region' => $node->region,
            'country' => $node->country,
            'city' => $node->city,
            'domain' => $node->domain,
            'public_ipv4' => $node->public_ipv4,
            'public_ipv6' => $node->public_ipv6,
            'weight' => $node->weight,
            'status' => $node->install_status === 'installed' ? 'online' : 'offline',
            'install_status' => $node->install_status,
            'node_count' => 1,
            'node_status' => $node->install_status === 'installed' ? 'online' : 'offline',
            'node_last_heartbeat_at' => $node->last_heartbeat_at?->toIso8601String(),
            'node_last_seen_ago' => $node->last_heartbeat_at?->diffForHumans(now(), ['short' => true]),
            'node_heartbeat_stale' => ! ($node->install_status === 'installed' && $node->last_heartbeat_at?->gt(now()->subSeconds(90))),
            'created_at' => $node->created_at?->toIso8601String(),
            'updated_at' => $node->updated_at?->toIso8601String(),
        ];
    }
}
