<?php

namespace App\Http\Controllers\Api\V1\Member;

use App\Application\Member\WorkspaceRuleService;
use App\Domain\Profile\MemberCatalogService;
use App\Domain\Profile\MemberWorkspaceService;
use App\Domain\Billing\OrderService;
use App\Domain\Billing\PaymentService;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

final class MemberWorkspaceController
{
    public function __construct(
        private readonly MemberWorkspaceService $workspace,
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
            return response()->json(['data' => $this->workspace->getSecurity($request->user()->id, $this->profileId($request))]);
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

        return response()->json(['data' => $this->workspace->updateSecurity($request->user()->id, $validated, $this->profileId($request))]);
    }

    public function privacy(Request $request): JsonResponse
    {
        if ($request->isMethod('get')) {
            return response()->json(['data' => $this->workspace->getPrivacy($request->user()->id, $this->profileId($request))]);
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
            'blocklists.denylist_ids' => 'sometimes|array',
            'blocklists.denylist_ids.*' => 'integer',
            'blocklists.parental' => 'sometimes|boolean',
            'deep_tracking_devices' => 'sometimes|array',
            'deep_tracking_devices.*' => 'string',
        ]);

        return response()->json(['data' => $this->workspace->updatePrivacy($request->user()->id, $validated, $this->profileId($request))]);
    }

    public function parental(Request $request): JsonResponse
    {
        if ($request->isMethod('get')) {
            return response()->json(['data' => $this->workspace->getParental($request->user()->id, $this->profileId($request))]);
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
            'blocked_items.*.name' => 'sometimes|string',
            'blocked_items.*.category' => 'sometimes|string',
            'blocked_categories' => 'sometimes|array',
            'blocked_categories.*.key' => 'sometimes|string',
        ]);

        return response()->json(['data' => $this->workspace->updateParental($request->user()->id, $validated, $this->profileId($request))]);
    }

    public function settings(Request $request): JsonResponse
    {
        if ($request->isMethod('get')) {
            return response()->json(['data' => $this->workspace->getSettings($request->user()->id, $this->profileId($request))]);
        }

        $validated = $request->validate([
            'locale' => 'required|string|max:20',
            'timezone' => 'required|string|max:64',
            'profile_name' => 'required|string|max:100',
            'default_action' => ['required', Rule::in(['allow', 'block'])],
            'block_response' => ['required', Rule::in(['nxdomain', 'zero_ip', 'refused'])],
        ]);

        return response()->json(['data' => $this->workspace->updateSettings($request->user()->id, $validated, $this->profileId($request))]);
    }

    public function password(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:6',
        ]);

        $this->workspace->changePassword(
            $request->user()->id,
            $validated['current_password'],
            $validated['new_password'],
        );

        return response()->json(['data' => ['updated' => true]]);
    }

    public function listRules(Request $request, string $listType): JsonResponse
    {
        return response()->json(['data' => $this->workspace->listRules($request->user()->id, $listType, $this->profileId($request))]);
    }

    public function allowlist(Request $request): JsonResponse
    {
        return $this->listRules($request, 'allow');
    }

    public function denylist(Request $request): JsonResponse
    {
        return $this->listRules($request, 'deny');
    }

    public function createRule(Request $request, string $listType): JsonResponse
    {
        $validated = $request->validate([
            'domain' => 'required|string|max:255',
            'match_type' => ['required', Rule::in(['exact', 'suffix', 'wildcard'])],
        ]);

        return response()->json([
            'data' => $this->workspace->createRule($request->user()->id, $listType, $validated, $this->profileId($request)),
        ], 201);
    }

    public function createAllowlistRule(Request $request): JsonResponse
    {
        return $this->createRule($request, 'allow');
    }

    public function createDenylistRule(Request $request): JsonResponse
    {
        return $this->createRule($request, 'deny');
    }

    public function deleteRule(Request $request, string $listType, string $ruleId): JsonResponse
    {
        return response()->json([
            'data' => $this->workspace->deleteRule($request->user()->id, $listType, $ruleId, $this->profileId($request)),
        ]);
    }

    public function deleteAllowlistRule(Request $request, string $ruleId): JsonResponse
    {
        return $this->deleteRule($request, 'allow', $ruleId);
    }

    public function deleteDenylistRule(Request $request, string $ruleId): JsonResponse
    {
        return $this->deleteRule($request, 'deny', $ruleId);
    }

    public function updateRule(Request $request, string $listType, string $ruleId): JsonResponse
    {
        $validated = $request->validate([
            'domain' => 'string|max:255',
            'match_type' => 'string|in:exact,suffix,wildcard',
            'enabled' => 'boolean',
        ]);

        return response()->json([
            'data' => $this->workspaceRuleService->updateRule($request->user()->id, $listType, $ruleId, $validated),
        ]);
    }

    public function updateAllowlistRule(Request $request, string $ruleId): JsonResponse
    {
        return $this->updateRule($request, 'allow', $ruleId);
    }

    public function updateDenylistRule(Request $request, string $ruleId): JsonResponse
    {
        return $this->updateRule($request, 'deny', $ruleId);
    }

    public function batchDeleteRule(Request $request, string $listType): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'string',
        ]);

        return response()->json([
            'data' => $this->workspace->batchDeleteRules($request->user()->id, $listType, $validated['ids'], $this->profileId($request)),
        ]);
    }

    public function batchDeleteAllowlist(Request $request): JsonResponse
    {
        return $this->batchDeleteRule($request, 'allow');
    }

    public function batchDeleteDenylist(Request $request): JsonResponse
    {
        return $this->batchDeleteRule($request, 'deny');
    }

    public function analytics(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->workspace->analytics($request->user()->id)]);
    }

    public function logs(Request $request): JsonResponse
    {
        $result = $this->workspace->logs($request->user()->id, $request->all());

        return response()->json($result);
    }

    public function membership(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->workspace->membership($request->user()->id)]);
    }

    public function upgrade(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'plan' => 'required_without:plan_code|string|max:30',
            'plan_code' => 'required_without:plan|string|max:30',
            'billing_cycle' => ['sometimes', Rule::in(['monthly', 'yearly'])],
        ]);

        return response()->json(['data' => $this->workspace->upgrade(
            $request->user()->id,
            $validated['plan_code'] ?? $validated['plan'],
            $validated['billing_cycle'] ?? 'monthly',
        )]);
    }

    public function dnsEndpoints(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->workspace->dnsEndpoints($request->user()->id, $this->profileId($request))]);
    }

    public function usage(Request $request): JsonResponse
    {
        $user = User::findOrFail($request->user()->id);
        $subscription = DB::table('subscriptions')->where('user_id', $user->id)->first();
        $plan = $subscription ? DB::table('plans')->where('code', $subscription->plan_code ?? 'free')->first() : null;
        
        $monthlyLimit = $plan && isset($plan->limits) ? (json_decode($plan->limits, true)['monthly_queries'] ?? null) : 300000;
        
        // Get usage from usage_records
        $queriesUsed = (int) DB::table('usage_records')
            ->where('user_id', $user->id)
            ->where('period', now()->format('Y-m'))
            ->sum('query_count');
        
        return response()->json([
            'data' => [
                'queries_used' => $queriesUsed,
                'queries_total' => $monthlyLimit,
                'is_unlimited' => $monthlyLimit === null,
                'upgrade_price' => 'US$3.99',
            ]
        ]);
    }

    public function wallet(Request $request): JsonResponse
    {
        $user = $request->user();
        $wallet = DB::table('wallets')->where('user_id', $user->id)->first();
        
        if (!$wallet) {
            DB::table('wallets')->insert([
                'user_id' => $user->id,
                'balance' => 0,
                'currency' => 'USD',
                'frozen' => 0,
                'version' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $wallet = DB::table('wallets')->where('user_id', $user->id)->first();
        }

        $balance = ((int) ($wallet->balance ?? 0)) / 100;
        
        return response()->json([
            'data' => [
                'balance' => number_format($balance, 2, '.', ''),
                'balance_minor' => (int) ($wallet->balance ?? 0),
                'currency' => $wallet->currency ?? 'USD',
            ]
        ]);
    }

    public function rechargeWallet(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:1|max:1000000',
        ]);

        $amountMinor = (int) round(((float) $validated['amount']) * 100);
        $order = (new OrderService())->create(
            userId: (string) $request->user()->id,
            planCode: 'wallet_topup',
            payableAmountMinor: $amountMinor,
            currency: 'USD',
            description: 'Wallet recharge',
            meta: ['source' => 'member_wallet_recharge'],
            idempotencyKey: 'wallet-topup-' . $request->user()->id . '-' . now()->format('YmdHisv'),
        );

        $tx = (new PaymentService())->createCheckout($order);
        $redirectUrl = $tx->meta['redirect_url'] ?? null;

        if (! is_string($redirectUrl) || ! str_starts_with($redirectUrl, 'https://checkout.stripe.com/')) {
            (new PaymentService())->handleSuccess((string) $tx->provider_session_id, null);

            return response()->json([
                'data' => [
                    'paid' => true,
                    'simulated' => true,
                    'order_id' => (string) $order->id,
                ],
            ]);
        }

        return response()->json([
            'data' => [
                'paid' => false,
                'order_id' => (string) $order->id,
                'pay_url' => $redirectUrl,
            ],
        ]);
    }

    public function subscription(Request $request): JsonResponse
    {
        $user = $request->user();
        $subscription = DB::table('subscriptions')
            ->where('user_id', $user->id)
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
                'link' => config('app.url') . '?ref=' . $user->id,
                'reward_amount' => 1.00,
                'currency' => 'USD',
            ]
        ]);
    }

    public function topDomains(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->workspace->topDomains($request->user()->id)]);
    }

    public function devices(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->workspace->devices($request->user()->id)]);
    }

    public function updateDevice(Request $request, string $deviceId): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
        ]);

        return response()->json(['data' => $this->workspace->updateDevice($request->user()->id, $deviceId, $validated)]);
    }

    public function deleteDevice(Request $request, string $deviceId): JsonResponse
    {
        return response()->json(['data' => $this->workspace->deleteDevice($request->user()->id, $deviceId)]);
    }
}
