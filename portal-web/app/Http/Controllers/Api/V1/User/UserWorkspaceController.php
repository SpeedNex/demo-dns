<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Application\Member\WorkspaceRuleService;
use App\Domain\Billing\PlanCatalogService;
use App\Domain\Profile\MemberCatalogService;
use App\Domain\Profile\UserWorkspaceService;
use App\Domain\Billing\PaymentService;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class UserWorkspaceController
{
    public function __construct(
        private readonly UserWorkspaceService $workspace,
        private readonly WorkspaceRuleService $workspaceRuleService,
        private readonly MemberCatalogService $catalogs = new MemberCatalogService(),
    ) {
    }

    private function profileId(Request $request): ?string
    {
        $id = $request->input('profile_id');
        return is_string($id) && $id !== '' ? $id : null;
    }

    public function catalogs(): JsonResponse
    {
        return response()->json(['data' => $this->catalogs->get()]);
    }

    public function security(Request $request): JsonResponse
    {
        if ($request->isMethod('get')) {
            return response()->json(['data' => $this->workspace->getSecurity($request->user()->uid, $this->profileId($request))]);
        }

        return $this->updateSecurity($request);
    }

    public function updateSecurity(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'enabled' => 'required|boolean',
            'block_malware' => 'sometimes|boolean',
            'block_phishing' => 'sometimes|boolean',
            'block_command_and_control' => 'sometimes|boolean',
            'block_cryptojacking' => 'sometimes|boolean',
            'threat_intel' => 'sometimes|boolean',
            'ai_threat_detection' => 'sometimes|boolean',
            'google_safe_browsing' => 'sometimes|boolean',
            'dns_rebind' => 'sometimes|boolean',
            'idn_homograph' => 'sometimes|boolean',
            'typo_squatting' => 'sometimes|boolean',
            'dga_protection' => 'sometimes|boolean',
            'block_new_domains' => 'sometimes|boolean',
            'block_dynamic_dns' => 'sometimes|boolean',
            'block_parked_domains' => 'sometimes|boolean',
            'block_tld' => 'sometimes|boolean',
            'child_abuse' => 'sometimes|boolean',
        ]);

        return response()->json(['data' => $this->workspace->updateSecurity($request->user()->uid, $validated, $this->profileId($request))]);
    }

    public function privacy(Request $request): JsonResponse
    {
        if ($request->isMethod('get')) {
            return response()->json(['data' => $this->workspace->getPrivacy($request->user()->uid, $this->profileId($request))]);
        }

        return $this->updatePrivacy($request);
    }

    public function updatePrivacy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'enabled' => 'required|boolean',
            'block_trackers' => 'sometimes|boolean',
            'block_analytics' => 'sometimes|boolean',
            'block_telemetry' => 'sometimes|boolean',
            'anonymize_client_ip' => 'sometimes|boolean',
            'allow_marketing_links' => 'sometimes|boolean',
            'block_disguised_trackers' => 'sometimes|boolean',
            'log_mode' => ['sometimes', Rule::in(['full', 'blocked_only', 'disabled'])],
            'blocklists' => 'sometimes|array',
            'blocklists.allowlist_ids' => 'sometimes|array',
            'blocklists.allowlist_ids.*' => 'integer',
            'blocklists.blocklist_ids' => 'sometimes|array',
            'blocklists.blocklist_ids.*' => 'integer',
            'blocklists.parental' => 'sometimes|boolean',
            'deep_tracking_devices' => 'sometimes|array',
            'deep_tracking_devices.*' => 'string',
        ]);

        // 手动合并动态 blocklist 类别 (phishing/malware/ads_tracking/third_party_tracking/deep_tracking/...)
        // 因为 blocklists.* boolean 与 array 子键规则同时存在会冲突，故单独处理
        $rawBlocklists = $request->input('blocklists', []);
        $knownKeys = ['allowlist_ids', 'blocklist_ids', 'parental'];
        $dynamic = [];
        foreach ($rawBlocklists as $k => $v) {
            if (!in_array($k, $knownKeys, true)) {
                $dynamic[$k] = filter_var($v, FILTER_VALIDATE_BOOLEAN);
            }
        }
        $validated['blocklists'] = array_merge(
            $dynamic,
            [
                'allowlist_ids' => $request->input('blocklists.allowlist_ids', []),
                'blocklist_ids' => $request->input('blocklists.blocklist_ids', []),
                'parental' => $request->boolean('blocklists.parental'),
            ]
        );

        return response()->json(['data' => $this->workspace->updatePrivacy($request->user()->uid, $validated, $this->profileId($request))]);
    }

    public function parental(Request $request): JsonResponse
    {
        if ($request->isMethod('get')) {
            return response()->json(['data' => $this->workspace->getParental($request->user()->uid, $this->profileId($request))]);
        }

        return $this->updateParental($request);
    }

    public function updateParental(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'enabled' => 'required|boolean',
            'block_adult_content' => 'sometimes|boolean',
            'block_gambling' => 'sometimes|boolean',
            'block_gambling_basic' => 'sometimes|boolean',
            'safe_search' => 'sometimes|boolean',
            'force_safe_search' => 'sometimes|boolean',
            'youtube_restricted_mode' => 'sometimes|boolean',
            'force_youtube_restricted' => 'sometimes|boolean',
            'block_bypass' => 'sometimes|boolean',
            'time_limits' => 'sometimes|array',
            'time_limits.weekday_start' => 'sometimes|string',
            'time_limits.weekday_end' => 'sometimes|string',
            'time_limits.weekend_start' => 'sometimes|string',
            'time_limits.weekend_end' => 'sometimes|string',
            'time_limits.per_day_minutes' => 'sometimes|integer|min:0',
            'blocked_items' => 'sometimes|array',
            'blocked_items.*.name' => 'sometimes',
            'blocked_items.*.category' => 'sometimes|string',
            'blocked_categories' => 'sometimes|array',
            'blocked_categories.*.key' => 'sometimes|string',
        ]);

        return response()->json(['data' => $this->workspace->updateParental($request->user()->uid, $validated, $this->profileId($request))]);
    }

    public function settings(Request $request): JsonResponse
    {
        if ($request->isMethod('get')) {
            return response()->json(['data' => $this->workspace->getSettings($request->user()->uid, $this->profileId($request))]);
        }

        $validated = $request->validate([
            'locale' => 'nullable|string|max:20',
            'timezone' => 'nullable|string|max:64',
            'profile_name' => 'nullable|string|max:100',
            'default_action' => ['nullable', Rule::in(['allow', 'block'])],
            'block_response' => ['nullable', Rule::in(['nxdomain', 'zero_ip', 'refused'])],
        ]);

        return response()->json(['data' => $this->workspace->updateSettings($request->user()->uid, $validated, $this->profileId($request))]);
    }

    public function password(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:6',
        ]);

        $this->workspace->changePassword(
            $request->user()->uid,
            $validated['current_password'],
            $validated['new_password'],
        );

        return response()->json(['data' => ['updated' => true]]);
    }

    public function email(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email|max:255|unique:App\Models\User,email',
            'password' => 'required|string',
        ]);

        $user = $request->user();

        if (!\Illuminate\Support\Facades\Hash::check($validated['password'], $user->password)) {
            return response()->json(['message' => __('auth.password')], 422);
        }

        $user->update(['email' => $validated['email']]);

        return response()->json(['data' => ['email' => $user->email]]);
    }

    public function listRules(Request $request, string $listType): JsonResponse
    {
        return response()->json(['data' => $this->workspace->listRules($request->user()->uid, $listType, $this->profileId($request))]);
    }

    public function allowlist(Request $request): JsonResponse
    {
        return $this->listRules($request, 'allow');
    }

    public function blocklist(Request $request): JsonResponse
    {
        return $this->listRules($request, 'block');
    }

    public function createRule(Request $request, string $listType): JsonResponse
    {
        // 2026-06-22: match_type 不再必填，系统默认 suffix 匹配（覆盖子域名）
        $validated = $request->validate([
            'domain' => 'required|string|max:255',
            'match_type' => ['sometimes', Rule::in(['exact', 'suffix', 'wildcard'])],
        ]);

        return response()->json([
            'data' => $this->workspace->createRule($request->user()->uid, $listType, $validated, $this->profileId($request)),
        ], 201);
    }

    public function createAllowlistRule(Request $request): JsonResponse
    {
        return $this->createRule($request, 'allow');
    }

    public function createBlocklistRule(Request $request): JsonResponse
    {
        return $this->createRule($request, 'block');
    }

    public function deleteRule(Request $request, string $listType, string $ruleId): JsonResponse
    {
        return response()->json([
            'data' => $this->workspace->deleteRule($request->user()->uid, $listType, $ruleId, $this->profileId($request)),
        ]);
    }

    public function deleteAllowlistRule(Request $request, string $ruleId): JsonResponse
    {
        return $this->deleteRule($request, 'allow', $ruleId);
    }

    public function deleteBlocklistRule(Request $request, string $ruleId): JsonResponse
    {
        return $this->deleteRule($request, 'block', $ruleId);
    }

    public function updateRule(Request $request, string $listType, string $ruleId): JsonResponse
    {
        $validated = $request->validate([
            'domain' => 'string|max:255',
            'match_type' => 'string|in:exact,suffix,wildcard',
            'enabled' => 'boolean',
        ]);

        return response()->json([
            'data' => $this->workspaceRuleService->updateRule($request->user()->uid, $listType, $ruleId, $validated, $this->profileId($request)),
        ]);
    }

    public function updateAllowlistRule(Request $request, string $ruleId): JsonResponse
    {
        return $this->updateRule($request, 'allow', $ruleId);
    }

    public function updateBlocklistRule(Request $request, string $ruleId): JsonResponse
    {
        return $this->updateRule($request, 'block', $ruleId);
    }

    public function batchDeleteRule(Request $request, string $listType): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'string',
        ]);

        return response()->json([
            'data' => $this->workspace->batchDeleteRules($request->user()->uid, $listType, $validated['ids'], $this->profileId($request)),
        ]);
    }

    public function batchDeleteAllowlist(Request $request): JsonResponse
    {
        return $this->batchDeleteRule($request, 'allow');
    }

    public function batchDeleteBlocklist(Request $request): JsonResponse
    {
        return $this->batchDeleteRule($request, 'block');
    }

    public function analytics(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->workspace->analytics($request->user()->uid, $this->profileId($request))]);
    }

    public function logs(Request $request): JsonResponse
    {
        $result = $this->workspace->logs($request->user()->uid, $request->all());

        return response()->json($result);
    }

    public function membership(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->workspace->membership($request->user()->uid)]);
    }

    public function paymentMethods(Request $request): JsonResponse
    {
        $payment = new PaymentService();
        $methods = $payment->paymentMethodOptions();

        return response()->json([
            'data' => [
                'methods' => $methods,
                'default' => $methods[0]['value'] ?? 'card',
            ],
        ]);
    }

    public function dnsEndpoints(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->workspace->dnsEndpoints($request->user()->uid, $this->profileId($request))]);
    }

    public function usage(Request $request): JsonResponse
    {
        $user = User::findOrFail($request->user()->uid);
        (new PlanCatalogService())->ensureDefaults();

        $subscription = DB::table('subscriptions')
            ->where('user_id', $user->uid)
            ->whereIn('status', ['active', 'trialing', 'past_due'])
            ->orderByDesc('id')
            ->first();
        $planCode = (string) ($subscription->plan_code ?? $user->plan_code ?? 'free');
        $plan = DB::table('plans')->where('code', $planCode)->first();
        $limits = is_string($plan?->limits ?? null) ? json_decode($plan->limits, true) : [];
        $limits = is_array($limits) ? $limits : [];

        $monthlyLimit = array_key_exists('monthly_queries', $limits)
            ? $limits['monthly_queries']
            : 300000;

        $periodIds = DB::table('billing_periods')
            ->where('user_id', $user->uid)
            ->where('period_start', '<=', now())
            ->where('period_end', '>=', now())
            ->pluck('id');
        $queriesUsed = (int) DB::table('usage_records')
            ->where('user_id', $user->uid)
            ->whereIn('billing_period_id', $periodIds)
            ->sum('query_count');
        
        return response()->json([
            'data' => [
                'queries_used' => $queriesUsed,
                'queries_total' => $monthlyLimit,
                'is_unlimited' => $monthlyLimit === null,
                'upgrade_price' => 'USD3.99',
                'quota_status' => (string) ($subscription->quota_status ?? 'normal'),
                'plan_code' => $planCode,
            ]
        ]);
    }

    public function subscription(Request $request): JsonResponse
    {
        $user = $request->user();
        $subscription = DB::table('subscriptions')
            ->where('user_id', $user->uid)
            ->first();
        
        if (!$subscription) {
            return response()->json(['data' => null]);
        }
        
        $plan = DB::table('plans')->where('code', $subscription->plan_code ?? 'free')->first();
        
        return response()->json([
            'data' => [
                'plan_name' => $plan ? $plan->name : 'Free',
                'status' => $subscription->status,
                'expires_at' => $subscription->current_period_end,
                'current_period_start' => $subscription->current_period_start,
            ]
        ]);
    }

    public function referralLink(Request $request): JsonResponse
    {
        $user = $request->user();
        
        return response()->json([
            'data' => [
                'link' => config('app.url') . '?ref=' . $user->uid,
                'reward_amount' => 1.00,
                'currency' => 'USD',
            ]
        ]);
    }

    public function topDomains(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->workspace->topDomains($request->user()->uid)]);
    }

    public function devices(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->workspace->devices($request->user()->uid)]);
    }

    public function updateDevice(Request $request, string $deviceId): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
        ]);

        return response()->json(['data' => $this->workspace->updateDevice($request->user()->uid, $deviceId, $validated)]);
    }

    public function deleteDevice(Request $request, string $deviceId): JsonResponse
    {
        return response()->json(['data' => $this->workspace->deleteDevice($request->user()->uid, $deviceId)]);
    }
}
