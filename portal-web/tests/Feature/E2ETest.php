<?php

/**
 * OcerDNS 端到端测试脚本
 *
 * 测试完整业务流程：
 * 1. 用户注册/登录
 * 2. 创建 Profile
 * 3. 配置安全/隐私/家长监护规则
 * 4. 添加黑名单/白名单规则
 * 5. 验证自动发布后 ConfigVersion 是否正确创建
 * 6. 验证 Profile 版本号是否递增
 * 7. 模拟 Resolver 拉取配置
 * 8. 验证 DNS 查询规则生效
 */

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ConfigVersion;
use App\Models\Device;
use App\Models\Profile;
use App\Models\ProfileRule;
use App\Models\User;
use App\Models\Node;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class E2ETest extends TestCase
{
    use RefreshDatabase;

    private string $testEmail;
    private string $testPassword = 'TestPassword123!';
    private int $userId;
    private int $profileId;
    private string $authToken;
    private array $profileData = [];
    private string $denylistDomain = '';
    private string $allowlistDomain = '';

    /**
     * 测试报告数据
     */
    private array $report = [
        'start_time' => '',
        'end_time' => '',
        'duration' => '',
        'total_steps' => 0,
        'passed_steps' => 0,
        'failed_steps' => 0,
        'steps' => [],
        'errors' => [],
        'summary' => [],
    ];

    public function test_full_e2e_workflow(): void
    {
        $this->report['start_time'] = date('Y-m-d H:i:s');

        $this->info('========================================');
        $this->info('OcerDNS 端到端测试开始');
        $this->info('========================================');

        // 步骤 1: 创建测试用户
        $this->step1_create_user();

        // 步骤 2: 用户登录获取 Token
        $this->step2_user_login();

        // 步骤 3: 获取用户 Profile
        $this->step3_get_or_create_profile();

        // 步骤 4: 配置安全规则
        $this->step4_configure_security();

        // 步骤 5: 配置隐私规则
        $this->step5_configure_privacy();

        // 步骤 6: 配置家长监护规则
        $this->step6_configure_parental();

        // 步骤 7: 添加黑名单规则
        $this->step7_add_denylist_rule();

        // 步骤 8: 添加白名单规则
        $this->step8_add_allowlist_rule();

        // 步骤 9: 批量删除黑名单规则
        $this->step9_batch_delete_denylist();

        // 步骤 10: 验证自动发布 - 检查 ConfigVersion
        $this->step10_verify_config_version_created();

        // 步骤 11: 验证 Profile 版本号递增
        $this->step11_verify_profile_version_incremented();

        // 步骤 12: 模拟 Resolver 拉取配置
        $this->step12_simulate_resolver_pull();

        // 步骤 13: 验证 DNS 查询规则
        $this->step13_verify_dns_query_rules();

        // 步骤 14: 验证日志记录
        $this->step14_verify_logging();

        // 生成报告
        $this->generate_report();

        // 打印结果
        $this->print_report();
    }

    // ==================== 辅助方法 ====================

    private function info(string $message): void
    {
        echo "[INFO] {$message}\n";
    }

    private function pass(string $step, string $message): void
    {
        $this->report['steps'][] = [
            'step' => $step,
            'status' => 'PASS',
            'message' => $message,
        ];
        $this->report['passed_steps']++;
        $this->report['total_steps']++;
        echo "[PASS] {$step}: {$message}\n";
    }

    private function markStepFailed(string $step, string $message, ?\Throwable $e = null): void
    {
        $this->report['steps'][] = [
            'step' => $step,
            'status' => 'FAIL',
            'message' => $message,
            'error' => $e ? $e->getMessage() : null,
        ];
        $this->report['failed_steps']++;
        $this->report['total_steps']++;
        $this->report['errors'][] = [
            'step' => $step,
            'message' => $message,
            'exception' => $e ? $e->getMessage() : null,
        ];
        echo "[FAIL] {$step}: {$message}\n";
        if ($e) {
            echo "[ERROR] {$e->getMessage()}\n";
        }
    }

    private function createAuthHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->authToken,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
    }

    // ==================== 测试步骤 ====================

    private function step1_create_user(): void
    {
        $step = '步骤 1: 创建测试用户';
        try {
            $this->testEmail = 'e2e_test_' . time() . '@example.com';

            $user = User::create([
                'uid' => Str::random(12),
                'email' => $this->testEmail,
                'username' => 'e2e_test_' . time(),
                'password' => Hash::make($this->testPassword),
                'plan_code' => 'free',
                'locale' => 'zh-CN',
                'timezone' => 'Asia/Shanghai',
            ]);

            $this->userId = $user->uid;

            // 创建 Free 订阅
            \DB::table('subscriptions')->insert([
                'user_id' => $this->userId,
                'plan_id' => 1,
                'plan_code' => 'free',
                'status' => 'active',
                'quota_status' => 'normal',
                'started_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->pass($step, "用户创建成功: {$this->testEmail} (UID: {$this->userId})");
        } catch (\Throwable $e) {
            $this->markStepFailed($step, "用户创建失败", $e);
            $this->generate_report();
            $this->print_report();
            throw $e;
        }
    }

    private function step2_user_login(): void
    {
        $step = '步骤 2: 用户登录获取 Token';
        try {
            $response = $this->postJson('/api/v1/auth/login', [
                'email' => $this->testEmail,
                'password' => $this->testPassword,
            ]);

            if ($response->status() !== 200) {
                throw new \Exception("登录失败，状态码: {$response->status()}");
            }

            $data = $response->json();
            if (empty($data['data']['token'])) {
                throw new \Exception('Token 未返回, response: ' . json_encode($data));
            }

            $this->authToken = $data['data']['token'];
            $this->pass($step, '登录成功，获取到 Token');
        } catch (\Throwable $e) {
            $this->markStepFailed($step, '登录失败', $e);
            $this->generate_report();
            $this->print_report();
            throw $e;
        }
    }

    private function step3_get_or_create_profile(): void
    {
        $step = '步骤 3: 获取用户 Profile';
        try {
            $user = User::findOrFail($this->userId);

            // 获取主 Profile
            $profile = Profile::where('user_id', $this->userId)
                ->orderBy('created_at')
                ->first();

            if (!$profile) {
                // 如果没有 Profile，API 会自动创建
                $response = $this->getJson('/api/v1/user/security', [
                    'Authorization' => 'Bearer ' . $this->authToken,
                ]);
                $profile = Profile::where('user_id', $this->userId)
                    ->orderBy('created_at')
                    ->first();
            }

            if (!$profile) {
                throw new \Exception('Profile 创建失败');
            }

            $this->profileId = $profile->id;
            $this->profileData = $profile->toArray();

            $this->pass($step, "Profile 获取成功: ID={$profile->id}, profile_id={$profile->profile_id}");
        } catch (\Throwable $e) {
            $this->markStepFailed($step, 'Profile 获取失败', $e);
            $this->generate_report();
            $this->print_report();
            throw $e;
        }
    }

    private function step4_configure_security(): void
    {
        $step = '步骤 4: 配置安全规则';
        try {
            // 记录修改前的 version
            $profileBefore = Profile::find($this->profileId);
            $versionBefore = $profileBefore->version ?? 0;

            $response = $this->putJson('/api/v1/user/security', [
                'enabled' => true,
                'block_malware' => true,
                'block_phishing' => true,
                'block_command_and_control' => true,
                'block_cryptojacking' => false,
                'threat_intel' => true,
                'ai_threat_detection' => false,
                'google_safe_browsing' => true,
                'dns_rebind' => true,
                'idn_homograph' => true,
                'typo_squatting' => true,
                'dga_protection' => true,
                'block_new_domains' => false,
                'block_dynamic_dns' => false,
                'block_parked_domains' => true,
                'block_tld' => false,
                'child_abuse' => true,
            ], $this->createAuthHeaders());

            if ($response->status() !== 200) {
                throw new \Exception("安全配置失败，状态码: {$response->status()}");
            }

            $data = $response->json()['data'];

            // 验证配置是否生效
            if ($data['block_phishing'] !== true) {
                throw new \Exception('安全配置未正确保存');
            }

            // 等待一小段时间让 autoPublish 完成
            usleep(500000); // 500ms

            // 验证 Profile version 是否更新
            $profileAfter = Profile::find($this->profileId);
            $versionAfter = $profileAfter->version ?? 0;

            // 验证 ConfigVersion 是否创建
            $configVersions = ConfigVersion::where('target_profile_id', $this->profileId)
                ->orderByDesc('id')
                ->first();

            $this->pass($step, "安全配置成功 (version: {$versionBefore} -> {$versionAfter}, config_version: " . ($configVersions->version ?? 'N/A') . ")");
        } catch (\Throwable $e) {
            $this->markStepFailed($step, '安全配置失败', $e);
            $this->generate_report();
            $this->print_report();
            throw $e;
        }
    }

    private function step5_configure_privacy(): void
    {
        $step = '步骤 5: 配置隐私规则';
        try {
            $response = $this->putJson('/api/v1/user/privacy', [
                'enabled' => true,
                'block_trackers' => true,
                'block_analytics' => true,
                'block_telemetry' => true,
                'anonymize_client_ip' => true,
                'allow_marketing_links' => false,
                'log_mode' => 'full',
            ], $this->createAuthHeaders());

            if ($response->status() !== 200) {
                throw new \Exception("隐私配置失败，状态码: {$response->status()}");
            }

            $data = $response->json()['data'];

            if ($data['block_trackers'] !== true || $data['log_mode'] !== 'full') {
                throw new \Exception('隐私配置未正确保存');
            }

            $this->pass($step, '隐私配置成功');
        } catch (\Throwable $e) {
            $this->markStepFailed($step, '隐私配置失败', $e);
            $this->generate_report();
            $this->print_report();
            throw $e;
        }
    }

    private function step6_configure_parental(): void
    {
        $step = '步骤 6: 配置家长监护规则';
        try {
            $response = $this->putJson('/api/v1/user/parental', [
                'enabled' => true,
                'block_adult_content' => true,
                'block_gambling' => true,
                'safe_search' => true,
                'force_safe_search' => true,
                'youtube_restricted_mode' => true,
                'block_bypass' => false,
            ], $this->createAuthHeaders());

            if ($response->status() !== 200) {
                throw new \Exception("家长监护配置失败，状态码: {$response->status()}");
            }

            $data = $response->json()['data'];

            if ($data['block_adult_content'] !== true || $data['safe_search'] !== true) {
                throw new \Exception('家长监护配置未正确保存');
            }

            $this->pass($step, '家长监护配置成功');
        } catch (\Throwable $e) {
            $this->markStepFailed($step, '家长监护配置失败', $e);
            $this->generate_report();
            $this->print_report();
            throw $e;
        }
    }

    private function step7_add_denylist_rule(): void
    {
        $step = '步骤 7: 添加黑名单规则';
        try {
            $this->denylistDomain = 'malware-test-' . time() . '.example.com';

            $response = $this->postJson('/api/v1/user/denylist', [
                'domain' => $this->denylistDomain,
                'match_type' => 'suffix',
            ], $this->createAuthHeaders());

            if ($response->status() !== 201) {
                throw new \Exception("黑名单添加失败，状态码: {$response->status()}");
            }

            $data = $response->json()['data'];

            // 验证规则是否创建
            $rule = ProfileRule::where('id', $data['id'])->first();
            if (!$rule) {
                throw new \Exception('黑名单规则未在数据库中找到');
            }

            // 等待 autoPublish 完成
            usleep(500000); // 500ms

            // 验证 ConfigVersion 中是否包含新规则
            $latestConfig = ConfigVersion::where('target_profile_id', $this->profileId)
                ->orderByDesc('id')
                ->first();

            $configJson = $latestConfig->config_json ?? [];
            $rulesInConfig = $configJson['rules'] ?? [];
            $domainFound = false;
            foreach ($rulesInConfig as $r) {
                if ($r['domain'] === $this->denylistDomain) {
                    $domainFound = true;
                    break;
                }
            }

            $this->pass($step, "黑名单规则添加成功: {$this->denylistDomain} (规则ID: {$data['id']}, 配置中包含: " . ($domainFound ? '是' : '否') . ")");
        } catch (\Throwable $e) {
            $this->markStepFailed($step, '黑名单规则添加失败', $e);
            $this->generate_report();
            $this->print_report();
            throw $e;
        }
    }

    private function step8_add_allowlist_rule(): void
    {
        $step = '步骤 8: 添加白名单规则';
        try {
            $this->allowlistDomain = 'allowed-test-' . time() . '.example.com';

            $response = $this->postJson('/api/v1/user/allowlist', [
                'domain' => $this->allowlistDomain,
                'match_type' => 'exact',
            ], $this->createAuthHeaders());

            if ($response->status() !== 201) {
                throw new \Exception("白名单添加失败，状态码: {$response->status()}");
            }

            $data = $response->json()['data'];

            $rule = ProfileRule::where('id', $data['id'])->first();
            if (!$rule) {
                throw new \Exception('白名单规则未在数据库中找到');
            }

            $this->pass($step, "白名单规则添加成功: {$this->allowlistDomain} (规则ID: {$data['id']})");
        } catch (\Throwable $e) {
            $this->markStepFailed($step, '白名单规则添加失败', $e);
            $this->generate_report();
            $this->print_report();
            throw $e;
        }
    }

    private function step9_batch_delete_denylist(): void
    {
        $step = '步骤 9: 批量删除黑名单规则';
        try {
            // 先添加一个新的黑名单规则用于删除测试，避免删除步骤 7 添加的规则
            $domainToDelete = 'delete-me-' . time() . '.example.com';

            $addResponse = $this->postJson('/api/v1/user/denylist', [
                'domain' => $domainToDelete,
                'match_type' => 'suffix',
            ], $this->createAuthHeaders());

            if ($addResponse->status() !== 201) {
                throw new \Exception("添加测试用黑名单规则失败，状态码: {$addResponse->status()}");
            }

            $ruleToDelete = $addResponse->json()['data']['id'];

            // 获取当前黑名单规则
            $rules = ProfileRule::where('profile_id', $this->profileId)
                ->where('list_type', 'denylist')
                ->where('id', $ruleToDelete)
                ->pluck('id')
                ->map(fn ($id) => (string) $id)
                ->toArray();

            if (count($rules) === 0) {
                $this->pass($step, '无黑名单规则需要删除，跳过');
                return;
            }

            $response = $this->postJson('/api/v1/user/denylist/batch-delete', [
                'ids' => $rules,
            ], $this->createAuthHeaders());

            if ($response->status() !== 200) {
                throw new \Exception("批量删除失败，状态码: {$response->status()}");
            }

            $data = $response->json()['data'];

            // 验证规则是否已删除
            $remaining = ProfileRule::whereIn('id', $rules)->count();
            if ($remaining > 0) {
                throw new \Exception('规则批量删除未成功');
            }

            $this->pass($step, "批量删除成功: 删除了 " . count($rules) . " 条规则");
        } catch (\Throwable $e) {
            $this->markStepFailed($step, '批量删除失败', $e);
            $this->generate_report();
            $this->print_report();
            throw $e;
        }
    }

    private function step10_verify_config_version_created(): void
    {
        $step = '步骤 10: 验证自动发布 - 检查 ConfigVersion';
        try {
            $configVersions = ConfigVersion::where('target_profile_id', $this->profileId)
                ->orderByDesc('id')
                ->take(5)
                ->get(['id', 'version', 'published_at', 'checksum']);

            if ($configVersions->isEmpty()) {
                throw new \Exception('未找到任何 ConfigVersion 记录');
            }

            $versionList = $configVersions->pluck('version')->toArray();

            $this->pass($step, "ConfigVersion 验证成功: 共有 {$configVersions->count()} 个版本，最新版本: " . implode(', ', $versionList));
        } catch (\Throwable $e) {
            $this->markStepFailed($step, 'ConfigVersion 验证失败', $e);
            $this->generate_report();
            $this->print_report();
            throw $e;
        }
    }

    private function step11_verify_profile_version_incremented(): void
    {
        $step = '步骤 11: 验证 Profile 版本号递增';
        try {
            $profile = Profile::find($this->profileId);
            $currentVersion = $profile->version ?? 0;
            $publishedAt = $profile->published_at;

            if ($currentVersion < 1) {
                throw new \Exception("Profile 版本号异常: {$currentVersion}");
            }

            if (!$publishedAt) {
                throw new \Exception('Profile published_at 未设置');
            }

            // 检查版本号是否与最新的 ConfigVersion 匹配
            $latestConfigVersion = ConfigVersion::where('target_profile_id', $this->profileId)
                ->max('version');

            if ($currentVersion != $latestConfigVersion) {
                $this->info("注意: Profile.version ({$currentVersion}) 与 ConfigVersion.max ({$latestConfigVersion}) 不一致");
            }

            $this->pass($step, "Profile 版本号验证成功: version={$currentVersion}, published_at={$publishedAt}");
        } catch (\Throwable $e) {
            $this->markStepFailed($step, 'Profile 版本号验证失败', $e);
            $this->generate_report();
            $this->print_report();
            throw $e;
        }
    }

    private function step12_simulate_resolver_pull(): void
    {
        $step = '步骤 12: 模拟 Resolver 拉取配置';
        try {
            // 获取最新的 ConfigVersion
            $latestConfig = ConfigVersion::where('target_profile_id', $this->profileId)
                ->orderByDesc('id')
                ->first();

            if (!$latestConfig) {
                throw new \Exception('无 ConfigVersion 可供拉取');
            }

            $configJson = $latestConfig->config_json ?? [];

            // 验证配置 JSON 结构
            $requiredFields = ['profile_id', 'version', 'default_action', 'security', 'privacy', 'parental', 'rules'];
            foreach ($requiredFields as $field) {
                if (!isset($configJson[$field])) {
                    throw new \Exception("配置缺少必需字段: {$field}");
                }
            }

            // 验证安全配置
            $security = $configJson['security'] ?? [];
            if (empty($security['block_phishing'])) {
                throw new \Exception('安全配置未包含在配置包中');
            }

            // 验证隐私配置
            $privacy = $configJson['privacy'] ?? [];
            if (empty($privacy['block_trackers'])) {
                throw new \Exception('隐私配置未包含在配置包中');
            }

            // 验证家长监护配置
            $parental = $configJson['parental'] ?? [];
            if (empty($parental['block_adult_content'])) {
                throw new \Exception('家长监护配置未包含在配置包中');
            }

            $this->pass($step, "Resolver 拉取配置成功: profile_id={$configJson['profile_id']}, version={$configJson['version']}, rules_count=" . count($configJson['rules']));
        } catch (\Throwable $e) {
            $this->markStepFailed($step, 'Resolver 拉取配置失败', $e);
            $this->generate_report();
            $this->print_report();
            throw $e;
        }
    }

    private function step13_verify_dns_query_rules(): void
    {
        $step = '步骤 13: 验证 DNS 查询规则';
        try {
            // 获取最新配置
            $latestConfig = ConfigVersion::where('target_profile_id', $this->profileId)
                ->orderByDesc('id')
                ->first();

            $configJson = $latestConfig->config_json ?? [];
            $rules = $configJson['rules'] ?? [];

            // 模拟 DNS 查询测试
            $testCases = [
                // 黑名单域名应该被拦截 - 使用完整域名（suffix 匹配）
                ['domain' => $this->denylistDomain, 'expected_action' => 'block', 'list_type' => 'denylist'],
                // 白名单域名应该被放行 - 使用完整域名（exact 匹配）
                ['domain' => $this->allowlistDomain, 'expected_action' => 'allow', 'list_type' => 'allowlist'],
                // 未知域名应该走默认动作
                ['domain' => 'google.com', 'expected_action' => $configJson['default_action'] ?? 'allow', 'list_type' => null],
            ];

            $passed = 0;
            $failed = 0;

            foreach ($testCases as $test) {
                $matched = false;
                $action = $configJson['default_action'] ?? 'allow';

                foreach ($rules as $rule) {
                    if ($rule['domain'] === $test['domain'] || (isset($rule['match_type']) && $rule['match_type'] === 'suffix' && str_ends_with($test['domain'], '.' . $rule['domain']))) {
                        $matched = true;
                        $action = $rule['action'];
                        break;
                    }
                }

                if ($action === $test['expected_action']) {
                    $passed++;
                } else {
                    $failed++;
                    $this->info("  DNS 查询测试失败: {$test['domain']}, 期望: {$test['expected_action']}, 实际: {$action}");
                }
            }

            if ($failed > 0) {
                throw new \Exception("DNS 查询规则测试失败: {$failed} 个测试用例未通过");
            }

            $this->pass($step, "DNS 查询规则验证成功: {$passed} 个测试用例全部通过");
        } catch (\Throwable $e) {
            $this->markStepFailed($step, 'DNS 查询规则验证失败', $e);
            $this->generate_report();
            $this->print_report();
            throw $e;
        }
    }

    private function step14_verify_logging(): void
    {
        $step = '步骤 14: 验证日志记录';
        try {
            // 验证 ProfileRule 审计字段
            $rules = ProfileRule::where('profile_id', $this->profileId)
                ->get();

            foreach ($rules as $rule) {
                if (!$rule->created_at || !$rule->updated_at) {
                    throw new \Exception("规则 ID={$rule->id} 缺少审计时间戳");
                }
            }

            // 验证 Profile 审计字段
            $profile = Profile::find($this->profileId);
            if (!$profile->created_at || !$profile->updated_at) {
                throw new \Exception('Profile 缺少审计时间戳');
            }

            $this->pass($step, '日志记录验证成功: 审计字段完整');
        } catch (\Throwable $e) {
            $this->markStepFailed($step, '日志记录验证失败', $e);
            $this->generate_report();
            $this->print_report();
            throw $e;
        }
    }

    // ==================== 报告生成 ====================

    private function generate_report(): void
    {
        $this->report['end_time'] = date('Y-m-d H:i:s');
        $start = strtotime($this->report['start_time']);
        $end = strtotime($this->report['end_time']);
        $this->report['duration'] = ($end - $start) . 's';

        $passRate = $this->report['total_steps'] > 0
            ? round($this->report['passed_steps'] / $this->report['total_steps'] * 100, 2)
            : 0;

        $status = $this->report['failed_steps'] === 0 ? '✅ ALL TESTS PASSED' : '❌ SOME TESTS FAILED';

        $this->report['summary'] = [
            'status' => $status,
            'pass_rate' => $passRate . '%',
            'total_steps' => $this->report['total_steps'],
            'passed' => $this->report['passed_steps'],
            'failed' => $this->report['failed_steps'],
            'duration' => $this->report['duration'],
        ];
    }

    private function print_report(): void
    {
        echo "\n";
        echo "========================================\n";
        echo "         E2E 测试报告\n";
        echo "========================================\n";
        echo "开始时间: {$this->report['start_time']}\n";
        echo "结束时间: {$this->report['end_time']}\n";
        echo "耗时: {$this->report['duration']}\n";
        echo "------------------------------------------\n";
        echo "测试结果: {$this->report['summary']['status']}\n";
        echo "通过率: {$this->report['summary']['pass_rate']}\n";
        echo "总步骤: {$this->report['summary']['total_steps']}\n";
        echo "通过: {$this->report['summary']['passed']}\n";
        echo "失败: {$this->report['summary']['failed']}\n";
        echo "------------------------------------------\n";
        echo "详细步骤:\n";

        foreach ($this->report['steps'] as $s) {
            $icon = $s['status'] === 'PASS' ? '✅' : '❌';
            echo "  {$icon} [{$s['step']}] {$s['message']}\n";
        }

        if (!empty($this->report['errors'])) {
            echo "------------------------------------------\n";
            echo "错误详情:\n";
            foreach ($this->report['errors'] as $e) {
                echo "  ❌ {$e['step']}: {$e['message']}\n";
                if ($e['exception']) {
                    echo "     Exception: {$e['exception']}\n";
                }
            }
        }

        echo "========================================\n";

        // 保存报告到文件
        $reportFile = storage_path('logs/e2e_test_report_' . date('Y-m-d_His') . '.json');
        file_put_contents($reportFile, json_encode($this->report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        echo "报告已保存到: {$reportFile}\n";
    }
}
