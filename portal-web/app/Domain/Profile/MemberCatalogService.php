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
        $stored = SystemConfig::query()->where('config_key', self::CONFIG_KEY)->first()?->config_value;
        $defaults = $this->defaults();

        if (! is_array($stored)) {
            return $defaults;
        }

        return [
            'device_models' => $this->normalizeItems($stored['device_models'] ?? $defaults['device_models'], ['id', 'name', 'desc', 'icon', 'color', 'enabled']),
            'privacy_blocklists' => $this->normalizeItems($stored['privacy_blocklists'] ?? $defaults['privacy_blocklists'], ['key', 'name', 'desc', 'entries', 'days_ago', 'enabled']),
            'parental_presets' => $this->normalizeItems($stored['parental_presets'] ?? $defaults['parental_presets'], ['name', 'icon', 'category', 'enabled']),
            'parental_categories' => $this->normalizeItems($stored['parental_categories'] ?? $defaults['parental_categories'], ['key', 'name', 'desc', 'enabled']),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function update(array $payload, int|string|null $actorId = null): array
    {
        $merged = [
            'device_models' => $this->normalizeItems($payload['device_models'] ?? [], ['id', 'name', 'desc', 'icon', 'color', 'enabled']),
            'privacy_blocklists' => $this->normalizeItems($payload['privacy_blocklists'] ?? [], ['key', 'name', 'desc', 'entries', 'days_ago', 'enabled']),
            'parental_presets' => $this->normalizeItems($payload['parental_presets'] ?? [], ['name', 'icon', 'category', 'enabled']),
            'parental_categories' => $this->normalizeItems($payload['parental_categories'] ?? [], ['key', 'name', 'desc', 'enabled']),
        ];

        SystemConfig::query()->updateOrCreate(
            ['config_key' => self::CONFIG_KEY],
            ['config_value' => $merged, 'updated_by' => $actorId ?? 'system'],
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
                    $value = $item[$field] ?? null;
                    if ($field === 'enabled') {
                        $normalized[$field] = (bool) $value;
                    } else {
                        $normalized[$field] = $value;
                    }
                }

                return $normalized;
            })
            ->filter(function (array $item): bool {
                foreach ($item as $field => $value) {
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
            'device_models' => [],
            'privacy_blocklists' => [],
            'parental_presets' => [],
            'parental_categories' => [],
        ];
    }
}
