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
            'device_models' => $this->mergeSystemDefaults(
                $this->normalizeItems($stored['device_models'] ?? [], ['key', 'name', 'desc', 'field_type', 'enabled', 'system']),
                $defaults['device_models'] ?? []
            ),
            'privacy_blocklists' => $this->mergeSystemDefaults(
                $this->normalizeItems($stored['privacy_blocklists'] ?? [], ['key', 'name', 'desc', 'field_type', 'entries', 'days_ago', 'enabled', 'system', 'devices']),
                $defaults['privacy_blocklists'] ?? []
            ),
            'parental_presets' => $this->mergeSystemDefaults(
                $this->normalizeItems($stored['parental_presets'] ?? [], ['name', 'key', 'icon', 'category', 'field_type', 'desc', 'enabled', 'url', 'system']),
                $defaults['parental_presets'] ?? []
            ),
            'parental_categories' => $this->normalizeItems($stored['parental_categories'] ?? [], ['key', 'name', 'desc', 'field_type', 'enabled']),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function update(array $payload, int|string|null $actorId = null): array
    {
        $merged = [
            'device_models' => $this->normalizeItems($payload['device_models'] ?? [], ['key', 'name', 'desc', 'field_type', 'enabled', 'system']),
            'privacy_blocklists' => $this->normalizeItems($payload['privacy_blocklists'] ?? [], ['key', 'name', 'desc', 'field_type', 'entries', 'days_ago', 'enabled', 'system', 'devices']),
            'parental_presets' => $this->normalizeItems($payload['parental_presets'] ?? [], ['name', 'key', 'icon', 'category', 'field_type', 'desc', 'enabled', 'url', 'system']),
            'parental_categories' => $this->normalizeItems($payload['parental_categories'] ?? [], ['key', 'name', 'desc', 'field_type', 'enabled']),
        ];

        SystemConfig::query()->updateOrCreate(
            ['config_key' => self::CONFIG_KEY],
            ['config_value' => $merged, 'updated_by' => $actorId ?? 'system'],
        );

        return $merged;
    }

    /**
     * 合并系统内置项：已存储的数据优先，缺失的系统默认项自动补入
     *
     * @param array<int, array<string, mixed>> $stored
     * @param array<int, array<string, mixed>> $defaults
     * @return array<int, array<string, mixed>>
     */
    private function mergeSystemDefaults(array $stored, array $defaults): array
    {
        $storedKeys = array_column($stored, 'key');

        // 已存储项：补充缺失的 devices 字段
        foreach ($stored as &$item) {
            if (! empty($item['system']) && empty($item['devices'])) {
                $defaultItem = collect($defaults)->firstWhere('key', $item['key']);
                if (! empty($defaultItem['devices'])) {
                    $item['devices'] = $defaultItem['devices'];
                }
            }
        }
        unset($item);

        // 缺失的系统默认项：自动补入
        foreach ($defaults as $item) {
            if (empty($item['key']) || in_array($item['key'], $storedKeys, true)) {
                continue;
            }
            $stored[] = $item;
        }

        return $stored;
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
                        $normalized[$field] = (bool) $value;
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
            ->map(fn ($d) => [
                'key' => (string) $d['key'],
                'name' => (string) ($d['name'] ?? $d['key']),
                'icon' => (string) ($d['icon'] ?? '📱'),
                'enabled' => (bool) ($d['enabled'] ?? true),
            ])
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
                ['key' => 'threat_intel', 'name' => '威胁情报', 'desc' => '使用威胁情报源来阻断已知恶意域名。', 'field_type' => 'switch', 'enabled' => true, 'system' => true],
                ['key' => 'ai_threat_detection', 'name' => 'AI 威胁检测', 'desc' => '使用人工智能检测并阻断新兴威胁。', 'field_type' => 'switch', 'enabled' => true, 'system' => true],
                ['key' => 'google_safe_browsing', 'name' => 'Google 安全浏览', 'desc' => '使用 Google 安全浏览来拦截流氓软件和诈骗网站，该技术每天检查数十亿个链接并识别不安全的网站。与某些浏览器中内置的版本不同，这不会将你的IP地址与恶意网站相关联，并且不允许绕过该拦截。', 'field_type' => 'switch', 'enabled' => true, 'system' => true],
                ['key' => 'anti_mining', 'name' => '挖矿病毒保护', 'desc' => '防止未经授权使用你的设备来开采加密货币。', 'field_type' => 'switch', 'enabled' => true, 'system' => true],
                ['key' => 'dns_rebinding', 'name' => 'DNS 重新绑定攻击保护', 'desc' => '拦截包含本地 IP 地址的 DNS 查询结果，防止黑客通过互联网操纵本地设备。', 'field_type' => 'switch', 'enabled' => true, 'system' => true],
                ['key' => 'idn_homograph', 'name' => 'IDN 同构攻击保护', 'desc' => '阻断视觉上与合法域名相似的国际化域名。', 'field_type' => 'switch', 'enabled' => true, 'system' => true],
                ['key' => 'typosquatting', 'name' => '误植域名保护', 'desc' => '拦截热门网站的拼写错误域名，这些域名常被用于钓鱼攻击。', 'field_type' => 'switch', 'enabled' => true, 'system' => true],
                ['key' => 'dga', 'name' => '域名生成算法（DGA）保护', 'desc' => '域名生成算法（DGA）生成的域名通常被用于各种流氓软件或病毒，这些域名可以被用作其命令和控制服务器的中心。', 'field_type' => 'switch', 'enabled' => true, 'system' => true],
                ['key' => 'block_newly_registered', 'name' => '拦截新注册域名', 'desc' => '拦截最近 30 天内注册的域名，这些域名常被用于恶意目的。', 'field_type' => 'switch', 'enabled' => true, 'system' => true],
                ['key' => 'block_dynamic_dns', 'name' => '拦截动态 DNS', 'desc' => '拦截动态 DNS 服务，这些服务常被攻击者用于维持对受 compromise 系统的访问。', 'field_type' => 'switch', 'enabled' => true, 'system' => true],
                ['key' => 'block_parked_domains', 'name' => '拦截停放域名', 'desc' => '拦截停放域名，这些域名不托管合法内容。', 'field_type' => 'switch', 'enabled' => true, 'system' => true],
                ['key' => 'block_specific_tld', 'name' => '拦截特定顶级域名', 'desc' => '拦截通常与恶意活动相关联的整个顶级域名。', 'field_type' => 'switch', 'enabled' => true, 'system' => true],
                ['key' => 'block_csam', 'name' => '拦截儿童色情内容', 'desc' => '拦截包含儿童性虐待材料的网站。', 'field_type' => 'switch', 'enabled' => true, 'system' => true],
            ],
            'privacy_blocklists' => [
                ['key' => 'deep_tracking_protection', 'name' => '深度跟踪保护', 'desc' => '拦截通常在操作系统级运行的深度跟踪软件，这些跟踪软件知道你在设备上的所有行为。这可能包括你访问的所有网站、你输入的所有内容或你的位置。', 'field_type' => 'multi', 'entries' => 0, 'days_ago' => 0, 'enabled' => true, 'system' => true, 'devices' => [
                    ['key' => 'iphone', 'name' => 'iPhone', 'icon' => '📱', 'enabled' => true],
                    ['key' => 'android', 'name' => 'Android', 'icon' => '🤖', 'enabled' => true],
                    ['key' => 'windows', 'name' => 'Windows', 'icon' => '🖥️', 'enabled' => true],
                    ['key' => 'macos', 'name' => 'macOS', 'icon' => '💻', 'enabled' => true],
                    ['key' => 'router', 'name' => '路由器', 'icon' => '📡', 'enabled' => false],
                ]],
                ['key' => 'disguised_trackers', 'name' => '拦截伪装过的第三方跟踪器', 'desc' => '拦截伪装成第一方资源的第三方跟踪器，这些跟踪器试图绕过常规跟踪保护。', 'field_type' => 'switch', 'entries' => 0, 'days_ago' => 0, 'enabled' => true, 'system' => true],
                ['key' => 'allow_marketing_links', 'name' => '允许营销和跟踪链接', 'desc' => '允许部分已知包含跟踪参数的营销链接正常访问，同时保留对恶意域名的拦截。', 'field_type' => 'switch', 'entries' => 0, 'days_ago' => 0, 'enabled' => false, 'system' => true],
            ],
            'parental_presets' => [
                ['key' => 'safe_search', 'name' => '安全搜索', 'icon' => '🔍', 'category' => 'website', 'desc' => '在主流搜索引擎上过滤掉含有色情内容的搜索结果，包括图像和视频。如果有搜索引擎不支持此功能，则整个搜索引擎都将被拦截。', 'field_type' => 'switch', 'enabled' => true, 'system' => true],
                ['key' => 'youtube_restricted', 'name' => 'YouTube 受限模式', 'icon' => '📺', 'category' => 'website', 'desc' => '过滤掉 YouTube 上的成人视频，并阻止嵌入的成人视频在其他网站上观看。这也将隐藏所有评论。', 'field_type' => 'switch', 'enabled' => true, 'system' => true],
                ['key' => 'block_bypass', 'name' => '阻止绕过', 'icon' => '🛡️', 'category' => 'website', 'desc' => '阻止用户通过代理或 VPN 绕过家长监护设置。', 'field_type' => 'switch', 'enabled' => true, 'system' => true],
            ],
            'parental_categories' => [],
        ];
    }
}
