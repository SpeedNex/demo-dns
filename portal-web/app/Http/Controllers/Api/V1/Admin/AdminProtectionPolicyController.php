<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\AdminAuditLog;
use App\Models\SystemConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 防护策略管理（全局默认值，对齐前台 /user/:id/security 的 security_settings 字段）
 *
 * 该配置作为所有 Profile 的 security 默认值基线，
 * 各 Profile 仍可在前台单独覆盖自己的 security_settings。
 */
final class AdminProtectionPolicyController
{
    private const GROUP_KEY = 'protection';

    public function show(): JsonResponse
    {
        $row = SystemConfig::where('config_key', self::GROUP_KEY)->first();
        $value = $row?->config_value ?? $this->defaultPolicies();

        return response()->json(['data' => $this->normalize($value)]);
    }

    public function update(Request $request): JsonResponse
    {
        $actorId = $request->user()?->admin_id;
        $payload = $request->validate([
            // 算法开关（对齐前台 Security.vue）
            'dns_rebind' => 'sometimes|boolean',
            'idn_homograph' => 'sometimes|boolean',
            'typo_squatting' => 'sometimes|boolean',
            'dga_protection' => 'sometimes|boolean',
            'block_tld' => 'sometimes|boolean',
            'block_disguised_trackers' => 'sometimes|boolean',
            'block_new_domains' => 'sometimes|boolean',
            'block_dynamic_dns' => 'sometimes|boolean',
            'block_parked_domains' => 'sometimes|boolean',
            // 威胁情报开关
            'threat_intel' => 'sometimes|boolean',
            'ai_threat_detection' => 'sometimes|boolean',
            'google_safe_browsing' => 'sometimes|boolean',
            'child_abuse' => 'sometimes|boolean',
            // 分类开关
            'block_malware' => 'sometimes|boolean',
            'block_phishing' => 'sometimes|boolean',
            'block_command_and_control' => 'sometimes|boolean',
            'block_cryptojacking' => 'sometimes|boolean',
            // DNS 重绑定白名单
            'dns_rebind_whitelist' => 'sometimes|array',
            'dns_rebind_whitelist.*' => 'string|max:255',
            // DGA 阈值
            'dga_entropy_threshold' => 'sometimes|numeric|min:3.0|max:5.5',
            'dga_digit_ratio' => 'sometimes|numeric|min:0|max:1',
            // 误植阈值
            'typo_threshold' => 'sometimes|integer|min:1|max:2',
        ]);

        // 合并保留其他字段
        $row = SystemConfig::where('config_key', self::GROUP_KEY)->first();
        $existing = $row?->config_value ?? $this->defaultPolicies();
        $merged = array_merge($existing, $payload);

        $newRow = SystemConfig::updateOrCreate(
            ['config_key' => self::GROUP_KEY],
            [
                'config_value' => $merged,
                'description' => 'Protection policies (global defaults, aligned with frontend /user/:id/security)',
                'is_secret' => false,
            ]
        );

        AdminAuditLog::record('protection.update', 'system_config', $newRow->id, $payload, $actorId, null, $request->ip(), $request->userAgent());

        return response()->json(['data' => $this->normalize($merged)]);
    }

    public function export(): JsonResponse
    {
        $row = SystemConfig::where('config_key', self::GROUP_KEY)->first();

        return response()->json([
            'data' => [
                'exported_at' => now()->toIso8601String(),
                'config' => $row?->config_value ?? $this->defaultPolicies(),
            ],
        ]);
    }

    public function import(Request $request): JsonResponse
    {
        $actorId = $request->user()?->admin_id;
        $validated = $request->validate([
            'config' => 'required|array',
        ]);

        $newRow = SystemConfig::updateOrCreate(
            ['config_key' => self::GROUP_KEY],
            [
                'config_value' => $validated['config'],
                'description' => 'Protection policies (imported)',
                'is_secret' => false,
            ]
        );

        AdminAuditLog::record('protection.import', 'system_config', $newRow->id, [], $actorId, null, $request->ip(), $request->userAgent());

        return response()->json(['data' => ['imported' => true]]);
    }

    private function normalize(array $value): array
    {
        $defaults = $this->defaultPolicies();
        return array_merge($defaults, $value);
    }

    private function defaultPolicies(): array
    {
        return [
            // 算法开关（对齐 UserWorkspaceService::DEFAULT_SECURITY）
            'threat_intel' => true,
            'ai_threat_detection' => false,
            'google_safe_browsing' => true,
            'block_malware' => true,
            'block_phishing' => true,
            'block_command_and_control' => true,
            'block_cryptojacking' => true,
            'dns_rebind' => true,
            'idn_homograph' => true,
            'typo_squatting' => true,
            'dga_protection' => true,
            'block_new_domains' => true,
            'block_dynamic_dns' => false,
            'block_parked_domains' => true,
            'block_tld' => false,
            'child_abuse' => true,
            // 伪装追踪器（对齐 Privacy.vue）
            'block_disguised_trackers' => true,
            // DNS 重绑定白名单
            'dns_rebind_whitelist' => ['localhost', '*.local'],
            // DGA 阈值
            'dga_entropy_threshold' => 4.2,
            'dga_digit_ratio' => 0.6,
            // 误植阈值
            'typo_threshold' => 1,
        ];
    }
}
