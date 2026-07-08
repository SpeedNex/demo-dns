<?php

declare(strict_types=1);

namespace App\Application\Member;

use App\Domain\Profile\DomainNormalizer;
use App\Domain\Profile\ProfileConfigBuilder;
use App\Domain\Profile\ProfilePublishService;
use App\Domain\Profile\RuleCategoryResolver;
use App\Domain\Publish\PublishService;
use App\Models\Profile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class ProfilePublishApplicationService
{
    public function __construct(
        private readonly ProfileConfigBuilder $configBuilder,
        private readonly PublishService $publishService,
        private readonly RuleCategoryResolver $categoryResolver,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function publishForUser(string $userId, string $profileUid): array
    {
        $profile = Profile::where('user_id', $userId)
            ->where(function ($query) use ($profileUid): void {
                $query->where('profile_id', $profileUid);
                if (ctype_digit($profileUid)) {
                    $query->orWhere('id', (int) $profileUid);
                }
            })
            ->firstOrFail();

        $rules = $profile->rules()->get()->toArray();
        $devices = $profile->devices()->get()->toArray();
        $categoryRules = $this->categoryResolver->loadCategoryRules();

        // 确保 parental_settings 是数组（防止 JSON 字符串未正确解析）
        $parentalSettings = $profile->parental_settings;
        if (is_string($parentalSettings)) {
            $parentalSettings = json_decode($parentalSettings, true) ?? [];
        }
        $parentalSettings = is_array($parentalSettings) ? $parentalSettings : [];

        // 将 parental blocked_items 转换为规则（resolver 只从 rules 列表加载域名规则）
        $blockedItems = $parentalSettings['blocked_items'] ?? [];
        if (! empty($blockedItems) && is_array($blockedItems)) {
            foreach ($blockedItems as $item) {
                $urls = $this->normalizeUrls($item['url'] ?? null);
                if ($urls === []) {
                    // 兼容旧数据：没有 url 时从 name 兜底提取
                    $name = $item['name'] ?? '';
                    if (empty($name)) {
                        continue;
                    }
                    $base = preg_replace('/[^a-zA-Z0-9.-]/', '', explode('/', $name)[0]);
                    if (empty($base)) {
                        continue;
                    }
                    $domain = strtolower($base);
                    if (! str_contains($domain, '.')) {
                        $domain .= '.com';
                    }
                    $urls = [$domain];
                }

                foreach ($urls as $domain) {
                    $domain = DomainNormalizer::normalize($domain);
                    if ($domain === '') {
                        continue;
                    }
                    $rules[] = [
                        'list_type' => 'blocklist',
                        'match_type' => 'suffix',
                        'domain' => $domain,
                        'normalized_domain' => $domain,
                        'action' => 'block',
                        'category' => 'parental',
                        'enabled' => true,
                    ];
                }
            }
        }

        // 将 parental blocked_categories 转换为 category:parental:{key} 规则
        $blockedCategories = $parentalSettings['blocked_categories'] ?? [];
        if (! empty($blockedCategories) && is_array($blockedCategories)) {
            $categoryKeys = [];
            foreach ($blockedCategories as $cat) {
                $key = is_array($cat) ? ($cat['key'] ?? '') : (string) $cat;
                if ($key !== '') {
                    $categoryKeys[] = $key;
                }
            }
            $rules = array_merge($rules, $this->buildCategoryRules($categoryKeys, 'parental'));
        }

        // 根据 parental 开关动态加载分类规则
        if (! empty($parental['enabled'])) {
            $parentalCategoryKeys = [];
            if (! empty($parental['block_adult_content'])) {
                $parentalCategoryKeys[] = 'adult';
            }
            if (! empty($parental['block_gambling']) || ! empty($parental['block_gambling_basic'])) {
                $parentalCategoryKeys[] = 'gambling';
            }
            if ($parentalCategoryKeys !== []) {
                $rules = array_merge($rules, $this->buildCategoryRules($parentalCategoryKeys, 'parental'));
            }
        }

        // 根据 privacy 开关动态加载分类规则
        if (! empty($privacy['enabled'])) {
            $privacyCategoryKeys = [];
            if (! empty($privacy['block_trackers'])) {
                $privacyCategoryKeys[] = 'tracker';
            }
            if (! empty($privacy['block_analytics'])) {
                $privacyCategoryKeys[] = 'analytics';
            }
            if (! empty($privacy['block_telemetry'])) {
                $privacyCategoryKeys[] = 'telemetry';
            }
            // 允许营销链接 = false 时拦截营销类域名
            if (empty($privacy['allow_marketing_links'])) {
                $privacyCategoryKeys[] = 'marketing';
            }
            if ($privacyCategoryKeys !== []) {
                $rules = array_merge($rules, $this->buildCategoryRules($privacyCategoryKeys, 'privacy'));
            }
        }

        // 确保其他 settings 也是数组
        $securitySettings = $profile->security_settings;
        if (is_string($securitySettings)) {
            $securitySettings = json_decode($securitySettings, true) ?? [];
        }
        $privacySettings = $profile->privacy_settings;
        if (is_string($privacySettings)) {
            $privacySettings = json_decode($privacySettings, true) ?? [];
        }

        $security = array_merge([
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
        ], is_array($securitySettings) ? $securitySettings : []);

        $privacy = array_merge([
            'enabled' => true,
            'block_trackers' => true,
            'block_analytics' => true,
            'block_telemetry' => true,
            'anonymize_client_ip' => true,
            'allow_marketing_links' => false,
            'block_disguised_trackers' => true,
            'log_mode' => 'full',
            'blocklists' => [],
            'deep_tracking_devices' => [],
        ], is_array($privacySettings) ? $privacySettings : []);

        $parental = array_merge([
            'enabled' => false,
            'block_adult_content' => false,
            'block_gambling' => false,
            'block_gambling_basic' => false,
            'safe_search' => false,
            'force_safe_search' => false,
            'youtube_restricted_mode' => false,
            'force_youtube_restricted' => false,
            'block_bypass' => false,
            'time_limits' => [],
            'blocked_items' => [],
            'blocked_categories' => [],
        ], is_array($parentalSettings) ? $parentalSettings : []);

        $featureSettings = [
            'security' => [
                'enabled' => (bool) ($security['enabled'] ?? $profile->security_enabled),
                'block_malware' => (bool) ($security['block_malware'] ?? true),
                'block_phishing' => (bool) ($security['block_phishing'] ?? true),
                'block_command_and_control' => (bool) ($security['block_command_and_control'] ?? true),
                'block_cryptojacking' => (bool) ($security['block_cryptojacking'] ?? true),
                'threat_intel' => (bool) ($security['threat_intel'] ?? true),
                'ai_threat_detection' => (bool) ($security['ai_threat_detection'] ?? false),
                'google_safe_browsing' => (bool) ($security['google_safe_browsing'] ?? true),
                'dns_rebind' => (bool) ($security['dns_rebind'] ?? true),
                'idn_homograph' => (bool) ($security['idn_homograph'] ?? true),
                'typo_squatting' => (bool) ($security['typo_squatting'] ?? true),
                'dga_protection' => (bool) ($security['dga_protection'] ?? true),
                'block_new_domains' => (bool) ($security['block_new_domains'] ?? true),
                'block_dynamic_dns' => (bool) ($security['block_dynamic_dns'] ?? false),
                'block_parked_domains' => (bool) ($security['block_parked_domains'] ?? true),
                'block_tld' => (bool) ($security['block_tld'] ?? false),
                'child_abuse' => (bool) ($security['child_abuse'] ?? true),
                'categories' => [
                    'malware' => (bool) ($security['block_malware'] ?? true),
                    'phishing' => (bool) ($security['block_phishing'] ?? true),
                    'command_and_control' => (bool) ($security['block_command_and_control'] ?? true),
                    'cryptojacking' => (bool) ($security['block_cryptojacking'] ?? true),
                ],
            ],
            'privacy' => [
                'enabled' => (bool) ($privacy['enabled'] ?? $profile->privacy_enabled),
                'block_trackers' => (bool) ($privacy['block_trackers'] ?? true),
                'block_analytics' => (bool) ($privacy['block_analytics'] ?? true),
                'block_telemetry' => (bool) ($privacy['block_telemetry'] ?? true),
                'anonymize_client_ip' => (bool) ($privacy['anonymize_client_ip'] ?? true),
                'allow_marketing_links' => (bool) ($privacy['allow_marketing_links'] ?? false),
                'block_disguised_trackers' => (bool) ($privacy['block_disguised_trackers'] ?? true),
                'log_mode' => (string) ($privacy['log_mode'] ?? 'full'),
                'blocklists' => is_array($privacy['blocklists'] ?? null) ? $privacy['blocklists'] : [],
                'deep_tracking_devices' => is_array($privacy['deep_tracking_devices'] ?? null) ? array_values($privacy['deep_tracking_devices']) : [],
            ],
            'parental' => [
                'enabled' => (bool) ($parental['enabled'] ?? $profile->parental_enabled),
                'block_adult_content' => (bool) ($parental['block_adult_content'] ?? false),
                'block_gambling' => (bool) ($parental['block_gambling'] ?? false),
                'block_gambling_basic' => (bool) ($parental['block_gambling_basic'] ?? $parental['block_gambling'] ?? false),
                'safe_search' => (bool) ($parental['safe_search'] ?? $profile->safe_search_enabled),
                'force_safe_search' => (bool) ($parental['force_safe_search'] ?? false),
                'youtube_restricted_mode' => (bool) ($parental['youtube_restricted_mode'] ?? false),
                'force_youtube_restricted' => (bool) ($parental['force_youtube_restricted'] ?? false),
                'block_bypass' => (bool) ($parental['block_bypass'] ?? false),
                'time_limits' => is_array($parental['time_limits'] ?? null) ? $parental['time_limits'] : [],
                'blocked_items' => is_array($parental['blocked_items'] ?? null) ? array_values($parental['blocked_items']) : [],
                'blocked_categories' => is_array($parental['blocked_categories'] ?? null) ? array_values($parental['blocked_categories']) : [],
                'adult' => (bool) ($parental['block_adult_content'] ?? false),
            ],
        ];

        $profilePublishService = new ProfilePublishService($this->configBuilder, $this->publishService);

        return DB::transaction(function () use ($profile, $profilePublishService, $featureSettings, $rules, $categoryRules, $devices, $userId): array {
            $publishResult = $profilePublishService->publish(
                array_merge($profile->toArray(), [
                    'devices' => $devices,
                    'security_settings' => $featureSettings['security'],
                    'privacy_settings' => $featureSettings['privacy'],
                    'parental_settings' => $featureSettings['parental'],
                ]),
                array_merge($rules, $categoryRules),
                $featureSettings,
                $this->loadQuotaData((int) $userId),
            );

            $newVersion = (int) ($profile->version ?? 1) + 1;

            $profile->update([
                'version' => $newVersion,
                'published_at' => now(),
            ]);

            return $publishResult;
        });
    }

    /**
     * 将 url 字段标准化为域名数组（支持字符串或数组，自动从 URL 中提取 host）。
     *
     * @param mixed $urls
     * @return array<int, string>
     */
    private function normalizeUrls(mixed $urls): array
    {
        if ($urls === null || $urls === '' || $urls === []) {
            return [];
        }

        if (is_string($urls)) {
            $urls = [$urls];
        }

        if (! is_array($urls)) {
            return [];
        }

        $result = [];
        foreach ($urls as $url) {
            $url = trim((string) $url);
            if ($url === '') {
                continue;
            }

            // 去掉协议前缀和路径，只保留 host
            if (str_contains($url, '://')) {
                $parsed = parse_url($url);
                if (is_array($parsed) && ! empty($parsed['host'])) {
                    $url = $parsed['host'];
                }
            }

            // 去掉可能的通配符前缀
            $url = ltrim($url, '*.');

            try {
                $normalized = DomainNormalizer::normalize($url);
                if ($normalized !== '') {
                    $result[] = $normalized;
                }
            } catch (\Throwable) {
                // 无效域名跳过
            }
        }

        return $result;
    }

    /**
     * 根据分类 key 列表从 rule_items 读取域名，生成指定顶层桶的规则。
     *
     * @param array<int, string> $categoryKeys
     * @param string $topLevel 'parental' | 'privacy'
     * @return array<int, array<string, mixed>>
     */
    private function buildCategoryRules(array $categoryKeys, string $topLevel): array
    {
        if ($categoryKeys === []) {
            return [];
        }

        try {
            $rows = DB::table('rule_items')
                ->join('rule_sources', 'rule_items.rule_source_id', '=', 'rule_sources.id')
                ->where('rule_sources.enabled', true)
                ->where('rule_items.action', 'block')
                ->whereIn('rule_items.category', $categoryKeys)
                ->select(['rule_items.domain', 'rule_items.category'])
                ->get();
        } catch (\Throwable $e) {
            Log::warning('buildCategoryRules failed', [
                'categories' => $categoryKeys,
                'top_level' => $topLevel,
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        $out = [];
        $seen = [];
        foreach ($rows as $row) {
            $domain = strtolower(trim((string) ($row->domain ?? '')));
            $domain = preg_replace('/^\|\|/', '', $domain);
            $domain = preg_replace('/\^$/', '', $domain);
            $domain = trim($domain);

            if ($domain === '' || strlen($domain) > 255) {
                continue;
            }

            try {
                $normalized = DomainNormalizer::normalize($domain);
            } catch (\InvalidArgumentException) {
                continue;
            }

            if ($normalized === '') {
                continue;
            }

            // 去重，避免同一域名重复生成规则
            $key = $topLevel . '|' . $row->category . '|' . $normalized;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $out[] = [
                'rule_id' => '',
                'list_type' => 'category:' . $topLevel . ':' . $row->category,
                'match_type' => 'suffix',
                'domain' => $normalized,
                'normalized_domain' => $normalized,
                'action' => 'block',
                'category' => $topLevel . ':' . $row->category,
                'enabled' => true,
            ];
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function loadQuotaData(int $userId): array
    {
        $quota = [];

        try {
            $subscription = DB::table('subscriptions')
                ->where('user_id', $userId)
                ->where('status', 'active')
                ->orderByDesc('id')
                ->first(['quota_status', 'plan_id']);

            if ($subscription !== null && ($subscription->quota_status ?? 'normal') !== 'normal') {
                $quota['quota_status'] = $subscription->quota_status;
            }
        } catch (\Throwable $e) {
            Log::warning('loadQuotaData failed, using default quota', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }

        return $quota;
    }
}
