<?php

declare(strict_types=1);

namespace App\Application\Member;

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

        // 将 parental blocked_items 转换为规则（因为 resolver 只从 rules 列表加载域名规则）
        $blockedItems = $parentalSettings['blocked_items'] ?? [];
        if (! empty($blockedItems) && is_array($blockedItems)) {
            foreach ($blockedItems as $item) {
                $name = $item['name'] ?? '';
                if (empty($name)) {
                    continue;
                }

                // 如果名称不是域名格式，尝试转换为域名（小写+无空格）
                $domain = $name;
                if (! str_contains($domain, '.')) {
                    $domain = strtolower(str_replace(' ', '', $domain)) . '.com';
                }

                if (! str_contains($domain, '.')) {
                    continue;
                }

                $category = $item['category'] ?? 'default';
                $rules[] = [
                    'list_type' => 'category:parental:'.$category,
                    'match_type' => 'exact',
                    'domain' => $domain,
                    'normalized_domain' => $domain,
                    'action' => 'block',
                    'category' => 'parental',
                    'enabled' => true,
                ];
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
