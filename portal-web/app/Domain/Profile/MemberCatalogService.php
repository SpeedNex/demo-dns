<?php

declare(strict_types=1);

namespace App\Domain\Profile;

use App\Models\SystemConfig;

final class MemberCatalogService
{
    public const CONFIG_KEY = 'member_feature_catalogs';

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function get(): array
    {
        $stored = SystemConfig::query()->where('config_key', self::CONFIG_KEY)->first()?->config_value;

        if (! is_array($stored)) {
            return [
                'device_models' => [],
                'privacy_blocklists' => [],
                'parental_presets' => [],
            ];
        }

        $result = [
            'device_models' => $this->normalizeItems($stored['device_models'] ?? [], ['key', 'name', 'desc', 'field_type', 'enabled', 'system']),
            'privacy_blocklists' => $this->normalizeItems($stored['privacy_blocklists'] ?? [], ['key', 'name', 'desc', 'field_type', 'days_ago', 'enabled', 'system', 'devices']),
            'parental_presets' => $this->normalizeParentalPresets($stored['parental_presets'] ?? []),
        ];

        // 确保每组所有项都有 field_type 默认值
        $defaultFieldTypes = [
            'device_models' => 'switch',
            'privacy_blocklists' => 'switch',
        ];
        foreach ($defaultFieldTypes as $group => $defaultType) {
            foreach ($result[$group] as &$item) {
                if (empty($item['field_type'])) {
                    $item['field_type'] = $defaultType;
                }
            }
            unset($item);
        }

        // 特殊处理：deep_tracking_protection 必须是 multi 类型（多选设备）
        foreach ($result['privacy_blocklists'] as &$item) {
            if (($item['key'] ?? '') === 'deep_tracking_protection') {
                $item['field_type'] = 'multi';
            }
        }
        unset($item);

        return $result;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function update(array $payload, int|string|null $actorId = null): array
    {
        $merged = [
            'device_models' => $this->normalizeItems($payload['device_models'] ?? [], ['key', 'name', 'desc', 'field_type', 'enabled', 'system']),
            'privacy_blocklists' => $this->normalizeItems($payload['privacy_blocklists'] ?? [], ['key', 'name', 'desc', 'field_type', 'days_ago', 'enabled', 'system', 'devices']),
            'parental_presets' => $this->normalizeParentalPresets($payload['parental_presets'] ?? []),
        ];

        SystemConfig::query()->updateOrCreate(
            ['config_key' => self::CONFIG_KEY],
            ['config_value' => $merged, 'updated_by' => $actorId ?? 'system'],
        );

        return $merged;
    }

    /**
     * 将 url 字段安全转为字符串（兼容旧版数组格式）
     */
    private function urlToString(mixed $url): string
    {
        if (is_array($url)) {
            $first = $url[array_key_first($url)] ?? '';
            return is_string($first) ? $first : '';
        }
        return (string) ($url ?? '');
    }

    /**
     * 标准化家长监护预设
     * 结构：
     * - safe_search: 开关
     * - youtube_restricted: 开关
     * - block_bypass: 开关
     * - app_presets: 多选（包含所有网站/应用/游戏选项）
     *
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function normalizeParentalPresets(array $items): array
    {
        return collect($items)
            ->filter(fn ($item) => is_array($item))
            ->map(function (array $item): array {
                $normalized = [
                    'key' => (string) ($item['key'] ?? ''),
                    'name' => (string) ($item['name'] ?? ''),
                    'desc' => (string) ($item['desc'] ?? ''),
                    'icon' => (string) ($item['icon'] ?? ''),
                    'field_type' => (string) ($item['field_type'] ?? 'switch'),
                    'enabled' => (bool) ($item['enabled'] ?? true),
                    'system' => (bool) ($item['system'] ?? false),
                    'url' => $this->urlToString($item['url'] ?? ''),
                ];

                // 多选类型：标准化 options 数组
                if ($normalized['field_type'] === 'multi' && isset($item['options'])) {
                    $normalized['options'] = $this->normalizeOptions($item['options']);
                }

                return $normalized;
            })
            ->filter(function (array $item): bool {
                if ($item['system']) {
                    return true;
                }
                return ! empty($item['name']);
            })
            ->values()
            ->all();
    }

    /**
     * 标准化多选选项
     *
     * @param array<int, array<string, mixed>> $options
     * @return array<int, array<string, mixed>>
     */
    private function normalizeOptions(array $options): array
    {
        return collect($options)
            ->filter(fn ($opt) => is_array($opt) && ! empty($opt['name']))
            ->map(function (array $opt): array {
                return [
                    'name' => (string) ($opt['name'] ?? ''),
                    'icon' => (string) ($opt['icon'] ?? '🌐'),
                    'category' => (string) ($opt['category'] ?? 'website'),
                    'desc' => (string) ($opt['desc'] ?? ''),
                    'url' => $this->urlToString($opt['value'] ?? $opt['url'] ?? ''),
                    'enabled' => (bool) ($opt['enabled'] ?? true),
                ];
            })
            ->values()
            ->all();
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
                    $value = $item[$field] ?? null;
                    if (in_array($field, ['enabled', 'system'], true)) {
                        $normalized[$field] = (bool) ($value ?? false);
                    } elseif ($field === 'devices' && is_array($value)) {
                        $normalized[$field] = $this->normalizeDevices($value);
                    } else {
                        $normalized[$field] = $value;
                    }
                }

                return $normalized;
            })
            ->filter(function (array $item): bool {
                foreach ($item as $field => $value) {
                    if ($field === 'system') {
                        continue;
                    }
                    if ($field === 'devices' && ! empty($value)) {
                        return true;
                    }
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
     * @param array<int, array<string, mixed>> $devices
     * @return array<int, array<string, mixed>>
     */
    private function normalizeDevices(array $devices): array
    {
        return collect($devices)
            ->filter(fn ($d) => is_array($d) && ! empty($d['key']))
            ->map(function (array $d): array {
                return [
                    'key' => (string) ($d['key'] ?? ''),
                    'name' => (string) ($d['name'] ?? $d['key'] ?? ''),
                    'icon' => (string) ($d['icon'] ?? '📱'),
                    'enabled' => (bool) ($d['enabled'] ?? true),
                ];
            })
            ->values()
            ->all();
    }
}
