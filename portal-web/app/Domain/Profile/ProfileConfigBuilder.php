<?php

namespace App\Domain\Profile;

use App\Models\Brand;
use App\Models\SystemConfig;
use Illuminate\Support\Facades\DB;

final class ProfileConfigBuilder
{
    /**
     * @param array<string, mixed> $profile
     * @param array<int, array<string, mixed>> $rules
     * @param array<string, mixed> $featureSettings
     * @param array<string, mixed> $quota
     * @return array<string, mixed>
     */
    public function build(array $profile, array $rules, array $featureSettings, array $quota): array
    {
        // resolver expects quota as JSON object (map), not array.
        // Convert empty array to stdClass so json_encode emits {} instead of [].
        $quotaObject = (object) $quota;
        return [
            'profile_id' => (string) ($profile['profile_id'] ?? $profile['id']),
            'user_id' => (string) ($profile['user_id']),
            'team_id' => $profile['team_id'] ?? null,
            'version' => (int) ($profile['version'] ?? $profile['draft_version'] ?? 0) + 1,
            'default_action' => $profile['default_action'] ?? 'allow',
            'block_response' => $profile['block_response'] ?? 'nxdomain',
            'security' => array_merge(
                $this->protectionPolicies(),
                $featureSettings['security'] ?? ['enabled' => true],
                [
                    'brand_domains' => Brand::where('enabled', true)
                        ->whereNotNull('domain')
                        ->pluck('domain')
                        ->toArray(),
                ],
            ),
            'adblock' => [
                'enabled' => (bool) ($profile['adblock_enabled'] ?? false),
            ],
            'privacy' => $featureSettings['privacy'] ?? ['enabled' => true, 'log_mode' => 'full'],
            'parental' => $featureSettings['parental'] ?? ['enabled' => false],
            'devices' => array_map([$this, 'mapDevice'], $profile['devices'] ?? []),
            'rules' => array_map([$this, 'mapRule'], $rules),
            'quota' => $quotaObject,
            'security_data' => (object) $this->loadSecurityData(),
        ];
    }

    /**
     * 从 system_configs 读取全局防护策略（protection），
     * 转换为 resolver profileSecurity 结构的扁平 key-value 格式。
     *
     * 优先级低于 Profile 自身的 security_settings（通过 $featureSettings 覆盖）。
     */
    private function protectionPolicies(): array
    {
        $protection = SystemConfig::where('config_key', 'protection')->value('config_value') ?? [];

        $resolver = [
            'idn_homograph' => (bool) ($protection['idn']['enabled'] ?? true),
            'typo_squatting' => (bool) ($protection['typo']['enabled'] ?? true),
            'dga_protection' => (bool) ($protection['dga']['enabled'] ?? true),
            'dns_rebind' => (bool) ($protection['dns_rebind']['enabled'] ?? true),
            'block_dynamic_dns' => (bool) ($protection['categories']['dynamic_dns']['enabled'] ?? true),
        ];

        if (isset($protection['dns_rebind']['whitelist'])) {
            $resolver['dns_rebind_whitelist'] = $protection['dns_rebind']['whitelist'];
        }
        if (isset($protection['dga']['entropy_threshold'])) {
            $resolver['dga_entropy_threshold'] = (float) $protection['dga']['entropy_threshold'];
        }
        if (isset($protection['dga']['digit_ratio'])) {
            $resolver['dga_digit_ratio'] = (float) $protection['dga']['digit_ratio'];
        }
        if (isset($protection['typo']['threshold'])) {
            $resolver['typo_threshold'] = (int) $protection['typo']['threshold'];
        }

        return $resolver;
    }

    /**
     * 从 security_data_items 表加载按 group_code 分组的域名列表，
     * 作为 security_data 字段加入发布包，由 resolver 端读取并注入 matching engine。
     *
     * 只加载 enabled = true 的条目。
     */
    private function loadSecurityData(): array
    {
        $rows = DB::table('security_data_items')
            ->where('enabled', true)
            ->select('group_code', 'value')
            ->orderBy('value')
            ->get();

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(string) $row->group_code][] = (string) $row->value;
        }

        return $grouped;
    }

    /**
     * @param array<string, mixed> $rule
     * @return array<string, mixed>
     */
    private function mapRule(array $rule): array
    {
        return [
            'rule_id' => (string) ($rule['id'] ?? $rule['rule_id'] ?? ''),
            'list_type' => $rule['list_type'],
            'match_type' => $rule['match_type'] ?? 'exact',
            'domain' => $rule['domain'],
            'normalized_domain' => DomainNormalizer::normalize($rule['normalized_domain'] ?? $rule['domain']),
            'action' => $rule['action'] ?? 'block',
            'category' => $rule['category'] ?? null,
            'enabled' => (bool) ($rule['enabled'] ?? true),
        ];
    }

    /**
     * @param array<string, mixed> $device
     * @return array<string, mixed>
     */
    private function mapDevice(array $device): array
    {
        return [
            'device_id' => $device['device_uid'] ?? $device['id'],
            'name' => $device['name'] ?? 'device',
            'device_type' => $device['protocol'] ?? null,
            'source_ip' => $device['source_ip'] ?? null,
            'device_key_hash' => null,
        ];
    }
}
