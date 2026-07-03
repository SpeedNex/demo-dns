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

        $defaults = $this->defaults();

        if (! is_array($stored)) {
            return $defaults;
        }

        // 自动合并默认 devices（生产数据库旧数据可能缺少 devices 字段）
        $stored = $this->mergeDefaultDevices($stored, $defaults);

        $result = [
            'device_models' => $this->normalizeItems($stored['device_models'] ?? [], ['key', 'name', 'desc', 'field_type', 'enabled', 'system']),
            'privacy_blocklists' => $this->normalizeItems($stored['privacy_blocklists'] ?? [], ['key', 'name', 'desc', 'field_type', 'days_ago', 'enabled', 'system', 'devices']),
            'parental_presets' => $this->normalizeItems($stored['parental_presets'] ?? [], ['name', 'key', 'icon', 'category', 'field_type', 'desc', 'enabled', 'url', 'system']),
        ];

        // 确保每组所有项都有 field_type 默认值
        $defaultFieldTypes = [
            'device_models' => 'switch',
            'privacy_blocklists' => 'switch',
            'parental_presets' => 'switch',
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
     * 递归合并默认 devices 到存储数据（不覆盖用户已保存的字段）
     *
     * @param array<string, mixed> $stored
     * @param array<string, mixed> $defaults
     * @return array<string, mixed>
     */
    private function mergeDefaultDevices(array $stored, array $defaults): array
    {
        // 合并 privacy_blocklists 的 devices
        if (isset($defaults['privacy_blocklists'])) {
            foreach ($defaults['privacy_blocklists'] as $defaultItem) {
                $key = $defaultItem['key'] ?? null;
                if ($key === null || ! isset($defaultItem['devices'])) {
                    continue;
                }

                // 查找存储中对应的项
                $storedIdx = null;
                foreach ($stored['privacy_blocklists'] ?? [] as $idx => $item) {
                    if (($item['key'] ?? null) === $key) {
                        $storedIdx = $idx;
                        break;
                    }
                }

                if ($storedIdx === null) {
                    // 存储中不存在该项，添加完整默认项
                    $stored['privacy_blocklists'][] = $defaultItem;
                } else {
                    // 存储中存在该项，合并默认 devices（添加缺失的）
                    $storedDevices = $stored['privacy_blocklists'][$storedIdx]['devices'] ?? [];
                    $storedKeys = array_column($storedDevices, 'key');
                    foreach ($defaultItem['devices'] as $d) {
                        if (!in_array($d['key'], $storedKeys, true)) {
                            $storedDevices[] = $d;
                            $storedKeys[] = $d['key'];
                        }
                    }
                    $stored['privacy_blocklists'][$storedIdx]['devices'] = $storedDevices;
                }
            }
        }

        return $stored;
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
            'parental_presets' => $this->normalizeItems($payload['parental_presets'] ?? [], ['name', 'key', 'icon', 'category', 'field_type', 'desc', 'enabled', 'url', 'system']),
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
    public function defaults(): array
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
                ['key' => 'deep_tracking_protection', 'name' => '深度跟踪保护', 'desc' => '拦截通常在操作系统级运行的深度跟踪软件，这些跟踪软件知道你在设备上的所有行为。这可能包括你访问的所有网站、你输入的所有内容或你的位置。', 'field_type' => 'multi', 'days_ago' => 0, 'enabled' => true, 'system' => true, 'devices' => [
                    ['key' => 'windows', 'name' => 'Windows', 'icon' => '🖥️', 'enabled' => true],
                    ['key' => 'apple', 'name' => '苹果', 'icon' => '🍎', 'enabled' => true],
                    ['key' => 'samsung', 'name' => '三星', 'icon' => '📱', 'enabled' => true],
                    ['key' => 'xiaomi', 'name' => '小米', 'icon' => '📱', 'enabled' => true],
                    ['key' => 'huawei', 'name' => '华为', 'icon' => '📱', 'enabled' => true],
                    ['key' => 'alexa', 'name' => '亚马逊 Alexa 助手', 'icon' => '🔊', 'enabled' => true],
                    ['key' => 'roku', 'name' => 'Roku', 'icon' => '📺', 'enabled' => true],
                    ['key' => 'sonos', 'name' => 'Sonos', 'icon' => '🔊', 'enabled' => true],
                ]],
                ['key' => 'disguised_trackers', 'name' => '拦截伪装过的第三方跟踪器', 'desc' => '自动检测并拦截为了避开 ITP 等隐私保护而伪装成第一方的第三方跟踪器。', 'field_type' => 'switch', 'days_ago' => 0, 'enabled' => true, 'system' => true],
                ['key' => 'allow_marketing_links', 'name' => '允许营销和跟踪链接', 'desc' => '允许在购物网站、电子邮件或搜索结果中常见的营销或跟踪域名。通常只有在手动点击后才能触发这些链接。您的 IP 地址将自动对这些网站隐藏，以保护您的隐私。', 'field_type' => 'switch', 'days_ago' => 0, 'enabled' => false, 'system' => true],
            ],
            'parental_presets' => [
                // 功能类
                ['key' => 'safe_search', 'name' => '安全搜索', 'icon' => '🔍', 'category' => 'website', 'desc' => '在主流搜索引擎上过滤掉含有色情内容的搜索结果，包括图像和视频。如果有搜索引擎不支持此功能，则整个搜索引擎都将被拦截。', 'field_type' => 'switch', 'enabled' => true, 'system' => true],
                ['key' => 'youtube_restricted', 'name' => 'YouTube 受限模式', 'icon' => '📺', 'category' => 'website', 'desc' => '过滤掉 YouTube 上的成人视频，并阻止嵌入的成人视频在其他网站上观看。这也将隐藏所有评论。', 'field_type' => 'switch', 'enabled' => true, 'system' => true],
                ['key' => 'block_bypass', 'name' => '阻止绕过', 'icon' => '🛡️', 'category' => 'website', 'desc' => '阻止用户通过代理或 VPN 绕过家长监护设置。', 'field_type' => 'switch', 'enabled' => true, 'system' => true],
                // 社交网站
                ['key' => 'tiktok', 'name' => 'TikTok/抖音', 'icon' => '🎵', 'category' => 'website', 'desc' => '短视频社交平台', 'field_type' => 'switch', 'enabled' => true, 'system' => true],
                ['key' => 'instagram', 'name' => 'Instagram', 'icon' => '📷', 'category' => 'app', 'desc' => '图片和视频分享社交平台', 'field_type' => 'switch', 'enabled' => true, 'system' => true],
                ['key' => 'snapchat', 'name' => 'Snapchat', 'icon' => '👻', 'category' => 'app', 'desc' => '即时通讯和短视频应用', 'field_type' => 'switch', 'enabled' => true, 'system' => true],
                ['key' => 'facebook', 'name' => 'Facebook', 'icon' => '👤', 'category' => 'website', 'desc' => '社交网络平台', 'field_type' => 'switch', 'enabled' => true, 'system' => true],
                ['key' => 'twitter', 'name' => 'Twitter/X', 'icon' => '🐦', 'category' => 'website', 'desc' => '社交媒体平台', 'field_type' => 'switch', 'enabled' => true, 'system' => true],
                ['key' => 'reddit', 'name' => 'Reddit', 'icon' => '🔴', 'category' => 'website', 'desc' => '社交新闻和讨论平台', 'field_type' => 'switch', 'enabled' => true, 'system' => true],
                ['key' => 'tumblr', 'name' => 'Tumblr', 'icon' => '📝', 'category' => 'website', 'desc' => '轻博客社交平台', 'field_type' => 'switch', 'enabled' => true, 'system' => true],
                ['key' => 'pinterest', 'name' => 'Pinterest', 'icon' => '📌', 'category' => 'website', 'desc' => '图片分享和发现平台', 'field_type' => 'switch', 'enabled' => true, 'system' => true],
                ['key' => 'bereal', 'name' => 'BeReal', 'icon' => '📸', 'category' => 'app', 'desc' => '真实生活分享应用', 'field_type' => 'switch', 'enabled' => true, 'system' => true],
                ['key' => 'mastodon', 'name' => 'Mastodon', 'icon' => '🐘', 'category' => 'app', 'desc' => '去中心化社交网络', 'field_type' => 'switch', 'enabled' => true, 'system' => true],
                ['key' => 'vk', 'name' => 'VK', 'icon' => '💬', 'category' => 'website', 'desc' => '俄罗斯社交网络平台', 'field_type' => 'switch', 'enabled' => true, 'system' => true],
                // 即时通讯
                ['key' => 'telegram', 'name' => 'Telegram', 'icon' => '✈️', 'category' => 'app', 'desc' => '加密即时通讯应用', 'field_type' => 'switch', 'enabled' => true, 'system' => true],
                ['key' => 'whatsapp', 'name' => 'WhatsApp', 'icon' => '💚', 'category' => 'app', 'desc' => '即时通讯应用', 'field_type' => 'switch', 'enabled' => true, 'system' => true],
                ['key' => 'signal', 'name' => 'Signal', 'icon' => '🔒', 'category' => 'app', 'desc' => '加密隐私通讯应用', 'field_type' => 'switch', 'enabled' => true, 'system' => true],
                ['key' => 'discord', 'name' => 'Discord', 'icon' => '🎮', 'category' => 'app', 'desc' => '游戏社区和语音聊天平台', 'field_type' => 'switch', 'enabled' => true, 'system' => true],
                ['key' => 'messenger', 'name' => 'Messenger', 'icon' => '💭', 'category' => 'app', 'desc' => 'Facebook 即时通讯', 'field_type' => 'switch', 'enabled' => true, 'system' => true],
                ['key' => 'skype', 'name' => 'Skype', 'icon' => '📞', 'category' => 'app', 'desc' => '视频通话和即时通讯', 'field_type' => 'switch', 'enabled' => true, 'system' => true],
                ['key' => 'googlechat', 'name' => 'Google Chat', 'icon' => '🟢', 'category' => 'app', 'desc' => 'Google 企业通讯工具', 'field_type' => 'switch', 'enabled' => true, 'system' => true],
                ['key' => 'zoom', 'name' => 'Zoom', 'icon' => '📹', 'category' => 'app', 'desc' => '视频会议平台', 'field_type' => 'switch', 'enabled' => true, 'system' => true],
                ['key' => 'tinder', 'name' => 'Tinder', 'icon' => '🔥', 'category' => 'app', 'desc' => '交友约会应用', 'field_type' => 'switch', 'enabled' => true, 'system' => true],
                // 视频/流媒体
                ['key' => 'youtube', 'name' => 'YouTube', 'icon' => '▶️', 'category' => 'website', 'desc' => '视频分享平台', 'field_type' => 'switch', 'enabled' => true, 'system' => true],
                ['key' => 'twitch', 'name' => 'Twitch', 'icon' => '🟣', 'category' => 'website', 'desc' => '游戏直播平台', 'field_type' => 'switch', 'enabled' => true, 'system' => true],
                ['key' => 'dailymotion', 'name' => 'Dailymotion', 'icon' => '🎬', 'category' => 'website', 'desc' => '视频分享平台', 'field_type' => 'switch', 'enabled' => true, 'system' => true],
                ['key' => 'vimeo', 'name' => 'Vimeo', 'icon' => '🎥', 'category' => 'website', 'desc' => '专业视频平台', 'field_type' => 'switch', 'enabled' => true, 'system' => true],
                ['key' => 'netflix', 'name' => 'Netflix', 'icon' => '🎬', 'category' => 'website', 'desc' => '流媒体视频平台', 'field_type' => 'switch', 'enabled' => true, 'system' => true],
                ['key' => 'hulu', 'name' => 'Hulu', 'icon' => '📺', 'category' => 'website', 'desc' => '美国流媒体平台', 'field_type' => 'switch', 'enabled' => true, 'system' => true],
                ['key' => 'disneyplus', 'name' => 'Disney+', 'icon' => '🏰', 'category' => 'website', 'desc' => '迪士尼流媒体平台', 'field_type' => 'switch', 'enabled' => true, 'system' => true],
                ['key' => 'primevideo', 'name' => 'Prime Video', 'icon' => '📦', 'category' => 'website', 'desc' => '亚马逊流媒体平台', 'field_type' => 'switch', 'enabled' => true, 'system' => true],
                ['key' => 'hbomax', 'name' => 'HBO Max', 'icon' => '🎭', 'category' => 'website', 'desc' => '华纳流媒体平台', 'field_type' => 'switch', 'enabled' => true, 'system' => true],
                ['key' => 'spotify', 'name' => 'Spotify', 'icon' => '🎵', 'category' => 'website', 'desc' => '音乐流媒体平台', 'field_type' => 'switch', 'enabled' => true, 'system' => true],
                // 游戏
                ['key' => 'roblox', 'name' => 'Roblox', 'icon' => '🎮', 'category' => 'game', 'desc' => '在线游戏平台', 'field_type' => 'switch', 'enabled' => true, 'system' => true],
                ['key' => 'fortnite', 'name' => 'Fortnite', 'icon' => '🏗️', 'category' => 'game', 'desc' => '堡垒之夜游戏', 'field_type' => 'switch', 'enabled' => true, 'system' => true],
                ['key' => 'leagueoflegends', 'name' => 'League of Legends', 'icon' => '⚔️', 'category' => 'game', 'desc' => '英雄联盟游戏', 'field_type' => 'switch', 'enabled' => true, 'system' => true],
                ['key' => 'minecraft', 'name' => 'Minecraft', 'icon' => '⛏️', 'category' => 'game', 'desc' => '我的世界游戏', 'field_type' => 'switch', 'enabled' => true, 'system' => true],
                ['key' => 'blizzard', 'name' => 'Blizzard', 'icon' => '❄️', 'category' => 'game', 'desc' => '暴雪游戏平台', 'field_type' => 'switch', 'enabled' => true, 'system' => true],
                ['key' => 'steam', 'name' => 'Steam', 'icon' => '🎲', 'category' => 'game', 'desc' => 'Valve 游戏平台', 'field_type' => 'switch', 'enabled' => true, 'system' => true],
                ['key' => 'xboxlive', 'name' => 'Xbox Live', 'icon' => '🟢', 'category' => 'game', 'desc' => '微软游戏服务', 'field_type' => 'switch', 'enabled' => true, 'system' => true],
                ['key' => 'playstation', 'name' => 'PlayStation Network', 'icon' => '🔵', 'category' => 'game', 'desc' => '索尼游戏服务', 'field_type' => 'switch', 'enabled' => true, 'system' => true],
                // 其他
                ['key' => '9gag', 'name' => '9GAG', 'icon' => '😂', 'category' => 'website', 'desc' => '搞笑图片和视频平台', 'field_type' => 'switch', 'enabled' => true, 'system' => true],
                ['key' => 'imgur', 'name' => 'Imgur', 'icon' => '🖼️', 'category' => 'website', 'desc' => '图片分享平台', 'field_type' => 'switch', 'enabled' => true, 'system' => true],
                ['key' => 'ebay', 'name' => 'eBay', 'icon' => '🛒', 'category' => 'website', 'desc' => '在线拍卖和购物平台', 'field_type' => 'switch', 'enabled' => true, 'system' => true],
                ['key' => 'amazon', 'name' => 'Amazon', 'icon' => '📦', 'category' => 'website', 'desc' => '在线购物平台', 'field_type' => 'switch', 'enabled' => true, 'system' => true],
                ['key' => 'chatgpt', 'name' => 'ChatGPT', 'icon' => '🤖', 'category' => 'website', 'desc' => 'AI 对话平台', 'field_type' => 'switch', 'enabled' => true, 'system' => true],
            ],
        ];
    }
}
