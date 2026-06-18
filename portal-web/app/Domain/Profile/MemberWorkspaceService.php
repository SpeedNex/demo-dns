<?php

declare(strict_types=1);

namespace App\Domain\Profile;

use App\Domain\Billing\PlanCatalogService;
use App\Domain\Ingest\QueryLogReadService;
use App\Domain\Rule\ProfileRuleService;
use App\Models\Device;
use App\Models\Profile;
use App\Models\ProfileRule;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class MemberWorkspaceService
{
    private const DEFAULT_SECURITY = [
        'enabled' => true,
        'block_malware' => true,
        'block_phishing' => true,
        'block_command_and_control' => true,
        'block_cryptojacking' => true,
        'threat_intel' => true,
        'ai_threat_detection' => false,
        'google_safe_browsing' => true,
        'dns_rebind' => true,
        'idn_homograph' => true,
        'typo_squatting' => true,
        'dga_protection' => true,
        'block_new_domains' => true,
        'block_dynamic_dns' => false,
        'block_parked_domains' => true,
        'block_tld' => false,
        'child_abuse' => true,
    ];

    private const DEFAULT_PRIVACY = [
        'enabled' => true,
        'block_trackers' => true,
        'block_analytics' => true,
        'block_telemetry' => true,
        'anonymize_client_ip' => true,
        'allow_marketing_links' => false,
        'block_disguised_trackers' => true,
        'log_mode' => 'full',
        'blocklists' => ['allowlist_ids' => [], 'denylist_ids' => [], 'parental' => false],
        'deep_tracking_devices' => [],
    ];

    private const DEFAULT_PARENTAL = [
        'enabled' => false,
        'block_adult_content' => false,
        'block_gambling' => false,
        'block_gambling_basic' => false,
        'safe_search' => false,
        'force_safe_search' => false,
        'youtube_restricted_mode' => false,
        'force_youtube_restricted' => false,
        'block_bypass' => false,
        'time_limits' => [
            'weekday_start' => '00:00',
            'weekday_end' => '23:59',
            'weekend_start' => '00:00',
            'weekend_end' => '23:59',
            'per_day_minutes' => 0,
        ],
        'blocked_items' => [],
        'blocked_categories' => [],
    ];

    public function __construct(
        private readonly ProfileRuleService $profileRuleService = new ProfileRuleService(),
        private readonly QueryLogReadService $queryLogReader = new QueryLogReadService(),
        private readonly \App\Infrastructure\ClickHouse\MemberAnalyticsService $clickhouseAnalytics = new \App\Infrastructure\ClickHouse\MemberAnalyticsService(),
        private readonly PlanCatalogService $planCatalog = new PlanCatalogService(),
    ) {
    }

    public function primaryProfile(string $userId): Profile
    {
        $profile = Profile::where('user_id', $userId)->orderBy('created_at')->first();
        if ($profile instanceof Profile) {
            return $this->hydrateProfileSettings($profile);
        }

        $user = User::findOrFail($userId);

        return $this->hydrateProfileSettings(Profile::create([
            'user_id' => $userId,
            'name' => ($user->username ?: 'Member') . ' Default',
            'description' => 'Default member-center profile',
            'default_action' => 'allow',
            'block_response' => 'nxdomain',
            'security_enabled' => true,
            'security_settings' => self::DEFAULT_SECURITY,
            'privacy_enabled' => true,
            'privacy_settings' => self::DEFAULT_PRIVACY,
            'parental_enabled' => false,
            'parental_settings' => self::DEFAULT_PARENTAL,
            'safe_search_enabled' => false,
            'log_mode' => 'full',
        ]));
    }

    public function getSecurity(string $userId): array
    {
        return $this->securityPayload($this->primaryProfile($userId));
    }

    public function updateSecurity(string $userId, array $payload): array
    {
        $profile = $this->primaryProfile($userId);
        $settings = array_merge(self::DEFAULT_SECURITY, $profile->security_settings ?? [], $payload);

        $profile->update([
            'security_enabled' => (bool) $settings['enabled'],
            'security_settings' => $settings,
        ]);

        return $this->securityPayload($profile->fresh());
    }

    public function getPrivacy(string $userId): array
    {
        return $this->privacyPayload($this->primaryProfile($userId));
    }

    public function updatePrivacy(string $userId, array $payload): array
    {
        $profile = $this->primaryProfile($userId);
        $settings = array_merge(self::DEFAULT_PRIVACY, $profile->privacy_settings ?? [], $payload);

        $profile->update([
            'privacy_enabled' => (bool) $settings['enabled'],
            'privacy_settings' => $settings,
            'log_mode' => $settings['log_mode'],
        ]);

        return $this->privacyPayload($profile->fresh());
    }

    public function getParental(string $userId): array
    {
        return $this->parentalPayload($this->primaryProfile($userId));
    }

    public function updateParental(string $userId, array $payload): array
    {
        $profile = $this->primaryProfile($userId);
        $settings = array_merge(self::DEFAULT_PARENTAL, $profile->parental_settings ?? [], $payload);

        $profile->update([
            'parental_enabled' => (bool) $settings['enabled'],
            'parental_settings' => $settings,
            'safe_search_enabled' => (bool) $settings['safe_search'],
        ]);

        return $this->parentalPayload($profile->fresh());
    }

    public function getSettings(string $userId): array
    {
        $user = User::findOrFail($userId);
        $profile = $this->primaryProfile($userId);

        return [
            'locale' => $user->locale,
            'timezone' => $user->timezone,
            'profile_name' => $profile->name,
            'default_action' => $profile->default_action,
            'block_response' => $profile->block_response,
        ];
    }

    public function updateSettings(string $userId, array $payload): array
    {
        $user = User::findOrFail($userId);
        $profile = $this->primaryProfile($userId);

        $user->update([
            'locale' => $payload['locale'] ?? $user->locale,
            'timezone' => $payload['timezone'] ?? $user->timezone,
        ]);

        $profile->update([
            'name' => $payload['profile_name'] ?? $profile->name,
            'default_action' => $payload['default_action'] ?? $profile->default_action,
            'block_response' => $payload['block_response'] ?? $profile->block_response,
        ]);

        return $this->getSettings($userId);
    }

    public function changePassword(string $userId, string $currentPassword, string $newPassword): void
    {
        $user = User::findOrFail($userId);

        if (! Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'Current password is incorrect.',
            ]);
        }

        $user->update(['password' => $newPassword]);
    }

    public function listRules(string $userId, string $listType): array
    {
        $profile = $this->primaryProfile($userId);

        return ProfileRule::where('profile_id', $profile->id)
            ->where('list_type', $listType)
            ->orderByDesc('created_at')
            ->get()
            ->toArray();
    }

    public function createRule(string $userId, string $listType, array $payload): array
    {
        $profile = $this->primaryProfile($userId);

        return $this->profileRuleService->create($userId, $profile->id, [
            'list_type' => $listType,
            'match_type' => $payload['match_type'] ?? 'exact',
            'domain' => $payload['domain'] ?? '',
            'action' => $listType === 'allow' ? 'allow' : 'block',
        ]);
    }

    public function deleteRule(string $userId, string $listType, string $ruleId): array
    {
        $profile = $this->primaryProfile($userId);
        $rule = ProfileRule::where('profile_id', $profile->id)
            ->where('list_type', $listType)
            ->where('id', $ruleId)
            ->firstOrFail();

        $rule->delete();

        return [
            'id' => $ruleId,
            'deleted' => true,
        ];
    }

    /**
     * @param array<int, string> $ruleIds
     * @return array<string, mixed>
     */
    public function batchDeleteRules(string $userId, string $listType, array $ruleIds): array
    {
        $profile = $this->primaryProfile($userId);

        $existingIds = ProfileRule::where('profile_id', $profile->id)
            ->where('list_type', $listType)
            ->whereIn('id', $ruleIds)
            ->pluck('id')
            ->all();

        if ($existingIds === []) {
            return [
                'requested' => count($ruleIds),
                'deleted' => 0,
                'not_found' => array_values($ruleIds),
            ];
        }

        $notFound = array_values(array_diff($ruleIds, $existingIds));
        $deletedCount = ProfileRule::where('profile_id', $profile->id)
            ->where('list_type', $listType)
            ->whereIn('id', $existingIds)
            ->delete();

        return [
            'requested' => count($ruleIds),
            'deleted' => $deletedCount,
            'not_found' => $notFound,
        ];
    }

    public function analytics(string $userId): array
    {
        // Primary source: ClickHouse analytics (dns_logs)
        $ch = $this->clickhouseAnalytics->summaryForUser($userId);
        if (($ch['period_queries'] ?? 0) > 0) {
            return array_merge($ch, [
                'allowed_domains'     => $this->clickhouseAnalytics->allowedDomains($userId),
                'blocked_domains'     => $this->clickhouseAnalytics->blockedDomains($userId),
                'block_reasons'       => $this->clickhouseAnalytics->blockReasons($userId),
                'devices'             => $this->clickhouseAnalytics->topDevices($userId),
                'client_ips'          => $this->clickhouseAnalytics->topClientIps($userId),
                'root_domains'        => $this->clickhouseAnalytics->topRootDomains($userId),
                'encrypted_dns'       => $this->clickhouseAnalytics->encryptedDnsRatio($userId),
                'dnssec'             => $this->clickhouseAnalytics->dnssecRatio($userId),
            ]);
        }

        // Fallback: PostgreSQL query_log_entries (covers warm-up window)
        $pg = $this->queryLogReader->analytics($userId);
        return array_merge($pg, [
            'allowed_domains' => [],
            'blocked_domains' => [],
            'block_reasons'   => [],
            'devices'         => [],
            'client_ips'      => [],
            'root_domains'    => [],
            'encrypted_dns'   => ['total' => 0, 'encrypted' => 0, 'ratio_percent' => 0],
            'dnssec'          => ['total' => 0, 'validated' => 0, 'ratio_percent' => 0],
        ]);
    }

    public function logs(string $userId, array $filters): array
    {
        $result = $this->queryLogReader->logs($userId, $filters);

        return [
            'data' => $this->decorateLogs($userId, $result['data']),
            'meta' => $result['meta'],
        ];
    }

    public function membership(string $userId): array
    {
        $user = User::findOrFail($userId);
        $plans = $this->planCatalog->memberList();
        $currentPlan = collect($plans)->firstWhere('code', $user->plan_code ?: 'free');

        return [
            'plan' => $user->plan_code ?: 'free',
            'current_plan' => $currentPlan,
            'plans' => $plans,
            'stats' => $this->analytics($userId),
            'orders' => $this->orders($userId),
        ];
    }

    public function upgrade(string $userId, string $planCode, string $billingCycle = 'monthly'): array
    {
        $user = User::findOrFail($userId);
        $plan = $this->planCatalog->activePlan($planCode);
        $price = $plan->prices->firstWhere('billing_cycle', $billingCycle) ?? $plan->prices->first();
        if ($price === null) {
            throw ValidationException::withMessages([
                'billing_cycle' => 'No active price found for this billing cycle.',
            ]);
        }

        DB::transaction(function () use ($user, $plan, $price, $billingCycle): void {
            $user->update(['plan_code' => $plan->code]);

            DB::table('subscriptions')->updateOrInsert(
                ['user_id' => $user->id],
                [
                    'plan_code' => $plan->code,
                    'status' => 'active',
                    'monthly_query_limit' => $plan->limits['monthly_queries'] ?? null,
                    'current_period_start' => now(),
                    'current_period_end' => $billingCycle === 'yearly' ? now()->addYear() : now()->addMonth(),
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );

            DB::table('invoices')->insert([
                'user_id' => $user->id,
                'invoice_no' => 'PLAN-' . now()->format('YmdHis') . '-' . strtoupper($plan->code) . '-' . substr(md5($user->id . microtime()), 0, 6),
                'amount_minor' => (int) $price->amount_minor,
                'currency' => $price->currency,
                'status' => 'paid',
                'type' => 'subscription',
                'description' => $plan->name . ' ' . $billingCycle,
                'finalized' => true,
                'paid_at' => now(),
                'finalized_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return [
            'plan' => $plan->code,
            'upgraded' => true,
        ];
    }

    public function dnsEndpoints(string $userId): array
    {
        $profile = $this->primaryProfile($userId);

        return [
            'doh' => sprintf('https://doh.ocerdns.local/%s/dns-query', $profile->id),
            'dot' => sprintf('%s.dot.ocerdns.local', $profile->id),
            'ipv4' => '127.0.0.1',
        ];
    }

    public function devices(string $userId): array
    {
        return Device::where('user_id', $userId)
            ->orderByDesc('last_seen_at')
            ->get()
            ->map(fn (Device $device): array => [
                'id' => $device->id,
                'name' => $device->name,
                'info' => trim(($device->device_type ?: 'device') . ' ' . ($device->public_ip ?: '')),
                'last_seen_at' => optional($device->last_seen_at)?->toIso8601String(),
            ])
            ->all();
    }

    public function topDomains(string $userId): array
    {
        // 1. ClickHouse (authoritative when the writer is the dns-resolver).
        $ch = $this->clickhouseAnalytics->summaryForUser($userId);
        if (($ch['period_queries'] ?? 0) > 0) {
            return [
                'top_visited' => $ch['top_domains'] ?? [],
                'top_blocked' => $ch['top_blocked'] ?? [],
            ];
        }

        // 2. Postgres query_log_entries (covers the warm-up window
        //    before the ClickHouse materialized views have rolled up).
        //    Same image and same database as the resolver now, so this
        //    is an in-process read with no HTTP and no fallback.
        $analytics = $this->queryLogReader->analytics($userId);
        if (($analytics['period_queries'] ?? 0) > 0) {
            return [
                'top_visited' => $analytics['top_domains'] ?? [],
                'top_blocked' => $analytics['top_blocked'] ?? [],
            ];
        }

        return [
            'top_visited' => [],
            'top_blocked' => [],
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function decorateLogs(string $userId, array $items): array
    {
        $profiles = Profile::query()->where('user_id', $userId)->pluck('name', 'id');
        $devices = Device::query()->where('user_id', $userId)->get();
        $deviceMap = $devices->mapWithKeys(fn (Device $device): array => array_filter([
            $device->id => $device->name,
            $device->device_id => $device->name,
        ], fn ($value, $key) => $key !== null && $key !== '', ARRAY_FILTER_USE_BOTH));

        return array_map(function (array $item) use ($profiles, $deviceMap): array {
            return [
                'timestamp' => $item['timestamp'] ?? null,
                'domain' => $item['domain'] ?? '',
                'action' => $item['action'] ?? 'allowed',
                'device' => $deviceMap->get($item['device_id'] ?? '', $item['device_id'] ?? 'Unknown Device'),
                'profile_name' => $profiles->get($item['profile_id'] ?? '', $item['profile_id'] ?? 'Unknown Profile'),
                'reason' => $item['reason'] ?? null,
                'category' => $item['category'] ?? null,
            ];
        }, $items);
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{data: array<int, array<string, mixed>>, meta: array<string, int>}
     */
    private function sampleLogsResponse(string $userId, array $filters): array
    {
        $logs = $this->sampleLogs($userId);

        if (! empty($filters['action'])) {
            $logs = $logs->where('action', $filters['action']);
        }

        if (! empty($filters['domain'])) {
            $needle = strtolower((string) $filters['domain']);
            $logs = $logs->filter(fn (array $log): bool => str_contains(strtolower($log['domain']), $needle));
        }

        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = max(1, min(100, (int) ($filters['per_page'] ?? 20)));
        $total = $logs->count();
        $items = $logs->slice(($page - 1) * $perPage, $perPage)->values()->all();

        return [
            'data' => $items,
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
            ],
        ];
    }

    /**
     * @param Collection<int, array<string, mixed>> $logs
     * @return array<string, mixed>
     */
    private function sampleAnalytics(Collection $logs): array
    {
        return [
            'today_queries' => $logs->count(),
            'today_blocked' => $logs->where('action', 'blocked')->count(),
            'period_queries' => $logs->count(),
            'top_domains' => $this->aggregateDomains($logs),
            'top_blocked' => $this->aggregateDomains($logs->where('action', 'blocked')),
        ];
    }

    private function hydrateProfileSettings(Profile $profile): Profile
    {
        $profile->security_settings = array_merge(self::DEFAULT_SECURITY, $profile->security_settings ?? []);
        $profile->privacy_settings = array_merge(self::DEFAULT_PRIVACY, $profile->privacy_settings ?? []);
        $profile->parental_settings = array_merge(self::DEFAULT_PARENTAL, $profile->parental_settings ?? []);

        return $profile;
    }

    private function securityPayload(Profile $profile): array
    {
        return array_merge(self::DEFAULT_SECURITY, $profile->security_settings ?? [], [
            'enabled' => (bool) $profile->security_enabled,
        ]);
    }

    private function privacyPayload(Profile $profile): array
    {
        return array_merge(self::DEFAULT_PRIVACY, $profile->privacy_settings ?? [], [
            'enabled' => (bool) $profile->privacy_enabled,
            'log_mode' => $profile->log_mode ?: 'full',
        ]);
    }

    private function parentalPayload(Profile $profile): array
    {
        return array_merge(self::DEFAULT_PARENTAL, $profile->parental_settings ?? [], [
            'enabled' => (bool) $profile->parental_enabled,
            'safe_search' => (bool) $profile->safe_search_enabled,
        ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function sampleLogs(string $userId): Collection
    {
        $profile = $this->primaryProfile($userId);
        $devices = Device::where('user_id', $userId)->get();
        $deviceName = $devices->first()?->name ?? 'Default Device';
        $blockedDomain = ProfileRule::where('profile_id', $profile->id)
            ->where('list_type', 'deny')
            ->value('domain') ?? 'ads.example.com';
        $allowedDomain = ProfileRule::where('profile_id', $profile->id)
            ->where('list_type', 'allow')
            ->value('domain') ?? 'openai.com';

        return collect([
            [
                'timestamp' => now()->subMinutes(3)->toIso8601String(),
                'domain' => $blockedDomain,
                'action' => 'blocked',
                'device' => $deviceName,
                'profile_name' => $profile->name,
            ],
            [
                'timestamp' => now()->subMinutes(11)->toIso8601String(),
                'domain' => $allowedDomain,
                'action' => 'allowed',
                'device' => $deviceName,
                'profile_name' => $profile->name,
            ],
            [
                'timestamp' => now()->subHour()->toIso8601String(),
                'domain' => 'dns.google',
                'action' => 'allowed',
                'device' => $deviceName,
                'profile_name' => $profile->name,
            ],
            [
                'timestamp' => now()->subHours(2)->toIso8601String(),
                'domain' => 'tracker.example.net',
                'action' => 'blocked',
                'device' => $deviceName,
                'profile_name' => $profile->name,
            ],
        ])->sortByDesc('timestamp')->values();
    }

    /**
     * @param Collection<int, array<string, mixed>> $logs
     * @return array<int, array<string, mixed>>
     */
    private function aggregateDomains(Collection $logs): array
    {
        return $logs
            ->countBy('domain')
            ->map(fn (int $count, string $domain): array => [
                'domain' => $domain,
                'count' => $count,
            ])
            ->sortByDesc('count')
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function orders(string $userId): array
    {
        return DB::table('invoices')
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get(['created_at', 'description', 'amount_minor', 'status', 'currency'])
            ->map(fn ($invoice): array => [
                'created_at' => (string) $invoice->created_at,
                'description' => (string) ($invoice->description ?? ''),
                'amount_minor' => (int) $invoice->amount_minor,
                'status' => (string) $invoice->status,
                'currency' => (string) $invoice->currency,
            ])
            ->all();
    }
}
