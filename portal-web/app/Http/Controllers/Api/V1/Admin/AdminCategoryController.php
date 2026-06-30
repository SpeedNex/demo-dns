<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\AdminAuditLog;
use App\Models\RuleCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class AdminCategoryController
{
    public function index(Request $request): JsonResponse
    {
        $query = RuleCategory::query();

        if ($search = trim((string) $request->input('search', ''))) {
            $query->where(function ($q) use ($search): void {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('name_en', 'like', "%{$search}%");
            });
        }

        if ($group = $request->input('group')) {
            $query->where('group', $group);
        }

        $categories = $query->orderBy('sort_order')->orderBy('id')->get()->map(fn (RuleCategory $c): array => $this->present($c))->all();

        return response()->json(['data' => $categories]);
    }

    public function options(): JsonResponse
    {
        $categories = RuleCategory::query()
            ->where('enabled', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (RuleCategory $c): array => [
                'code' => $c->code,
                'name' => $c->name,
                'group' => $c->group,
            ])->all();

        return response()->json(['data' => $categories]);
    }

    public function store(Request $request): JsonResponse
    {
        $actorId = $request->user()?->admin_id;
        $validated = $request->validate([
            'code' => 'required|string|max:40|unique:rule_categories,code',
            'name' => 'required|string|max:100',
            'name_en' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'icon' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:20',
            'parent_code' => 'nullable|string|max:40',
            'group' => ['required', Rule::in(['threat', 'privacy', 'family', 'custom'])],
            'enabled' => 'boolean',
            'is_system' => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);

        $category = RuleCategory::create($validated);

        AdminAuditLog::record('rule_category.create', 'rule_category', $category->id, $this->present($category), $actorId, null, $request->ip(), $request->userAgent());

        return response()->json(['data' => $this->present($category)], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $actorId = $request->user()?->admin_id;
        $category = RuleCategory::findOrFail($id);

        $validated = $request->validate([
            'code' => 'string|max:40|unique:rule_categories,code,' . $id,
            'name' => 'string|max:100',
            'name_en' => 'string|max:100',
            'description' => 'nullable|string|max:500',
            'icon' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:20',
            'parent_code' => 'nullable|string|max:40',
            'group' => [Rule::in(['threat', 'privacy', 'family', 'custom'])],
            'enabled' => 'boolean',
            'sort_order' => 'integer|min:0',
        ]);

        $category->update($validated);

        AdminAuditLog::record('rule_category.update', 'rule_category', $id, $this->present($category->fresh()), $actorId, null, $request->ip(), $request->userAgent());

        return response()->json(['data' => $this->present($category->fresh())]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $actorId = $request->user()?->admin_id;
        $category = RuleCategory::findOrFail($id);

        if ($category->is_system) {
            return \App\Helpers\ApiResponse::error('SYSTEM_CATEGORY', 'System categories cannot be deleted.', 422);
        }

        $category->delete();

        AdminAuditLog::record('rule_category.delete', 'rule_category', $id, [], $actorId, null, $request->ip(), $request->userAgent());

        return response()->json(['data' => ['id' => $id, 'deleted' => true]]);
    }

    /** POST /admin/rule-categories/batch-destroy */
    public function batchDestroy(Request $request): JsonResponse
    {
        $actorId = $request->user()?->admin_id;
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
        ]);

        // 排除系统分类
        $query = RuleCategory::whereIn('id', $validated['ids'])->where('is_system', false);
        $count = $query->delete();

        AdminAuditLog::record('rule_category.batch_delete', 'rule_category', null, ['ids' => $validated['ids'], 'count' => $count], $actorId, null, $request->ip(), $request->userAgent());

        return response()->json(['data' => ['deleted' => $count]]);
    }

    private function present(RuleCategory $c): array
    {
        return $c->toArray();
    }
}
