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
            'device_models.*.key' => 'nullable|string|max:60',
            'device_models.*.name' => 'nullable|string|max:120',
            'device_models.*.desc' => 'nullable|string|max:500',
            'device_models.*.enabled' => 'nullable|boolean',
            'device_models.*.system' => 'nullable|boolean',
            'privacy_blocklists' => 'required|array',
            'privacy_blocklists.*.key' => 'nullable|string|max:60',
            'privacy_blocklists.*.name' => 'nullable|string|max:120',
            'privacy_blocklists.*.desc' => 'nullable|string|max:255',
            'privacy_blocklists.*.entries' => 'nullable|integer|min:0',
            'privacy_blocklists.*.days_ago' => 'nullable|integer|min:0',
            'privacy_blocklists.*.enabled' => 'nullable|boolean',
            'privacy_blocklists.*.system' => 'nullable|boolean',
            'privacy_blocklists.*.devices' => 'nullable|array',
            'privacy_blocklists.*.devices.*.key' => 'nullable|string|max:60',
            'privacy_blocklists.*.devices.*.name' => 'nullable|string|max:120',
            'privacy_blocklists.*.devices.*.icon' => 'nullable|string|max:10',
            'privacy_blocklists.*.devices.*.enabled' => 'nullable|boolean',
            'parental_presets' => 'required|array',
            'parental_presets.*.name' => 'nullable|string|max:120',
            'parental_presets.*.icon' => 'nullable|string|max:500',
            'parental_presets.*.category' => ['nullable', Rule::in(['website', 'app', 'game'])],
            'parental_presets.*.enabled' => 'nullable|boolean',
            'parental_presets.*.url' => 'nullable|string|max:500',
            'parental_categories' => 'required|array',
            'parental_categories.*.key' => 'nullable|string|max:60',
            'parental_categories.*.name' => 'nullable|string|max:120',
            'parental_categories.*.desc' => 'nullable|string|max:255',
            'parental_categories.*.enabled' => 'nullable|boolean',
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
            'list_type' => ['nullable', Rule::in(['allow', 'block'])],
            'domain' => 'nullable|string|max:255',
            'profile_id' => 'nullable|string|max:40',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = ProfileRule::query()->with('profile:id,profile_id,name,user_id');

        if (! empty($validated['list_type'])) {
            // 前端传 block/allow，数据库存 blocklist/allowlist，需映射
            $mapped = match ($validated['list_type']) {
                'block' => 'blocklist',
                'allow' => 'allowlist',
                default => $validated['list_type'],
            };
            $query->where('list_type', $mapped);
        }
        if (! empty($validated['domain'])) {
            $query->where('domain', 'like', '%' . $validated['domain'] . '%');
        }
        if (! empty($validated['profile_id'])) {
            $query->where('profile_id', $validated['profile_id']);
        }

        $perPage = (int) ($validated['per_page'] ?? 20);
        $paginator = $query->orderByDesc('created_at')->paginate($perPage);
        $items = collect($paginator->items());

        // 聚合用户信息，避免 N+1
        $userIds = $items
            ->map(fn (ProfileRule $r) => $r->profile?->user_id)
            ->filter()
            ->unique()
            ->values()
            ->all();
        $userMap = \App\Models\User::query()
            ->whereIn('uid', $userIds)
            ->get(['uid', 'username', 'email'])
            ->keyBy('uid');

        $rows = $items->map(function (ProfileRule $rule) use ($userMap): array {
            $profile = $rule->profile;
            $user = $profile && $profile->user_id ? $userMap->get($profile->user_id) : null;

            return [
                'id' => $rule->id,
                'profile_id' => $profile?->id,
                'profile_id' => $profile?->profile_id,
                'profile_name' => $profile?->name,
                'user_id' => $profile?->user_id,
                'username' => $user?->username,
                'user_email' => $user?->email,
                'list_type' => $rule->list_type,
                'match_type' => $rule->match_type,
                'domain' => $rule->domain,
                'action' => $rule->action,
                'enabled' => (bool) $rule->enabled,
                'created_at' => optional($rule->created_at)?->toIso8601String(),
            ];
        })->all();

        return response()->json([
            'data' => $rows,
            'meta' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'page' => $paginator->currentPage(),
            ],
        ]);
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
