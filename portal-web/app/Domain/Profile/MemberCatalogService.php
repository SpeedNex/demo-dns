<?php

declare(strict_types=1);

namespace App\Domain\Profile;

use App\Models\SystemConfig;

final class MemberCatalogService
{
    private const CONFIG_KEY = 'member_feature_catalogs';

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function get(): array
    {
        $stored = SystemConfig::query()->find(self::CONFIG_KEY)?->value;
        $defaults = $this->defaults();

        if (! is_array($stored)) {
            return $defaults;
        }

        return [
            'device_models' => $this->normalizeItems($stored['device_models'] ?? $defaults['device_models'], ['id', 'name', 'desc', 'icon', 'color']),
            'privacy_blocklists' => $this->normalizeItems($stored['privacy_blocklists'] ?? $defaults['privacy_blocklists'], ['key', 'name', 'desc', 'entries', 'days_ago']),
            'parental_presets' => $this->normalizeItems($stored['parental_presets'] ?? $defaults['parental_presets'], ['name', 'icon', 'category']),
            'parental_categories' => $this->normalizeItems($stored['parental_categories'] ?? $defaults['parental_categories'], ['key', 'name', 'desc']),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function update(array $payload, int|string|null $actorId = null): array
    {
        $merged = [
            'device_models' => $this->normalizeItems($payload['device_models'] ?? [], ['id', 'name', 'desc', 'icon', 'color']),
            'privacy_blocklists' => $this->normalizeItems($payload['privacy_blocklists'] ?? [], ['key', 'name', 'desc', 'entries', 'days_ago']),
            'parental_presets' => $this->normalizeItems($payload['parental_presets'] ?? [], ['name', 'icon', 'category']),
            'parental_categories' => $this->normalizeItems($payload['parental_categories'] ?? [], ['key', 'name', 'desc']),
        ];

        SystemConfig::query()->updateOrCreate(
            ['key' => self::CONFIG_KEY],
            ['value' => $merged, 'updated_by' => $actorId ?? 'system'],
        );

        return $merged;
    }

    /**
     * @param array<int, mixed> $items
     * @param array<int, string> $fields
     * @return array<int, array<string, mixed>>
     */
    private function normalizeItems(array $items, array $fields): array
    {
        return collect($items)
            ->filter(fn ($item) => is_array($item))
            ->map(function (array $item) use ($fields): array {
                $normalized = [];
                foreach ($fields as $field) {
                    $normalized[$field] = $item[$field] ?? null;
                }

                return $normalized;
            })
            ->filter(function (array $item): bool {
                foreach ($item as $value) {
                    if (is_string($value) && trim($value) !== '') {
                        return true;
                    }
                    if (is_numeric($value)) {
                        return true;
                    }
                }

                return false;
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function defaults(): array
    {
        return [
            'device_models' => [
                ['id' => 'windows', 'name' => 'Windows', 'desc' => 'Desktop and laptop devices', 'icon' => '/static/media/windows.svg', 'color' => '#0078d4'],
                ['id' => 'apple', 'name' => 'Apple', 'desc' => 'iOS, macOS and tvOS', 'icon' => '/static/media/apple.svg', 'color' => '#555555'],
                ['id' => 'android', 'name' => 'Android', 'desc' => 'Phones, tablets and Android TV', 'icon' => '/static/media/android.svg', 'color' => '#3ddc84'],
                ['id' => 'samsung', 'name' => 'Samsung', 'desc' => 'Phones, tablets and smart TVs', 'icon' => '/static/media/samsung.svg', 'color' => '#1428a0'],
                ['id' => 'xiaomi', 'name' => 'Xiaomi', 'desc' => 'Phones, tablets, smart TVs and routers', 'icon' => '/static/media/xiaomi.svg', 'color' => '#ff6900'],
                ['id' => 'huawei', 'name' => 'Huawei', 'desc' => 'Phones and tablets', 'icon' => '/static/media/huawei.svg', 'color' => '#cf0a2c'],
                ['id' => 'alexa', 'name' => 'Amazon Alexa', 'desc' => 'Alexa-enabled devices', 'icon' => '/static/media/alexa.svg', 'color' => '#00cae4'],
                ['id' => 'roku', 'name' => 'Roku', 'desc' => 'All Roku streaming devices', 'icon' => '/static/media/roku.svg', 'color' => '#6f1d8f'],
                ['id' => 'sonos', 'name' => 'Sonos', 'desc' => 'Smart speakers', 'icon' => '/static/media/sonos.svg', 'color' => '#da3d2e'],
            ],
            'privacy_blocklists' => [
                ['key' => 'ads_tracking', 'name' => 'Ads & Tracking', 'desc' => 'Ad and tracker protection', 'entries' => 86222, 'days_ago' => 5],
                ['key' => 'third_party_tracking', 'name' => 'Third-party Tracking', 'desc' => 'Cross-site tracking protection', 'entries' => 45678, 'days_ago' => 3],
                ['key' => 'phishing', 'name' => 'Phishing', 'desc' => 'Known phishing domains', 'entries' => 32100, 'days_ago' => 2],
                ['key' => 'malware', 'name' => 'Malware', 'desc' => 'Known malware domains', 'entries' => 28900, 'days_ago' => 2],
            ],
            'parental_presets' => [
                ['name' => 'TikTok', 'icon' => 'https://favicons.nextdns.io/hex:7777772e74696b746f6b2e636f6d@1x.png', 'category' => 'website'],
                ['name' => 'Instagram', 'icon' => 'https://favicons.nextdns.io/hex:7777772e696e7374616772616d2e636f6d@1x.png', 'category' => 'app'],
                ['name' => 'YouTube', 'icon' => 'https://favicons.nextdns.io/hex:7777772e796f75747562652e636f6d@1x.png', 'category' => 'website'],
                ['name' => 'Discord', 'icon' => 'https://favicons.nextdns.io/hex:646973636f72646170702e636f6d@1x.png', 'category' => 'app'],
                ['name' => 'Roblox', 'icon' => 'https://favicons.nextdns.io/hex:7777772e726f626c6f782e636f6d@1x.png', 'category' => 'game'],
            ],
            'parental_categories' => [
                ['key' => 'adult', 'name' => 'Adult Content', 'desc' => 'Adult and explicit content'],
                ['key' => 'gambling', 'name' => 'Gambling', 'desc' => 'Betting and gambling services'],
                ['key' => 'social', 'name' => 'Social Media', 'desc' => 'Social networks and communities'],
                ['key' => 'gaming', 'name' => 'Gaming', 'desc' => 'Gaming platforms and launchers'],
                ['key' => 'streaming', 'name' => 'Streaming', 'desc' => 'Video and live streaming'],
            ],
        ];
    }
}
