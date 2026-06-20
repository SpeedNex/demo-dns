<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Profile\MemberCatalogService;
use App\Models\AdminAuditLog;
use App\Models\ProfileRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class AdminMemberCatalogController
{
    public function __construct(
        private readonly MemberCatalogService $catalogs = new MemberCatalogService(),
    ) {
    }

    public function show(): JsonResponse
    {
        return response()->json(['data' => $this->catalogs->get()]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_models' => 'required|array',
            'device_models.*.id' => 'nullable|string|max:60',
            'device_models.*.name' => 'nullable|string|max:120',
            'device_models.*.desc' => 'nullable|string|max:255',
            'device_models.*.icon' => 'nullable|string|max:500',
            'device_models.*.color' => 'nullable|string|max:20',
            'privacy_blocklists' => 'required|array',
            'privacy_blocklists.*.key' => 'nullable|string|max:60',
            'privacy_blocklists.*.name' => 'nullable|string|max:120',
            'privacy_blocklists.*.desc' => 'nullable|string|max:255',
            'privacy_blocklists.*.entries' => 'nullable|integer|min:0',
            'privacy_blocklists.*.days_ago' => 'nullable|integer|min:0',
            'parental_presets' => 'required|array',
            'parental_presets.*.name' => 'nullable|string|max:120',
            'parental_presets.*.icon' => 'nullable|string|max:500',
            'parental_presets.*.category' => ['nullable', Rule::in(['website', 'app', 'game'])],
            'parental_categories' => 'required|array',
            'parental_categories.*.key' => 'nullable|string|max:60',
            'parental_categories.*.name' => 'nullable|string|max:120',
            'parental_categories.*.desc' => 'nullable|string|max:255',
        ]);

        $payload = $this->catalogs->update($validated, $request->user()?->admin_id);

        AdminAuditLog::record(
            'member_catalogs.update',
            'system_config',
            'member_feature_catalogs',
            ['counts' => array_map('count', $payload)],
            $request->user()?->admin_id,
            null,
            $request->ip(),
            $request->userAgent(),
        );

        return response()->json(['data' => $payload]);
    }

    public function rules(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'list_type' => ['nullable', Rule::in(['allow', 'deny'])],
            'domain' => 'nullable|string|max:255',
            'profile_id' => 'nullable|string|max:40',
        ]);

        $query = ProfileRule::query()->with('profile:id,name,user_id');

        if (! empty($validated['list_type'])) {
            $query->where('list_type', $validated['list_type']);
        }
        if (! empty($validated['domain'])) {
            $query->where('domain', 'like', '%' . $validated['domain'] . '%');
        }
        if (! empty($validated['profile_id'])) {
            $query->where('profile_id', $validated['profile_id']);
        }

        $items = $query->orderByDesc('created_at')->limit(500)->get()->map(fn (ProfileRule $rule): array => [
            'id' => $rule->id,
            'profile_id' => $rule->profile_id,
            'profile_name' => $rule->profile?->name,
            'user_id' => $rule->profile?->user_id,
            'list_type' => $rule->list_type,
            'match_type' => $rule->match_type,
            'domain' => $rule->domain,
            'action' => $rule->action,
            'enabled' => (bool) $rule->enabled,
            'created_at' => optional($rule->created_at)?->toIso8601String(),
        ])->all();

        return response()->json(['data' => $items]);
    }

    public function destroyRule(Request $request, string $id): JsonResponse
    {
        $rule = ProfileRule::query()->findOrFail($id);
        $rule->delete();

        AdminAuditLog::record('member_rule.delete', 'profile_rule', $id, [], $request->user()?->admin_id, null, $request->ip(), $request->userAgent());

        return response()->json(['data' => ['deleted' => true]]);
    }

    public function batchDestroyRules(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'string',
        ]);

        $deleted = ProfileRule::query()->whereIn('id', $validated['ids'])->delete();

        AdminAuditLog::record('member_rule.batch_delete', 'profile_rule', null, ['count' => $deleted], $request->user()?->admin_id, null, $request->ip(), $request->userAgent());

        return response()->json(['data' => ['deleted' => $deleted]]);
    }
}
