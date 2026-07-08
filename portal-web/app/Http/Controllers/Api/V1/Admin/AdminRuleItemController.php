<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\AdminAuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class AdminRuleItemController
{
    public function index(Request $request): JsonResponse
    {
        $query = DB::table('rule_items')
            ->leftJoin('rule_sources', 'rule_items.rule_source_id', '=', 'rule_sources.id')
            ->leftJoin('rule_categories', 'rule_items.category', '=', 'rule_categories.code')
            ->select('rule_items.*', 'rule_sources.name as rule_source_name', 'rule_categories.name as category_name');

        if ($request->filled('rule_source_id')) {
            $query->where('rule_items.rule_source_id', (int) $request->input('rule_source_id'));
        }

        if ($category = $request->input('category')) {
            $query->where('rule_items.category', $category);
        }

        if ($action = $request->input('action')) {
            $query->where('rule_items.action', $action);
        }

        if ($search = trim((string) $request->input('search', ''))) {
            $query->where('rule_items.domain', 'like', "%{$search}%");
        }

        $perPage = (int) $request->input('per_page', 50);
        $page = (int) $request->input('page', 1);

        $total = (clone $query)->count();
        $items = $query->orderByDesc('rule_items.id')->paginate($perPage, ['*'], 'page', $page)->toArray();

        return response()->json([
            'data' => $items['data'],
            'meta' => [
                'total' => $total,
                'per_page' => $items['per_page'],
                'current_page' => $items['current_page'],
                'last_page' => $items['last_page'],
            ],
        ]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $actorId = $request->user()?->admin_id;
        $count = DB::table('rule_items')->where('id', $id)->delete();

        AdminAuditLog::record('rule_item.delete', 'rule_item', $id, [], $actorId, null, $request->ip(), $request->userAgent());

        return response()->json(['data' => ['id' => $id, 'deleted' => $count > 0]]);
    }

    public function batchDestroy(Request $request): JsonResponse
    {
        $actorId = $request->user()?->admin_id;
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
        ]);

        $count = DB::table('rule_items')->whereIn('id', $validated['ids'])->delete();

        AdminAuditLog::record('rule_item.batch_delete', 'rule_item', null, ['ids' => $validated['ids'], 'count' => $count], $actorId, null, $request->ip(), $request->userAgent());

        return response()->json(['data' => ['deleted' => $count]]);
    }

    public function import(Request $request): JsonResponse
    {
        $actorId = $request->user()?->admin_id;
        $validated = $request->validate([
            'rule_source_id' => 'required|integer',
            'domains' => 'required|array|min:1',
            'domains.*' => 'string|max:255',
            'category' => 'nullable|string|max:60',
            'action' => ['nullable', Rule::in(['block', 'allow', 'rewrite', 'safe_search'])],
        ]);

        $now = now();
        $imported = 0;
        $action = $validated['action'] ?? 'block';
        $category = $validated['category'] ?? 'default';

        foreach ($validated['domains'] as $domain) {
            $domain = trim((string) $domain);
            if ($domain === '' || strlen($domain) > 255) {
                continue;
            }
            DB::table('rule_items')->updateOrInsert(
                ['rule_source_id' => $validated['rule_source_id'], 'domain' => $domain],
                ['category' => $category, 'action' => $action, 'created_at' => $now]
            );
            $imported++;
        }

        AdminAuditLog::record('rule_item.import', 'rule_item', null, ['rule_source_id' => $validated['rule_source_id'], 'imported' => $imported], $actorId, null, $request->ip(), $request->userAgent());

        return response()->json(['data' => ['imported' => $imported]]);
    }
}
