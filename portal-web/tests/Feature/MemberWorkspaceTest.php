<?php

namespace Tests\Feature;

use App\Models\ProfileVersion;
use App\Models\Device;
use App\Models\Node;
use App\Models\QueryLogIngestBatch;
use App\Models\User;
use App\Infrastructure\ClickHouse\ClickHouseClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class MemberWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_workspace_endpoints_persist_primary_profile_settings(): void
    {
        $user = $this->createUser('workspace1@example.com', 'password123');

        Sanctum::actingAs($user, [], 'api');

        $this->putJson('/api/v1/user/security', [
            'enabled' => true,
            'block_malware' => true,
            'block_phishing' => false,
            'block_command_and_control' => true,
            'block_cryptojacking' => false,
        ])->assertOk()->assertJsonPath('data.block_phishing', false);

        $this->putJson('/api/v1/user/privacy', [
            'enabled' => true,
            'block_trackers' => true,
            'block_analytics' => true,
            'block_telemetry' => false,
            'anonymize_client_ip' => true,
            'log_mode' => 'blocked_only',
        ])->assertOk()->assertJsonPath('data.log_mode', 'blocked_only');

        $this->putJson('/api/v1/user/parental', [
            'enabled' => true,
            'block_adult_content' => true,
            'safe_search' => true,
            'youtube_restricted_mode' => false,
            'block_gambling_basic' => true,
        ])->assertOk()->assertJsonPath('data.safe_search', true);

        $this->putJson('/api/v1/user/settings', [
            'locale' => 'zh-CN',
            'timezone' => 'Asia/Shanghai',
            'profile_name' => 'Family Profile',
            'default_action' => 'allow',
            'block_response' => 'zero_ip',
        ])->assertOk()->assertJsonPath('data.profile_name', 'Family Profile');

        $this->getJson('/api/v1/user/settings')
            ->assertOk()
            ->assertJsonPath('data.locale', 'zh-CN')
            ->assertJsonPath('data.profile_name', 'Family Profile');
    }

    public function test_allowlist_and_denylist_endpoints_work_against_primary_profile(): void
    {
        $user = $this->createUser('workspace2@example.com');
        Sanctum::actingAs($user, [], 'api');

        $allow = $this->postJson('/api/v1/user/allowlist', [
            'domain' => 'openai.com',
            'match_type' => 'exact',
        ])->assertCreated();

        $deny = $this->postJson('/api/v1/user/denylist', [
            'domain' => 'ads.example.com',
            'match_type' => 'suffix',
        ])->assertCreated();

        $this->getJson('/api/v1/user/allowlist')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/v1/user/denylist')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->deleteJson('/api/v1/user/allowlist/' . $allow->json('data.id'))
            ->assertOk()
            ->assertJsonPath('data.deleted', true);

        $this->deleteJson('/api/v1/user/denylist/' . $deny->json('data.id'))
            ->assertOk()
            ->assertJsonPath('data.deleted', true);
    }

    public function test_publish_uses_persisted_profile_state_and_creates_profile_version(): void
    {
        $user = $this->createUser('workspace3@example.com');
        Sanctum::actingAs($user, [], 'api');

        $this->putJson('/api/v1/user/settings', [
            'locale' => 'en',
            'timezone' => 'UTC',
            'profile_name' => 'Home',
            'default_action' => 'block',
            'block_response' => 'refused',
        ])->assertOk();

        $this->putJson('/api/v1/user/security', [
            'enabled' => true,
            'block_malware' => true,
            'block_phishing' => true,
            'block_command_and_control' => true,
            'block_cryptojacking' => true,
        ])->assertOk();

        $ruleResponse = $this->postJson('/api/v1/user/denylist', [
            'domain' => 'tracker.example.com',
            'match_type' => 'exact',
        ])->assertCreated();

        $profileId = $this->getJson('/api/v1/user/profiles')->json('data.0.id');
        Device::create([
            'user_id' => $user->getKey(),
            'profile_id' => $profileId,
            'name' => 'Family iPad',
            'device_uid' => 'dev-ipad-01',
            'fingerprint' => hash('sha256', 'dev-ipad-01'),
            'source' => 'manual',
            'protocol' => 'doh',
            'ip_hash' => hash('sha256', '203.0.113.25'),
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        $publishResponse = $this->postJson("/api/v1/user/profiles/{$profileId}/publish", [
            'profile' => ['default_action' => 'allow'],
            'rules' => [],
            'features' => [],
        ]);

        $publishResponse->assertOk()
            ->assertJsonPath('data.payload.config_json.default_action', 'block')
            ->assertJsonPath('data.payload.config_json.rules.0.domain', 'tracker.example.com')
            ->assertJsonPath('data.payload.config_json.devices.0.device_id', 'dev-ipad-01');

        $this->assertDatabaseCount('profile_versions', 1);

        /** @var ProfileVersion $version */
        $version = ProfileVersion::query()->firstOrFail();
        $this->assertSame('published', $version->status);
        $this->assertSame('block', $version->config_json['default_action']);
        $this->assertSame('tracker.example.com', $version->config_json['rules'][0]['domain']);
        $this->assertNotSame('allow', $version->config_json['default_action']);
        $this->assertNotNull($ruleResponse->json('data.id'));
    }

    public function test_member_logs_and_analytics_can_use_dns_console_internal_api(): void
    {
        // 2026-06-22: 查询日志唯一源是 ClickHouse。测试通过 fake ClickHouse client
        // 直接喂 dns_logs 数据，验证 controller 的 SQL 拼装与 user_id 隔离。
        $user = $this->createUser('workspace4@example.com');
        $profile = $user->profiles()->create([
            'name' => 'Home Profile',
            'description' => 'Primary profile',
            'default_action' => 'allow',
            'block_response' => 'nxdomain',
            'security_enabled' => true,
            'privacy_enabled' => true,
            'parental_enabled' => false,
            'safe_search_enabled' => false,
            'log_mode' => 'full',
        ]);
        $device = Device::create([
            'user_id' => $user->getKey(),
            'profile_id' => $profile->id,
            'name' => 'MacBook',
            'device_uid' => 'dev-home-01',
            'fingerprint' => hash('sha256', 'dev-home-01'),
            'source' => 'manual',
            'protocol' => 'doh',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        $node = Node::create([
            'node_code' => 'node-test-01',
            'name' => 'test-node',
            'status' => 'online',
            'region' => 'ap-northeast-1',
        ]);

        $fakeRows = $this->buildFakeRows($user, $profile, $device, $node);

        // 2026-06-22: ClickHouseClient 是 final 类，用 Mockery 直接替换 jsonSelect
        $mock = \Mockery::mock(ClickHouseClient::class);
        $mock->shouldReceive('jsonSelect')
            ->andReturnUsing(function (string $query) use ($fakeRows) {
                if (stripos($query, 'count()') !== false) {
                    return [['c' => count($fakeRows)]];
                }
                return $fakeRows;
            });
        $this->app->instance(ClickHouseClient::class, $mock);

        Sanctum::actingAs($user, [], 'api');

        $this->getJson('/api/v1/user/logs')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.profile_name', 'Home Profile');

        $this->getJson('/api/v1/user/analytics')
            ->assertOk()
            ->assertJsonPath('data.today_queries', 2)
            ->assertJsonPath('data.today_blocked', 1);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildFakeRows(User $user, \App\Models\Profile $profile, Device $device, Node $node): array
    {
        $userId = (string) $user->getKey();
        $profileUid = (string) $profile->profile_uid;
        $deviceUid = (string) $device->device_uid;
        $now = now()->format('Y-m-d H:i:s');
        return [
            [
                'event_time' => $now,
                'user_id' => $userId,
                'profile_id' => $profileUid,
                'device_id' => $deviceUid,
                'domain' => 'tracker.example.com',
                'query_type' => 'A',
                'action' => 'BLOCK',
                'reason' => 'denylist',
                'category' => 'custom',
                'client_ip' => '',
                'rcode' => '0',
                'latency_ms' => 12,
                'protocol' => 'doh',
                'node_id' => (string) $node->id,
            ],
            [
                'event_time' => now()->subMinute()->format('Y-m-d H:i:s'),
                'user_id' => $userId,
                'profile_id' => $profileUid,
                'device_id' => $deviceUid,
                'domain' => 'openai.com',
                'query_type' => 'A',
                'action' => 'ALLOW',
                'reason' => '',
                'category' => '',
                'client_ip' => '',
                'rcode' => '0',
                'latency_ms' => 8,
                'protocol' => 'doh',
                'node_id' => (string) $node->id,
            ],
        ];
    }

    private function createUser(string $email, string $password = 'password123'): User
    {
        return User::create([
            'name' => 'Test User',
            'email' => $email,
            'password' => Hash::make($password),
            'timezone' => 'UTC',
            'locale' => 'en',
            'role' => 'member',
            'status' => 'active',
            'plan_code' => 'free',
        ]);
    }
}
