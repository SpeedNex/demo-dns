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

        if ($request->filled('country')) {
            $query->where('country', strtoupper((string) $request->input('country')));
        }

        if ($request->filled('enabled')) {
            $query->where('enabled', filter_var($request->input('enabled'), FILTER_VALIDATE_BOOLEAN));
        }

        $mappings = $query->orderBy('country')->orderBy('priority')->get()->map(function (GeoDnsMapping $mapping): array {
            $row = $mapping->toArray();
            $row['node_name'] = $mapping->node?->node_name;

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
            'country' => 'required|string|size:2',
            'region' => 'required|string|max:80',
            'node_id' => 'required|exists:nodes,id',
            'priority' => 'integer|min:0|max:1000',
            'weight' => 'integer|min:0|max:10000',
            'enabled' => 'boolean',
        ]);

        $mapping = GeoDnsMapping::create([
            'domain' => $request->input('domain', 'resolver.ocerlink.com'),
            'country' => strtoupper($validated['country']),
            'region' => $validated['region'],
            'target_node_id' => (int) $validated['node_id'],
            'priority' => $validated['priority'] ?? 0,
            'weight' => $validated['weight'] ?? 100,
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
            'country' => 'string|size:2',
            'region' => 'string|max:80',
            'node_id' => 'exists:nodes,id',
            'priority' => 'integer|min:0|max:1000',
            'weight' => 'integer|min:0|max:10000',
            'enabled' => 'boolean',
        ]);

        if (isset($validated['country'])) {
            $validated['country'] = strtoupper($validated['country']);
        }
        if (array_key_exists('node_id', $validated)) {
            $validated['target_node_id'] = (int) $validated['node_id'];
            unset($validated['node_id']);
        }

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
}
