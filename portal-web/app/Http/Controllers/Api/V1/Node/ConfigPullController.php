<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Node;

use App\Models\ProfileVersion;
use App\Models\Plan;
use App\Models\Profile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Resolver 配置拉取控制器。
 *
 * 架构：Global Config + Lazy Profile
 *   - GET /config           → 公共运行参数（upstreams / plans / rulesets）
 *   - GET /profiles/{id}    → 单个 Profile 配置（按需拉取）
 *   - POST /profiles/check  → 批量版本检查
 */
final class ConfigPullController
{
    /**
     * 拉取 Global Config：Resolver 公共运行配置，不含任何用户 Profile 数据。
     *
     * GET /api/v1/node/dns-resolver/config
     */
    public function show(): JsonResponse
    {
        $plans = Plan::where('status', 'active')->get()->mapWithKeys(fn ($plan) => [
            $plan->code => [
                'monthly_query_limit' => (int) $plan->monthly_query_limit,
                'profiles_limit'      => (int) $plan->profiles_limit,
                'devices_limit'       => (int) $plan->devices_limit,
                'log_retention_days'  => (int) $plan->log_retention_days,
            ],
        ]);

        $data = [
            // 2026-06-30: Global Config version 与 Profile 版本解耦，独立维护
            'version' => (int) (DB::table('dns_global_config_versions')->max('version') ?? 1),
            'upstreams' => [$this->defaultUpstream()],
            'plans'     => $plans,
            'rulesets'  => [],
            'limits'    => [
                'max_qps'        => (int) config('dns.max_qps', 1000),
                'rate_limit_rps' => (int) config('dns.rate_limit_rps', 100),
            ],
        ];

        // 2026-06-27: 为 Global Config 补充 checksum 和 generated_at，
        // Resolver 端会校验 checksum 字段以确认配置完整性。
        $data['generated_at'] = now()->toIso8601String();
        $data['checksum'] = hash('sha256', json_encode($data));

        return response()->json(['data' => $data]);
    }

    /**
     * 拉取单个 Profile 的完整配置（resolver 按需调用）。
     *
     * GET /api/v1/node/dns-resolver/profiles/{profileId}
     */
    public function showProfile(string $profileId): JsonResponse
    {
        // 1. 通过 6 位 hex 查找 Profile
        $profile = Profile::where('profile_id', $profileId)->first();
        if (! $profile) {
            return \App\Helpers\ApiResponse::error('NOT_FOUND', 'Profile not found', 404);
        }

        // 2. 取该 Profile 的最新 ConfigVersion
        $configVersion = ProfileVersion::where('target_profile_id', $profile->id)
            ->orderByDesc('version')
            ->first();

        if (! $configVersion) {
            return \App\Helpers\ApiResponse::error('NOT_FOUND', 'No published config for this profile', 404);
        }

        // 3. 解析 config_json
        $raw = $configVersion->getRawOriginal('config_json');
        $config = is_string($raw) ? json_decode($raw, true) : (array) $raw;

        if (! is_array($config)) {
            return \App\Helpers\ApiResponse::error('INTERNAL_ERROR', 'Invalid config format', 500);
        }

        // 4. 确保 quota 对象格式正确
        if (array_key_exists('quota', $config)) {
            $config['quota'] = (object) $config['quota'];
        } else {
            $config['quota'] = (object) [];
        }

        // 4b. 确保 security_data 对象格式正确（避免 json_decode true 后空对象变空数组）
        if (array_key_exists('security_data', $config)) {
            $config['security_data'] = (object) $config['security_data'];
        }

        // 5. rule_id 转为字符串（resolver 期望 string 类型）
        if (isset($config['rules']) && is_array($config['rules'])) {
            foreach ($config['rules'] as $i => $r) {
                if (is_array($r) && array_key_exists('rule_id', $r)) {
                    $config['rules'][$i]['rule_id'] = (string) $r['rule_id'];
                }
            }
        }

        return response()->json(['data' => $config]);
    }

    /**
     * 批量检查 Profile 版本：resolver 上报本地缓存的版本号，
     * Portal 返回有更新的 Profile 列表。
     *
     * POST /api/v1/node/dns-resolver/profiles/check
     *
     * @bodyParam profiles object { profile_id: local_version, ... }
     */
    public function checkProfiles(Request $request): JsonResponse
    {
        $clientVersions = $request->input('profiles', []);
        if (! is_array($clientVersions) || $clientVersions === []) {
            return response()->json(['data' => ['updated' => []]]);
        }

        $updated = [];

        // 批量查找所有请求的 Profile
        $profileIds = array_keys($clientVersions);
        $profiles = Profile::whereIn('profile_id', $profileIds)
            ->get(['id', 'profile_id'])
            ->keyBy('profile_id');

        foreach ($clientVersions as $profileId => $localVersion) {
            $profile = $profiles->get($profileId);
            if (! $profile) {
                continue;
            }
            $latestVersion = ProfileVersion::where('target_profile_id', $profile->id)
                ->max('version');
            if ($latestVersion !== null && (int) $latestVersion > (int) $localVersion) {
                $updated[$profileId] = (int) $latestVersion;
            }
        }

        return response()->json(['data' => ['updated' => $updated]]);
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultUpstream(): array
    {
        return [
            'address'  => config('dns.default_upstream', '1.1.1.1:53'),
            'protocol' => 'udp',
            'timeout'  => '1500ms',
        ];
    }
}
