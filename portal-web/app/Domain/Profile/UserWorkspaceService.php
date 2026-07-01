<?php

declare(strict_types=1);

namespace App\Domain\Profile;

use App\Domain\Billing\PlanCatalogService;
use App\Domain\Ingest\QueryLogReadService;
use App\Domain\Rule\ProfileRuleService;
use App\Models\Device;
use App\Models\Profile;
use App\Models\ProfileRule;
use App\Models\SystemConfig;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class UserWorkspaceService
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
        'blocklists' => ['allowlist_ids' => [], 'blocklist_ids' => [], 'parental' => false],
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
        private readonly \App\Infrastructure\ClickHouse\UserAnalyticsService $clickhouseAnalytics = new \App\Infrastructure\ClickHouse\UserAnalyticsService(),
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
            'description' => 'Default workspace profile',
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

    /**
     * 根据可选 profile_id 解析 Profile，未传或无效时 fallback 到 primaryProfile
     */
    public function resolveProfile(string $userId, ?string $profileId = null): Profile
    {
        if ($profileId !== null && $profileId !== '') {
            $profile = Profile::where('user_id', $userId)
                ->where(function ($query) use ($profileId): void {
                    $query->where('profile_id', $profileId);
                    if (ctype_digit($profileId)) {
                        $query->orWhere('id', (int) $profileId);
                    }
                })
                ->first();
            if ($profile instanceof Profile) {
                return $this->hydrateProfileSettings($profile);
            }
        }
        return $this->primaryProfile($userId);
    }

    public function getSecurity(string $userId, ?string $profileId = null): array
    {
        return $this->securityPayload($this->resolveProfile($userId, $profileId));
    }

    public function updateSecurity(string $userId, array $payload, ?string $profileId = null): array
    {
        $profile = $this->resolveProfile($userId, $profileId);
        $settings = array_merge(self::DEFAULT_SECURITY, $profile->security_settings ?? [], $payload);

        $profile->update([
            'security_enabled' => (bool) $settings['enabled'],
            'security_settings' => $settings,
        ]);

        $this->autoPublish($profile);

        return $this->securityPayload($profile->fresh());
    }

    public function getPrivacy(string $userId, ?string $profileId = null): array
    {
        return $this->privacyPayload($this->resolveProfile($userId, $profileId));
    }

    public function updatePrivacy(string $userId, array $payload, ?string $profileId = null): array
    {
        $profile = $this->resolveProfile($userId, $profileId);
        $settings = array_merge(self::DEFAULT_PRIVACY, $profile->privacy_settings ?? [], $payload);

        $profile->update([
            'privacy_enabled' => (bool) $settings['enabled'],
            'privacy_settings' => $settings,
        ]);

        $this->autoPublish($profile);

        return $this->privacyPayload($profile->fresh());
    }

    public function getParental(string $userId, ?string $profileId = null): array
    {
        return $this->parentalPayload($this->resolveProfile($userId, $profileId));
    }

    public function updateParental(string $userId, array $payload, ?string $profileId = null): array
    {
        $profile = $this->resolveProfile($userId, $profileId);
        $settings = array_merge(self::DEFAULT_PARENTAL, $profile->parental_settings ?? [], $payload);

        $profile->update([
            'parental_enabled' => (bool) $settings['enabled'],
            'parental_settings' => $settings,
            'safe_search_enabled' => (bool) $settings['safe_search'],
        ]);

        $this->autoPublish($profile);

        return $this->parentalPayload($profile->fresh());
    }

    public function getSettings(string $userId, ?string $profileId = null): array
    {
        $user = User::findOrFail($userId);
        $profile = $this->resolveProfile($userId, $profileId);

        return [
            'locale' => $user->locale,
            'timezone' => $user->timezone,
            'profile_name' => $profile->name,
            'default_action' => $profile->default_action,
            'block_response' => $profile->block_response,
        ];
    }

    public function updateSettings(string $userId, array $payload, ?string $profileId = null): array
    {
        $user = User::findOrFail($userId);
        $profile = $this->resolveProfile($userId, $profileId);

        $user->update([
            'locale' => $payload['locale'] ?? $user->locale,
            'timezone' => $payload['timezone'] ?? $user->timezone,
        ]);

        $profile->update([
            'name' => $payload['profile_name'] ?? $profile->name,
            'default_action' => $payload['default_action'] ?? $profile->default_action,
            'block_response' => $payload['block_response'] ?? $profile->block_response,
        ]);

        return $this->getSettings($userId, $profileId);
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

    public function listRules(string $userId, string $listType, ?string $profileId = null): array
    {
        $profile = $this->resolveProfile($userId, $profileId);
        $normalizedListType = $listType === 'allow' ? 'allowlist' : 'blocklist';

        return ProfileRule::where('profile_id', $profile->id)
            ->where('list_type', $normalizedListType)
            ->orderByDesc('created_at')
            ->get()
            ->toArray();
    }

    public function createRule(string $userId, string $listType, array $payload, ?string $profileId = null): array
    {
        $profile = $this->resolveProfile($userId, $profileId);
        $normalizedListType = $listType === 'allow' ? 'allowlist' : 'blocklist';

        // 2026-06-22: 系统默认按 suffix 匹配，自动覆盖该域名下所有子域名，前端不再展示 match_type 表单
        $matchType = (string) ($payload['match_type'] ?? 'suffix');
        if (!in_array($matchType, ['exact', 'suffix', 'wildcard'], true)) {
            $matchType = 'suffix';
        }

        $result = $this->profileRuleService->create($userId, $profile->profile_id, [
            'list_type' => $normalizedListType,
            'match_type' => $matchType,
            'domain' => $payload['domain'] ?? '',
            'action' => $listType === 'allow' ? 'allow' : 'block',
        ]);

        $this->autoPublish($profile);

        return $result;
    }

    public function deleteRule(string $userId, string $listType, string $ruleId, ?string $profileId = null): array
    {
        $profile = $this->resolveProfile($userId, $profileId);
        $normalizedListType = $listType === 'allow' ? 'allowlist' : 'blocklist';
        $rule = ProfileRule::where('profile_id', $profile->id)
            ->where('list_type', $normalizedListType)
            ->where('id', $ruleId)
            ->firstOrFail();

        $rule->delete();

        $this->autoPublish($profile);

        return [
            'id' => $ruleId,
            'deleted' => true,
        ];
    }

    /**
     * @param array<int, string> $ruleIds
     * @return array<string, mixed>
     */
    public function batchDeleteRules(string $userId, string $listType, array $ruleIds, ?string $profileId = null): array
    {
        $profile = $this->resolveProfile($userId, $profileId);
        $normalizedListType = $listType === 'allow' ? 'allowlist' : 'blocklist';

        $existingIds = ProfileRule::where('profile_id', $profile->id)
            ->where('list_type', $normalizedListType)
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
            ->where('list_type', $normalizedListType)
            ->whereIn('id', $existingIds)
            ->delete();

        $this->autoPublish($profile);

        return [
            'requested' => count($ruleIds),
            'deleted' => $deletedCount,
            'not_found' => $notFound,
        ];
    }

    public function analytics(string $userId, ?string $profileId = null): array
    {
        // CH dns_logs.profile_id 存 uid 字符串
        $profileUid = ($profileId !== null && $profileId !== '') ? $profileId : null;

        // ClickHouse analytics (dns_logs)
        $ch = $this->clickhouseAnalytics->summaryForUser($userId, $profileUid);
        if (($ch['period_queries'] ?? 0) > 0) {
            return array_merge($ch, [
                'allowed_domains'     => $this->clickhouseAnalytics->allowedDomains($userId, 20, $profileUid),
                'blocked_domains'     => $this->clickhouseAnalytics->blockedDomains($userId, 20, $profileUid),
                'block_reasons'       => $this->clickhouseAnalytics->blockReasons($userId, 10, $profileUid),
                'devices'             => $this->clickhouseAnalytics->topDevices($userId, 10, $profileUid),
                'client_ips'          => $this->clickhouseAnalytics->topClientIps($userId, 10, $profileUid),
                'root_domains'        => $this->clickhouseAnalytics->topRootDomains($userId, 20, $profileUid),
                'encrypted_dns'       => $this->clickhouseAnalytics->encryptedDnsRatio($userId, $profileUid),
                'dnssec'             => $this->clickhouseAnalytics->dnssecRatio($userId, $profileUid),
            ]);
        }

        // 2026-06-22: query_log_entries (PG) fallback 已删除，该表不再写入。
        return array_merge($ch, [
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
        // 当前 profile 隔离：必须按 profile PK 过滤，且 ownership 校验
        $profileUid = isset($filters['profile_id']) && is_string($filters['profile_id']) ? $filters['profile_id'] : null;
        $profilePk = null;
        if ($profileUid !== null && $profileUid !== '') {
            try {
                // 校验 ownership：用户拥有该 profile
                $this->resolveProfile($userId, $profileUid);
                // CH 存的是 profile_id 字符串，直接传原文
                $filters['profile_id'] = $profileUid;
            } catch (\Throwable) {
                // 越权访问 / profile 不存在 → 强制 0 行
                $filters['profile_id'] = '-';
            }
        }

        try {
            $result = $this->queryLogReader->logs($userId, $filters);
        } catch (\Throwable) {
            $result = [
                'data' => [],
                'meta' => [
                    'page' => (int) ($filters['page'] ?? 1),
                    'per_page' => (int) ($filters['per_page'] ?? 20),
                    'total' => 0,
                ],
            ];
        }

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
            'stats' => $this->analytics($userId, null),
            // 订单表(dns_orders)已在计费重构中删除，改用 subscriptions 管理
            'orders' => [],
        ];
    }

    public function dnsEndpoints(string $userId, ?string $profileId = null): array
    {
        $profile = $this->resolveProfile($userId, $profileId);
        $domain = $this->getDnsDomain();
        // V2.2: 使用 profile_id（6位 hex 字符串）作为 DNS 路由 key
        $shortId = $profile->profile_id;

        // 每个 Profile 稳定绑定一个在线 resolver IPv4，避免一个方案展示多个服务器。
        // 2026-06-22: 单一事实源 — nodes.status 列已 drop，用 install_status + last_heartbeat_at 阈值即时算"在线"。
        $threshold = (int) env('NODE_HEARTBEAT_STALE_SECONDS', 90);
        $onlineIps = DB::table('resolver_nodes')
            ->where('install_status', 'installed')
            ->whereNotNull('last_heartbeat_at')
            ->where('last_heartbeat_at', '>', now()->subSeconds($threshold))
            ->whereNotNull('public_ipv4')
            ->where('public_ipv4', '!=', '')
            ->orderBy('id')
            ->pluck('public_ipv4')
            ->map(fn ($ip) => trim($ip))
            ->filter()
            ->unique()
            ->values()
            ->all();
        $boundIpv4 = null;
        if ($onlineIps !== []) {
            $boundIpv4 = $onlineIps[hexdec(substr(hash('crc32b', (string) $shortId), 0, 8)) % count($onlineIps)];
        }

        $host = sprintf('%s.%s', $shortId, $domain);

        return [
            'profile_id' => $shortId,
            'doh' => sprintf('https://%s/%s', $domain, $shortId),
            'dot' => $host,
            'doq' => $host,
            'doq_url' => sprintf('quic://%s:853', $host),
            'ipv6' => [sprintf('2606:%s:%s::53', substr($shortId, 0, 2), substr($shortId, 2, 4))],
            'ipv4' => $boundIpv4 ? [$boundIpv4] : [],
            'server_ip' => $boundIpv4,
        ];
    }

    /**
     * 从后台 DNS 设置中读取 DNS 域名，优先 dns.dns_domain，回退 basic.dns_domain，默认 dns.ocerlink.com
     */
    private function getDnsDomain(): string
    {
        $dns = SystemConfig::query()->where('config_key', 'dns')->first();
        if ($dns && is_array($dns->config_value) && !empty($dns->config_value['dns_domain'])) {
            return $dns->config_value['dns_domain'];
        }
        $basic = SystemConfig::query()->where('config_key', 'basic')->first();
        if ($basic && is_array($basic->config_value) && !empty($basic->config_value['dns_domain'])) {
            return $basic->config_value['dns_domain'];
        }
        return 'dns.ocerlink.com';
    }

    public function devices(string $userId): array
    {
        return Device::where('user_id', $userId)
            ->orderByDesc('last_seen_at')
            ->get()
            ->map(fn (Device $device): array => [
                'id' => $device->id,
                'name' => $device->name,
                // 2026-06-25: 修复 P3-B — device_type 改读 devices.device_type 字段，
                // 兼容历史无值情况时回退到 protocol 列，保持向后兼容
                'device_type' => $device->device_type ?: ($device->protocol ?: 'device'),
                'device_os' => $device->device_os,
                'protocol' => $device->protocol,
                'source_ip' => $device->ip_hash ? 'hashed' : ($device->source_ip ?? null),
                // 2026-06-25: 修复 P3-B — 与 Admin API 字段对齐，统一返回 device_uid
                'device_uid' => $device->device_uid,
                'device_id' => $device->device_uid,
                'info' => trim(($device->device_type ?: $device->protocol ?: 'device') . ' ' . ($device->device_uid ?: '')),
                'last_seen_at' => optional($device->last_seen_at)?->toIso8601String(),
            ])
            ->all();
    }

    public function updateDevice(string $userId, string $deviceId, array $payload): array
    {
        $device = Device::query()
            ->where('user_id', $userId)
            ->where('id', $deviceId)
            ->firstOrFail();

        $device->update([
            'name' => $payload['name'] ?? $device->name,
        ]);

        return [
            'id' => $device->id,
            'name' => $device->name,
            'device_type' => $device->protocol,
            'source_ip' => $device->ip_hash ? 'hashed' : null,
            'last_seen_at' => optional($device->last_seen_at)?->toIso8601String(),
        ];
    }

    public function deleteDevice(string $userId, string $deviceId): array
    {
        $device = Device::query()
            ->where('user_id', $userId)
            ->where('id', $deviceId)
            ->firstOrFail();

        $device->delete();

        return [
            'id' => $deviceId,
            'deleted' => true,
        ];
    }

    public function topDomains(string $userId): array
    {
        $ch = $this->clickhouseAnalytics->summaryForUser($userId);
        if (($ch['period_queries'] ?? 0) > 0) {
            return [
                'top_visited' => $ch['top_domains'] ?? [],
                'top_blocked' => $ch['top_blocked'] ?? [],
            ];
        }

        // 2026-06-22: query_log_entries (PG) fallback 已删除。
        return [
            'top_visited' => [],
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function decorateLogs(string $userId, array $items): array
    {
        $profileMap = [];
        foreach (Profile::query()->where('user_id', $userId)->get(['id', 'profile_id', 'name']) as $profile) {
            $profileMap[(string) $profile->id] = $profile->name;
            if ($profile->profile_id !== null && $profile->profile_id !== '') {
                $profileMap[(string) $profile->profile_id] = $profile->name;
            }
        }
        $devices = Device::query()->where('user_id', $userId)->get();
        $deviceMap = $devices->mapWithKeys(fn (Device $device): array => array_filter([
            $device->id => $device->name,
            $device->device_uid => $device->name,
        ], fn ($value, $key) => $key !== null && $key !== '', ARRAY_FILTER_USE_BOTH));

        return array_map(function (array $item) use ($profileMap, $deviceMap): array {
            $action = match (strtolower((string) ($item['action'] ?? ''))) {
                'block' => 'blocked',
                'blocked' => 'blocked',
                'allow' => 'allowed',
                default => 'allowed',
            };

            return [
                'timestamp' => $item['timestamp'] ?? null,
                'domain' => $item['domain'] ?? '',
                'action' => $action,
                'device' => $deviceMap->get($item['device_id'] ?? '', $item['device_id'] ?? 'Unknown Device'),
                'profile_name' => $profileMap[(string) ($item['profile_id'] ?? '')] ?? ($item['profile_id'] ?? 'Unknown Profile'),
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
            'today_blocked' => $logs->whereIn('action', ['block', 'blocked'])->count(),
            'period_queries' => $logs->count(),
            'top_domains' => $this->aggregateDomains($logs),
            'top_blocked' => $this->aggregateDomains($logs->whereIn('action', ['block', 'blocked'])),
        ];
    }

    private function hydrateProfileSettings(Profile $profile): Profile
    {
        $securitySettings = is_string($profile->security_settings)
            ? json_decode($profile->security_settings, true) ?? []
            : ($profile->security_settings ?? []);

        $privacySettings = is_string($profile->privacy_settings)
            ? json_decode($profile->privacy_settings, true) ?? []
            : ($profile->privacy_settings ?? []);

        $parentalSettings = is_string($profile->parental_settings)
            ? json_decode($profile->parental_settings, true) ?? []
            : ($profile->parental_settings ?? []);

        $profile->security_settings = array_merge(self::DEFAULT_SECURITY, $securitySettings);
        $profile->privacy_settings = array_merge(self::DEFAULT_PRIVACY, $privacySettings);
        $profile->parental_settings = array_merge(self::DEFAULT_PARENTAL, $parentalSettings);

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
        $privacySettings = is_array($profile->privacy_settings)
            ? $profile->privacy_settings
            : [];

        return array_merge(self::DEFAULT_PRIVACY, $privacySettings, [
            'enabled' => (bool) $profile->privacy_enabled,
            'log_mode' => ($privacySettings['log_mode'] ?? $profile->log_mode) ?: 'full',
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
            ->whereIn('list_type', ['block', 'blocklist'])
            ->value('domain') ?? 'ads.example.com';
        $allowedDomain = ProfileRule::where('profile_id', $profile->id)
            ->whereIn('list_type', ['allow', 'allowlist'])
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
        return DB::table('orders')
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get(['created_at', 'plan_code_snapshot', 'payable_amount_minor', 'status', 'currency'])
            ->map(fn ($order): array => [
                'created_at' => (string) $order->created_at,
                'description' => (string) ($order->plan_code_snapshot ?? ''),
                'amount_minor' => (int) $order->payable_amount_minor,
                'status' => (string) $order->status,
                'currency' => (string) $order->currency,
            ])
            ->all();
    }

    /**
     * 自动发布 Profile 配置。
     * 当规则或设置变更后自动触发，无需用户手动发布。
     */
    public function autoPublish(Profile $profile): void
    {
        try {
            $publishService = new ProfilePublishService(
                new ProfileConfigBuilder(),
                new \App\Domain\Publish\PublishService(),
            );

            $rules = ProfileRule::where('profile_id', $profile->id)
                ->where('enabled', true)
                ->get()
                ->toArray();

            $featureSettings = [
                'security' => $profile->security_settings ?? self::DEFAULT_SECURITY,
                'privacy' => $profile->privacy_settings ?? self::DEFAULT_PRIVACY,
                'parental' => $profile->parental_settings ?? self::DEFAULT_PARENTAL,
            ];

            // 2026-06-27: 将 blocked_categories 从 parental_settings 转换为 category:parental 规则。
            // 从 rule_items 表中查找对应分类的域名，加入到 $rules 中供 Resolver 引擎加载。
            $parentalSettings = $featureSettings['parental'] ?? [];
            $blockedCategories = $parentalSettings['blocked_categories'] ?? [];
            if (!empty($blockedCategories) && is_array($blockedCategories)) {
                $categoryKeys = array_map(fn ($c) => is_string($c) ? $c : ($c['key'] ?? ''), $blockedCategories);
                $categoryKeys = array_filter($categoryKeys);
                if (!empty($categoryKeys)) {
                    $categoryRules = \App\Models\RuleItem::whereIn('category', $categoryKeys)
                        ->where('action', 'block')
                        ->get(['domain', 'category'])
                        ->map(fn ($item) => [
                            'list_type' => 'category:parental:' . $item->category,
                            'match_type' => 'suffix',
                            'domain' => $item->domain,
                            'normalized_domain' => \App\Domain\Profile\DomainNormalizer::normalize($item->domain),
                            'action' => 'block',
                            'enabled' => true,
                            'category' => $item->category,
                            'rule_id' => 'cat_' . $item->category . '_' . md5($item->domain),
                        ])
                        ->toArray();
                    $rules = array_merge($rules, $categoryRules);
                }
            }

            \Illuminate\Support\Facades\Log::info('AutoPublish triggered', [
                'profile_id' => $profile->profile_id,
                'rules_count' => count($rules),
                'feature_settings' => $featureSettings,
            ]);

            $result = $publishService->publish(
                $profile->toArray(),
                $rules,
                $featureSettings,
            );

            // 同步更新 Profile 的 version 和 published_at，确保下次发布版本号递增
            $profile->update([
                'version' => (int) ($result['config_version'] ?? 0),
                'published_at' => now(),
            ]);

            \Illuminate\Support\Facades\Log::info('AutoPublish succeeded', [
                'profile_id' => $profile->profile_id,
                'result' => $result,
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('AutoPublish failed', [
                'profile_id' => $profile->profile_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
