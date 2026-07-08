<?php

declare(strict_types=1);

namespace App\Domain\Profile;

use App\Models\SystemConfig;

final class MemberCatalogService
{
    public const CONFIG_KEY = 'member_feature_catalogs';

    /**
     * 最小骨架默认值。完整 catalog 由后台 admin 维护，本方法仅在测试 seed / 首次
     * 部署时提供 system=true 的占位，避免 b4369bab 删 hardcode 后
     * `MemberFeatureCatalogSeeder` 与 `tests/Feature/ApiTest` 调到不存在的
     * `defaults()` 时崩 500。
     *
     * 关键来源：生产 portal-web `system_configs.member_feature_catalogs` 实际
     * 由 admin 后台维护出来的 system 项；本方法在缺数据时 fallback 到这些项。
     *
     * 2026-07-07: 恢复完整功能条目（15条安全防护功能）
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function defaults(): array
    {
        // 安全功能 key 必须与 dns-resolver internal/resolver/resolver.go 中
        // LoadSecurityConfig 读取的 key 保持一致，否则前端开关无法控制 resolver。
        return [
            'device_models' => [
                ['key' => 'threat_intel', 'name' => '威胁情报', 'desc' => '使用威胁情报源来阻断已知恶意域名。', 'field_type' => 'switch', 'enabled' => true, 'system' => true],
                ['key' => 'ai_threat_detection', 'name' => 'AI 威胁检测', 'desc' => '使用人工智能检测并阻断新兴威胁。', 'field_type' => 'switch', 'enabled' => true, 'system' => true],
                ['key' => 'google_safe_browsing', 'name' => 'Google 安全浏览', 'desc' => '使用 Google 安全浏览来拦截流氓软件和诈骗网站，该技术每天检查数十亿个链接并识别不安全的网站。', 'field_type' => 'switch', 'enabled' => true, 'system' => true],
                ['key' => 'dns_rebind', 'name' => 'DNS 重新绑定攻击保护', 'desc' => '拦截包含本地 IP 地址的 DNS 查询结果，防止黑客通过互联网操纵本地设备。', 'field_type' => 'switch', 'enabled' => true, 'system' => true],
                ['key' => 'idn_homograph', 'name' => 'IDN 同构攻击保护', 'desc' => '阻断视觉上与合法域名相似的国际化域名。', 'field_type' => 'switch', 'enabled' => true, 'system' => true],
                ['key' => 'typo_squatting', 'name' => '误植域名保护', 'desc' => '拦截热门网站的拼写错误域名，这些域名常被用于钓鱼攻击。', 'field_type' => 'switch', 'enabled' => true, 'system' => true],
                ['key' => 'dga_protection', 'name' => '域名生成算法（DGA）保护', 'desc' => '域名生成算法（DGA）生成的域名通常被用于各种流氓软件或病毒。', 'field_type' => 'switch', 'enabled' => true, 'system' => true],
                ['key' => 'block_cryptojacking', 'name' => '挖矿病毒保护', 'desc' => '防止未经授权使用你的设备来开采加密货币。', 'field_type' => 'switch', 'enabled' => true, 'system' => true],
                ['key' => 'block_dynamic_dns', 'name' => '拦截动态 DNS', 'desc' => '拦截动态 DNS 服务，这些服务常被攻击者用于维持对受 compromise 系统的访问。', 'field_type' => 'switch', 'enabled' => true, 'system' => true],
                ['key' => 'block_parked_domains', 'name' => '拦截停放域名', 'desc' => '拦截停放域名，这些域名不托管合法内容。', 'field_type' => 'switch', 'enabled' => true, 'system' => true],
                ['key' => 'block_tld', 'name' => '拦截特定顶级域名', 'desc' => '拦截通常与恶意活动相关联的整个顶级域名。', 'field_type' => 'switch', 'enabled' => true, 'system' => true],
                ['key' => 'block_new_domains', 'name' => '拦截新注册域名', 'desc' => '拦截最近 30 天内注册的域名，这些域名常被用于恶意目的。', 'field_type' => 'switch', 'enabled' => true, 'system' => true],
                ['key' => 'block_malware', 'name' => '拦截恶意软件', 'desc' => '阻断已知恶意软件分发域名', 'field_type' => 'switch', 'enabled' => true, 'system' => true],
                ['key' => 'block_phishing', 'name' => '拦截钓鱼攻击', 'desc' => '阻断钓鱼和凭证窃取网站', 'field_type' => 'switch', 'enabled' => true, 'system' => true],
                ['key' => 'child_abuse', 'name' => '拦截儿童色情内容', 'desc' => '拦截儿童色情内容。', 'field_type' => 'switch', 'enabled' => true, 'system' => true],
            ],
            'privacy_blocklists' => [
                ['key' => 'deep_tracking_protection', 'name' => '深度跟踪保护', 'desc' => '屏蔽深度跟踪请求', 'field_type' => 'multi', 'days_ago' => 7, 'enabled' => true, 'system' => true, 'devices' => []],
                ['key' => 'disguised_trackers', 'name' => '伪装追踪器', 'desc' => '拦截伪装为一级域名的追踪器', 'field_type' => 'switch', 'enabled' => true, 'system' => true, 'days_ago' => 3],
                ['key' => 'allow_marketing_links', 'name' => '允许营销链接', 'desc' => '关闭以启用更严格拦截', 'field_type' => 'switch', 'enabled' => true, 'system' => true, 'days_ago' => 3],
            ],
            'parental_presets' => [
                ['key' => 'safe_search', 'name' => '安全搜索', 'desc' => '启用安全搜索', 'field_type' => 'switch', 'enabled' => true, 'system' => true, 'icon' => '', 'url' => ''],
                ['key' => 'youtube_restricted', 'name' => 'YouTube 受限模式', 'desc' => '启用 YouTube 受限模式', 'field_type' => 'switch', 'enabled' => true, 'system' => true, 'icon' => '', 'url' => ''],
                ['key' => 'block_bypass', 'name' => '拦截绕过', 'desc' => '拦截绕过 DNS 的方法', 'field_type' => 'switch', 'enabled' => true, 'system' => true, 'icon' => '', 'url' => ''],
                ['key' => 'app_presets', 'name' => '应用预设', 'desc' => '预设应用/网站/游戏拦截列表', 'field_type' => 'multi', 'enabled' => true, 'system' => true, 'icon' => '', 'url' => ''],
            ],
        ];
    }

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
