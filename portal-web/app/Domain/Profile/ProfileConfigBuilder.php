<?php

namespace App\Domain\Profile;

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
        return [
            'profile_id' => (string) ($profile['profile_uid'] ?? $profile['id']),
            'user_id' => (string) ($profile['user_id']),
            'team_id' => $profile['team_id'] ?? null,
            'version' => (int) ($profile['version'] ?? $profile['draft_version'] ?? 0) + 1,
            'default_action' => $profile['default_action'] ?? 'allow',
            'block_response' => $profile['block_response'] ?? 'nxdomain',
            'security' => $featureSettings['security'] ?? ['enabled' => true],
            'adblock' => [
                'enabled' => (bool) ($profile['adblock_enabled'] ?? false),
            ],
            'privacy' => $featureSettings['privacy'] ?? ['enabled' => true, 'log_mode' => 'full'],
            'parental' => $featureSettings['parental'] ?? ['enabled' => false],
            'devices' => array_map([$this, 'mapDevice'], $profile['devices'] ?? []),
            'rules' => array_map([$this, 'mapRule'], $rules),
            'quota' => $quota,
        ];
    }

    /**
     * @param array<string, mixed> $rule
     * @return array<string, mixed>
     */
    private function mapRule(array $rule): array
    {
        return [
            'rule_id' => $rule['id'],
            'list_type' => $rule['list_type'],
            'match_type' => $rule['match_type'],
            'domain' => $rule['domain'],
            'normalized_domain' => DomainNormalizer::normalize($rule['normalized_domain'] ?? $rule['domain']),
            'action' => $rule['action'],
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
