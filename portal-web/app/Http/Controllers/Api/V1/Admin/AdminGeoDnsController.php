<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\AdminAuditLog;
use App\Models\GeoDnsMapping;
use App\Models\Node;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AdminGeoDnsController
{
    public function index(Request $request): JsonResponse
    {
        $query = GeoDnsMapping::query()->with('node');

        if ($request->filled('country')) {
            $query->where('country', strtoupper((string) $request->input('country')));
        }

        if ($request->filled('region')) {
            $query->where('region', 'like', '%' . $request->input('region') . '%');
        }

        if ($request->filled('enabled')) {
            $query->where('enabled', filter_var($request->input('enabled'), FILTER_VALIDATE_BOOLEAN));
        }

        $mappings = $query->orderByDesc('id')->get()->map(function (GeoDnsMapping $mapping): array {
            $row = $this->presentMapping($mapping);
            $row['node_count'] = 1;
            $row['node_status'] = $mapping->node?->status;
            $row['node_last_heartbeat_at'] = $mapping->node?->last_heartbeat_at?->toIso8601String();

            return $row;
        })->all();

        // 2026-06-22 NEW P0#1 (联调发现): 已安装的 geodns 节点若未在 geo_dns_mappings
        // 创建映射（例如通过 geodns-install.sh 直接安装，或 mapping 被误删），
        // 会导致 /admin/geo-dns 列表看不到这些节点。补上"已存在但无 mapping"的 fallback 行。
        $mappedNodeIds = GeoDnsMapping::query()
            ->whereNotNull('target_node_id')
            ->pluck('target_node_id')
            ->all();
        $orphanNodes = \App\Models\Node::query()
            ->where('node_type', 'geodns')
            ->whereNotIn('id', $mappedNodeIds)
            ->orderByDesc('id')
            ->get();
        foreach ($orphanNodes as $node) {
            $mappings[] = [
                'id' => 'orphan-node-' . $node->id,
                'domain' => '',
                'country' => null,
                'region' => $node->region ?? '',
                'target_node_id' => $node->id,
                'node_id' => $node->id,
                'node_name' => $node->node_name ?? $node->name,
                'public_ipv4' => $node->public_ipv4,
                'node_alias' => null,
                'target_endpoint' => null,
                'priority' => 0,
                'weight' => 0,
                'enabled' => $node->status === 'online',
                'created_at' => $node->created_at?->toIso8601String(),
                'updated_at' => $node->updated_at?->toIso8601String(),
                'node_count' => 1,
                'node_status' => $node->status,
                'node_last_heartbeat_at' => $node->last_heartbeat_at?->toIso8601String(),
                'is_orphan' => true,
            ];
        }

        return response()->json([
            'data' => $mappings,
            'meta' => [
                'total' => count($mappings),
                'enabled' => count(array_filter($mappings, fn (array $m): bool => (bool) $m['enabled'])),
            ],
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $mapping = GeoDnsMapping::with('node')->findOrFail($id);
        $row = $this->presentMapping($mapping);

        return response()->json(['data' => $row]);
    }

    public function store(Request $request): JsonResponse
    {
        $actorId = $request->user()?->admin_id;
        $validated = $request->validate([
            'country' => 'nullable|string|size:2',
            'region' => 'required|string|max:80',
            'node_id' => 'nullable|exists:nodes,id',
            'node_name' => 'nullable|string|max:100',
            'public_ipv4' => 'nullable|string|max:45',
            'node_alias' => 'nullable|string|max:100',
            'target_endpoint' => 'nullable|string|max:255',
            'priority' => 'integer|min:0|max:1000',
            'weight' => 'integer|min:0|max:10000',
            'enabled' => 'boolean',
        ]);

        $node = $this->resolveNode($validated['node_id'] ?? null, $validated['node_name'] ?? null);

        // 如果没有关联节点，自动创建一个
        if (! $node) {
            $node = Node::create([
                'name' => $validated['node_name'] ?? $validated['region'],
                'region' => $validated['region'],
                'public_ipv4' => $validated['public_ipv4'] ?? null,
                'status' => 'pending',
                'node_type' => 'geodns',
            ]);
        }

        $mapping = GeoDnsMapping::create([
            'domain' => $request->input('domain', 'resolver.ocerlink.com'),
            'country' => isset($validated['country']) ? strtoupper($validated['country']) : strtoupper((string) $request->input('country', '*')),
            'region' => $validated['region'],
            'target_node_id' => $node?->id,
            'node_name' => $validated['node_name'] ?? $node?->node_name ?? $node?->name,
            'public_ipv4' => $validated['public_ipv4'] ?? $node?->public_ipv4,
            'node_alias' => $validated['node_alias'] ?? null,
            'target_endpoint' => $validated['target_endpoint'] ?? null,
            'priority' => $validated['priority'] ?? 0,
            'weight' => $validated['weight'] ?? 100,
            'enabled' => $validated['enabled'] ?? true,
        ]);

        AdminAuditLog::record('geo_dns.create', 'geo_dns_mapping', $mapping->id, $this->presentMapping($mapping->fresh('node')), $actorId, null, $request->ip(), $request->userAgent());

        return response()->json(['data' => $this->presentMapping($mapping->fresh('node'))], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $actorId = $request->user()?->admin_id;
        $mapping = GeoDnsMapping::findOrFail($id);

        $validated = $request->validate([
            'country' => 'nullable|string|size:2',
            'region' => 'string|max:80',
            'node_id' => 'nullable|exists:nodes,id',
            'node_name' => 'nullable|string|max:100',
            'public_ipv4' => 'nullable|string|max:45',
            'node_alias' => 'nullable|string|max:100',
            'target_endpoint' => 'nullable|string|max:255',
            'priority' => 'integer|min:0|max:1000',
            'weight' => 'integer|min:0|max:10000',
            'enabled' => 'boolean',
        ]);

        $payload = $validated;

        if (array_key_exists('country', $payload) && $payload['country'] !== null) {
            $payload['country'] = strtoupper($payload['country']);
        }

        if (array_key_exists('node_id', $payload)) {
            $node = $this->resolveNode($payload['node_id'], $payload['node_name'] ?? null);
            $payload['target_node_id'] = $node?->id;
            $payload['node_name'] = $payload['node_name'] ?? $node?->node_name ?? $node?->name;
            $payload['public_ipv4'] = $payload['public_ipv4'] ?? $node?->public_ipv4;
            unset($payload['node_id']);
        }

        $mapping->update($payload);

        AdminAuditLog::record('geo_dns.update', 'geo_dns_mapping', $id, $this->presentMapping($mapping->fresh('node')), $actorId, null, $request->ip(), $request->userAgent());

        return response()->json(['data' => $this->presentMapping($mapping->fresh('node'))]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $actorId = $request->user()?->admin_id;
        $mapping = GeoDnsMapping::findOrFail($id);

        $targetNodeId = $mapping->target_node_id;
        $mapping->delete();

        if ($targetNodeId) {
            Node::query()->where('id', $targetNodeId)->delete();
        }

        AdminAuditLog::record('geo_dns.delete', 'geo_dns_mapping', $id, [], $actorId, null, $request->ip(), $request->userAgent());

        return response()->json(['data' => ['id' => $id, 'deleted' => true]]);
    }

    public function batchDestroy(Request $request): JsonResponse
    {
        $actorId = $request->user()?->admin_id;
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'required',
        ]);

        $targetNodeIds = GeoDnsMapping::whereIn('id', $validated['ids'])
            ->whereNotNull('target_node_id')
            ->pluck('target_node_id')
            ->all();

        $count = GeoDnsMapping::whereIn('id', $validated['ids'])->delete();

        if (! empty($targetNodeIds)) {
            Node::query()->whereIn('id', $targetNodeIds)->delete();
        }

        AdminAuditLog::record('geo_dns.batch_delete', 'geo_dns_mapping', null, ['ids' => $validated['ids'], 'count' => $count], $actorId, null, $request->ip(), $request->userAgent());

        return response()->json(['data' => ['deleted' => $count]]);
    }

    /**
     * 一键插入 GeoDNS 演示数据：4 个节点（local + CN/US/EU）+ 6 条国家映射。
     * 已存在的 node_code / (domain, country, region) 组合会被跳过，不重复插入。
     */
    public function seedDemo(Request $request): JsonResponse
    {
        $actorId = $request->user()?->admin_id;
        $seeder = app(\Database\Seeders\GeoDnsDemoSeeder::class);
        $seeder->setContainer(app());
        $seeder->setCommand($this->commandForSeeder());
        $seeder->run();

        $createdNodes = \App\Models\Node::query()->whereIn('node_code', ['nd_local_mac', 'nd_cn_shanghai', 'nd_us_silicon', 'nd_eu_frankfurt'])->get(['id', 'node_code', 'name']);
        $createdMappings = GeoDnsMapping::query()->where('domain', 'resolver.ocerlink.com')->get(['id', 'country', 'region', 'target_node_id']);

        AdminAuditLog::record('geo_dns.seed_demo', 'geo_dns_mapping', null, [
            'nodes' => $createdNodes->pluck('node_code')->all(),
            'mappings' => $createdMappings->count(),
        ], $actorId, null, $request->ip(), $request->userAgent());

        return response()->json([
            'data' => [
                'nodes' => $createdNodes->toArray(),
                'mappings' => $createdMappings->toArray(),
            ],
        ]);
    }

    /**
     * 创建或获取「本地」节点（用于本地调试 / GeoDNS 路由）。
     * 返回固定 node_code = nd_local_mac，便于在 GeoDNS 映射里引用。
     */
    public function bindLocalNode(Request $request): JsonResponse
    {
        $actorId = $request->user()?->admin_id;
        $validated = $request->validate([
            'public_ipv4' => 'nullable|string|max:45',
            'public_ipv6' => 'nullable|string|max:64',
        ]);

        $node = \App\Models\Node::query()->firstOrCreate(
            ['node_code' => 'nd_local_mac'],
            [
                'name' => 'Local Mac',
                'node_name' => 'Local Mac',
                'region' => 'local',
                'country' => 'CN',
                'city' => 'Shanghai',
                'public_ipv4' => $validated['public_ipv4'] ?? '127.0.0.1',
                'public_ipv6' => $validated['public_ipv6'] ?? null,
                'status' => 'online',
                'supported_protocols' => ['doh', 'dot', 'udp'],
                'current_config_version' => 0,
                'desired_config_version' => 1,
            ],
        );

        // 同步 IPv4/IPv6（如果传了）
        $updates = [];
        if (! empty($validated['public_ipv4'])) $updates['public_ipv4'] = $validated['public_ipv4'];
        if (! empty($validated['public_ipv6'])) $updates['public_ipv6'] = $validated['public_ipv6'];
        if ($updates) $node->update($updates);

        // 自动创建一条 LOCAL → local node 的映射
        $mapping = GeoDnsMapping::query()->updateOrCreate(
            ['domain' => 'resolver.ocerlink.com', 'country' => 'LOCAL', 'region' => 'local'],
            [
                'target_node_id' => (int) $node->id,
                'node_name' => $node->node_name ?? $node->name,
                'public_ipv4' => $node->public_ipv4,
                'priority' => 5,
                'weight' => 200,
                'enabled' => true,
            ],
        );

        AdminAuditLog::record('geo_dns.bind_local_node', 'node', (string) $node->id, [
            'node_code' => $node->node_code,
            'mapping_id' => $mapping->id,
        ], $actorId, null, $request->ip(), $request->userAgent());

        $row = $node->fresh()->toArray();
        $row['mapping_id'] = $mapping->id;

        return response()->json(['data' => $row]);
    }

    private function commandForSeeder(): \Illuminate\Console\Command
    {
        // 给 seeder 一个无操作的 command 桩，避免它内部用 $this->command 输出报错
        $command = new \Illuminate\Console\Command('seed');
        $output = new \Illuminate\Console\OutputStyle(
            new \Symfony\Component\Console\Input\StringInput(''),
            new \Symfony\Component\Console\Output\NullOutput(),
        );
        $command->setOutput($output);

        return $command;
    }

    private function resolveNode(string|int|null $nodeId, ?string $nodeName): ?Node
    {
        if ($nodeId !== null && $nodeId !== '') {
            return Node::query()->find($nodeId);
        }

        if ($nodeName !== null && $nodeName !== '') {
            return Node::query()
                ->where('name', $nodeName)
                ->first();
        }

        return null;
    }

    private function presentMapping(GeoDnsMapping $mapping): array
    {
        $row = $mapping->toArray();
        $row['node_id'] = $mapping->target_node_id;
        $row['node_name'] = $mapping->node_name ?? $mapping->node?->node_name ?? $mapping->node?->name;
        $row['public_ipv4'] = $mapping->public_ipv4 ?? $mapping->node?->public_ipv4;

        return $row;
    }
}
