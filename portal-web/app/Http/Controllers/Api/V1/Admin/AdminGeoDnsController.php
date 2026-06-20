<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\AdminAuditLog;
use App\Models\GeoDnsMapping;
use App\Models\Node;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class AdminGeoDnsController
{
    public function index(Request $request): JsonResponse
    {
        $query = GeoDnsMapping::query()->with('node');

        if ($request->filled('region')) {
            $query->where('region', 'like', '%' . $request->input('region') . '%');
        }

        if ($request->filled('enabled')) {
            $query->where('enabled', filter_var($request->input('enabled'), FILTER_VALIDATE_BOOLEAN));
        }

        $mappings = $query->orderByDesc('id')->get()->map(function (GeoDnsMapping $mapping): array {
            $row = $mapping->toArray();
            // 优先使用映射表自身的字段，否则回退到关联节点
            $row['node_name'] = $mapping->node_name ?? $mapping->node?->node_name;
            $row['node_alias'] = $mapping->node_alias;
            $row['public_ipv4'] = $mapping->public_ipv4 ?? $mapping->node?->public_ipv4;
            $row['node_count'] = 1;
            $row['node_status'] = $mapping->node?->status;
            $row['node_last_heartbeat_at'] = $mapping->node?->last_heartbeat_at?->toIso8601String();

            return $row;
        })->all();

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
        $row = $mapping->toArray();
        $row['node_name'] = $mapping->node?->node_name;

        return response()->json(['data' => $row]);
    }

    public function store(Request $request): JsonResponse
    {
        $actorId = $request->user()?->admin_id;
        $validated = $request->validate([
            'region' => 'required|string|max:80',
            'node_name' => 'required|string|max:100',
            'public_ipv4' => 'nullable|string|max:45',
            'node_alias' => 'nullable|string|max:100',
            'enabled' => 'boolean',
        ]);

        $mapping = GeoDnsMapping::create([
            'domain' => $request->input('domain', 'resolver.ocerlink.com'),
            'region' => $validated['region'],
            'node_name' => $validated['node_name'],
            'public_ipv4' => $validated['public_ipv4'] ?? null,
            'node_alias' => $validated['node_alias'] ?? null,
            'enabled' => $validated['enabled'] ?? true,
        ]);

        AdminAuditLog::record('geo_dns.create', 'geo_dns_mapping', $mapping->id, $mapping->toArray(), $actorId, null, $request->ip(), $request->userAgent());

        return response()->json(['data' => $mapping->toArray()], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $actorId = $request->user()?->admin_id;
        $mapping = GeoDnsMapping::findOrFail($id);

        $validated = $request->validate([
            'region' => 'string|max:80',
            'node_name' => 'string|max:100',
            'public_ipv4' => 'nullable|string|max:45',
            'node_alias' => 'nullable|string|max:100',
            'enabled' => 'boolean',
        ]);

        $mapping->update($validated);

        AdminAuditLog::record('geo_dns.update', 'geo_dns_mapping', $id, $mapping->toArray(), $actorId, null, $request->ip(), $request->userAgent());

        return response()->json(['data' => $mapping->fresh()->toArray()]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $actorId = $request->user()?->admin_id;
        $mapping = GeoDnsMapping::findOrFail($id);
        $mapping->delete();

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

        $count = GeoDnsMapping::whereIn('id', $validated['ids'])->delete();

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
}
