<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use App\Models\User;
use App\Models\Admin;
use App\Models\Profile;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\TeamInvitation;
use App\Models\ProfileRule;
use App\Models\ApiKey;
use App\Models\DnsGeodns;
use App\Models\Node;
use App\Models\RuleSource;
use App\Models\PublishTask;
use App\Models\SystemConfig;
use App\Models\Device;
use Tests\TestCase;

class ApiTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected ?string $userToken;
    protected ?string $adminToken;
    protected ?int $userId;
    protected ?int $adminId;
    protected ?int $profileId;
    protected ?int $teamId;
    protected ?int $apiKeyId;
    protected ?int $nodeId;
    protected ?int $ruleId;
    protected ?int $geoId;
    protected ?int $publishId;
    protected ?int $auditId;
    protected ?int $deviceId;
    protected ?int $roleId;
    protected ?int $alertId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedDatabase();
        $this->loginUsers();
        $this->setupTestData();
    }

    protected function seedDatabase()
    {
        $user = User::create([
            'username' => 'test-user',
            'email' => 'user@example.com',
            'password' => '123456',
            'plan_code' => 'free',
            'locale' => 'zh-CN',
            'email_verified_at' => now(),
            'status' => 'active',
        ]);
        $this->userId = $user->getKey();

        $admin = Admin::create([
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => '123456',
            'status' => 'active',
            'is_super' => true,
            'locale' => 'zh-CN',
        ]);
        $this->adminId = $admin->getKey();

        SystemConfig::create([
            'config_key' => 'maintenance_mode',
            'config_value' => ['enabled' => false],
        ]);
    }

    protected function loginUsers()
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'user@example.com',
            'password' => '123456',
        ]);
        $this->userToken = $response->json('data.token');

        $response = $this->postJson('/api/v1/admin/login', [
            'email' => 'admin@example.com',
            'password' => '123456',
        ]);
        $this->adminToken = $response->json('data.token');
    }

    protected function setupTestData()
    {
        // 基础测试数据
        $profile = Profile::create([
            'user_id' => $this->userId,
            'name' => 'Test Profile',
            'description' => 'Test Description',
            'default_action' => 'allow',
            'block_response' => 'nxdomain',
        ]);
        $this->profileId = $profile->id;

        $team = Team::create([
            'name' => 'Test Team',
            'slug' => 'test-team-' . time(),
            'owner_id' => $this->userId,
        ]);
        $this->teamId = $team->id;

        TeamMember::create([
            'team_id' => $this->teamId,
            'user_id' => $this->userId,
            'role_key' => 'owner',
            'joined_at' => now(),
        ]);

        $apiKey = ApiKey::create([
            'user_id' => $this->userId,
            'name' => 'Test API Key',
            'key_prefix' => 'pk_test',
            'key_hash' => hash('sha256', 'pk_test_' . time()),
            'expires_at' => now()->addYear(),
        ]);
        $this->apiKeyId = $apiKey->id;
    }

    // ==================== Public API 测试 ====================

    public function test_public_login()
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'user@example.com',
            'password' => '123456',
        ]);
        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['token', 'user']]);
    }

    public function test_public_register()
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'New User',
            'email' => 'newuser_' . time() . '@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'timezone' => 'Asia/Shanghai',
            'locale' => 'zh-CN',
        ]);
        $response->assertStatus(200);
    }

    public function test_public_admin_login()
    {
        $response = $this->postJson('/api/v1/admin/login', [
            'email' => 'admin@example.com',
            'password' => '123456',
        ]);
        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['token', 'user']]);
    }

    // ==================== Member API - User Account Tests ====================

    public function test_01_member_me()
    {
        $this->callMemberApi('GET', '/api/v1/user/me', [], 200);
    }

    public function test_02_member_logout()
    {
        $this->callMemberApi('POST', '/api/v1/user/logout', [], 200);
    }

    public function test_03_member_settings_get()
    {
        $this->callMemberApi('GET', '/api/v1/user/settings', [], 200);
    }

    public function test_04_member_settings_update()
    {
        $this->callMemberApi('PUT', '/api/v1/user/settings', [
            'locale' => 'zh-CN',
            'timezone' => 'Asia/Shanghai',
            'profile_name' => 'Updated Profile',
            'default_action' => 'block',
            'block_response' => 'zero_ip',
        ], 200);
    }

    public function test_05_member_security_get()
    {
        $this->callMemberApi('GET', '/api/v1/user/security', [], 200);
    }

    public function test_06_member_security_update()
    {
        $this->callMemberApi('PUT', '/api/v1/user/security', [
            'enabled' => true,
            'block_malware' => true,
            'block_phishing' => true,
            'block_command_and_control' => true,
            'block_cryptojacking' => true,
            'threat_intel' => true,
            'ai_threat_detection' => false,
            'google_safe_browsing' => true,
            'dns_rebind' => true,
            'idn_homograph' => true,
            'typo_squatting' => true,
            'dga_protection' => true,
            'block_new_domains' => true,
            'block_dynamic_dns' => false,
            'block_parked_domains' => true,
            'block_tld' => false,
            'child_abuse' => true,
        ], 200);
    }

    public function test_06b_member_security_update_partial()
    {
        // Partial update - only changed fields
        $this->callMemberApi('PUT', '/api/v1/user/security', [
            'enabled' => false,
        ], 200);
    }

    public function test_07_member_privacy_get()
    {
        $this->callMemberApi('GET', '/api/v1/user/privacy', [], 200);
    }

    public function test_08_member_privacy_update()
    {
        $this->callMemberApi('PUT', '/api/v1/user/privacy', [
            'enabled' => true,
            'block_trackers' => true,
            'block_analytics' => true,
            'block_telemetry' => true,
            'anonymize_client_ip' => true,
            'allow_marketing_links' => false,
            'block_disguised_trackers' => true,
            'log_mode' => 'full',
            'blocklists' => [
                'allowlist_ids' => [],
                'denylist_ids' => [],
                'parental' => false,
            ],
            'deep_tracking_devices' => [],
        ], 200);
    }

    public function test_08b_member_privacy_update_partial()
    {
        // Partial update - only changed fields
        $this->callMemberApi('PUT', '/api/v1/user/privacy', [
            'enabled' => false,
            'log_mode' => 'disabled',
        ], 200);
    }

    public function test_09_member_parental_get()
    {
        $this->callMemberApi('GET', '/api/v1/user/parental', [], 200);
    }

    public function test_10_member_parental_update()
    {
        $this->callMemberApi('PUT', '/api/v1/user/parental', [
            'enabled' => true,
            'block_adult_content' => true,
            'block_gambling' => true,
            'block_gambling_basic' => true,
            'safe_search' => true,
            'force_safe_search' => false,
            'youtube_restricted_mode' => true,
            'force_youtube_restricted' => false,
            'time_limits' => [
                'weekday_start' => '08:00',
                'weekday_end' => '20:00',
                'weekend_start' => '00:00',
                'weekend_end' => '23:59',
                'per_day_minutes' => 120,
            ],
            'blocked_items' => ['chat.openai.com'],
            'blocked_categories' => ['adult', 'gambling'],
        ], 200);
    }

    public function test_10b_member_parental_update_partial()
    {
        // Partial update - only changed fields
        $this->callMemberApi('PUT', '/api/v1/user/parental', [
            'enabled' => true,
            'block_adult_content' => false,
        ], 200);
    }

    // Independent settings API endpoints
    public function test_10c_member_settings_security_update()
    {
        $this->callMemberApi('PUT', '/api/v1/user/settings/security', [
            'enabled' => true,
            'block_malware' => true,
            'block_phishing' => true,
        ], 200);
    }

    public function test_10d_member_settings_privacy_update()
    {
        $this->callMemberApi('PUT', '/api/v1/user/settings/privacy', [
            'enabled' => true,
            'block_trackers' => true,
            'log_mode' => 'full',
        ], 200);
    }

    public function test_10e_member_settings_parental_update()
    {
        $this->callMemberApi('PUT', '/api/v1/user/settings/parental', [
            'enabled' => true,
            'block_adult_content' => true,
            'safe_search' => true,
        ], 200);
    }

    public function test_11_member_password_update()
    {
        $this->callMemberApi('PUT', '/api/v1/user/password', [
            'current_password' => '123456',
            'new_password' => '1234567',
        ], 200);
    }

    public function test_14_member_membership()
    {
        $response = $this->callMemberApi('GET', '/api/v1/user/membership', [], 200);
        $response->assertJsonStructure(['data' => ['plan', 'plans', 'orders']]);
    }

    public function test_15_member_upgrade()
    {
        // upgrade 端点已移除 — 升级必须走订单 + Stripe 支付
        $this->callMemberApi('POST', '/api/v1/user/upgrade', [], 404);
    }

    public function test_12_member_analytics()
    {
        $this->callMemberApi('GET', '/api/v1/user/analytics', [], 200);
    }

    public function test_13_member_logs()
    {
        $this->callMemberApi('GET', '/api/v1/user/logs', [], 200);
    }

    // ==================== Member Center API Tests ====================

    public function test_20_member_center_overview()
    {
        $this->callMemberApi('GET', '/api/v1/user/member-center/overview', [], 200);
    }

    public function test_21_member_center_dns_endpoints()
    {
        $this->callMemberApi('GET', '/api/v1/user/member-center/dns-endpoints', [], 200);
    }

    public function test_22_member_center_devices()
    {
        $this->callMemberApi('GET', '/api/v1/user/member-center/devices', [], 200);
    }

    public function test_23_member_center_top_domains()
    {
        $this->callMemberApi('GET', '/api/v1/user/member-center/top-domains', [], 200);
    }

    // ==================== Allowlist API Tests ====================

    public function test_30_member_allowlist_list()
    {
        $this->callMemberApi('GET', '/api/v1/user/allowlist', [], 200);
    }

    public function test_31_member_allowlist_create()
    {
        $this->callMemberApi('POST', '/api/v1/user/allowlist', [
            'match_type' => 'exact',
            'domain' => 'allow.example.com',
        ], 201);
    }

    public function test_32_member_allowlist_batch_delete()
    {
        $this->callMemberApi('POST', '/api/v1/user/allowlist/batch-delete', [
            'ids' => ['fake-rule-1', 'fake-rule-2'],
        ], 200);
    }

    public function test_33_member_allowlist_update()
    {
        $rule = ProfileRule::create([
            'profile_id' => $this->profileId,
            'list_type' => 'allow',
            'match_type' => 'exact',
            'domain' => 'allow-update.example.com',
            'normalized_domain' => 'allow-update.example.com',
            'action' => 'allow',
            'enabled' => true,
            'created_by' => $this->userId,
        ]);
        $this->callMemberApi('PUT', "/api/v1/user/allowlist/{$rule->id}", [
            'domain' => 'allow-updated.example.com',
            'match_type' => 'exact',
            'enabled' => true,
        ], 200);
    }

    public function test_34_member_allowlist_delete()
    {
        $rule = ProfileRule::create([
            'profile_id' => $this->profileId,
            'list_type' => 'allow',
            'match_type' => 'exact',
            'domain' => 'allow-del.example.com',
            'normalized_domain' => 'allow-del.example.com',
            'action' => 'allow',
            'enabled' => true,
            'created_by' => $this->userId,
        ]);
        $this->callMemberApi('DELETE', "/api/v1/user/allowlist/{$rule->id}", [], 200);
    }

    // ==================== Denylist API Tests ====================

    public function test_40_member_denylist_list()
    {
        $this->callMemberApi('GET', '/api/v1/user/denylist', [], 200);
    }

    public function test_41_member_denylist_create()
    {
        $this->callMemberApi('POST', '/api/v1/user/denylist', [
            'match_type' => 'exact',
            'domain' => 'block.example.com',
        ], 201);
    }

    public function test_42_member_denylist_batch_delete()
    {
        $this->callMemberApi('POST', '/api/v1/user/denylist/batch-delete', [
            'ids' => ['fake-rule-1', 'fake-rule-2'],
        ], 200);
    }

    public function test_43_member_denylist_update()
    {
        $rule = ProfileRule::create([
            'profile_id' => $this->profileId,
            'list_type' => 'deny',
            'match_type' => 'exact',
            'domain' => 'deny-update.example.com',
            'normalized_domain' => 'deny-update.example.com',
            'action' => 'block',
            'enabled' => true,
            'created_by' => $this->userId,
        ]);
        $this->callMemberApi('PUT', "/api/v1/user/denylist/{$rule->id}", [
            'domain' => 'deny-updated.example.com',
            'match_type' => 'exact',
            'enabled' => true,
        ], 200);
    }

    public function test_44_member_denylist_delete()
    {
        $rule = ProfileRule::create([
            'profile_id' => $this->profileId,
            'list_type' => 'deny',
            'match_type' => 'exact',
            'domain' => 'deny-del.example.com',
            'normalized_domain' => 'deny-del.example.com',
            'action' => 'block',
            'enabled' => true,
            'created_by' => $this->userId,
        ]);
        $this->callMemberApi('DELETE', "/api/v1/user/denylist/{$rule->id}", [], 200);
    }

    // ==================== Profile API Tests ====================

    public function test_50_member_profiles_list()
    {
        $this->callMemberApi('GET', '/api/v1/user/profiles', [], 200);
    }

    public function test_51_member_profiles_create()
    {
        $this->callMemberApi('POST', '/api/v1/user/profiles', [
            'name' => 'New Profile',
            'default_action' => 'allow',
        ], 201);
    }

    public function test_52_member_profile_detail()
    {
        $this->callMemberApi('GET', "/api/v1/user/profiles/{$this->profileId}", [], 200);
    }

    public function test_53_member_profile_update()
    {
        $this->callMemberApi('PUT', "/api/v1/user/profiles/{$this->profileId}", [
            'name' => 'Updated Profile',
        ], 200);
    }

    public function test_54_member_profile_delete()
    {
        $profile = Profile::create([
            'user_id' => $this->userId,
            'name' => 'Temp Profile',
            'default_action' => 'allow',
        ]);
        $this->callMemberApi('DELETE', "/api/v1/user/profiles/{$profile->id}", [], 200);
    }

    public function test_55_member_profile_copy()
    {
        $this->callMemberApi('POST', "/api/v1/user/profiles/{$this->profileId}/copy", [
            'name' => 'Copied Profile',
        ], 201);
    }

    public function test_56_member_profile_batch_delete()
    {
        $this->callMemberApi('POST', '/api/v1/user/profiles/batch-delete', [
            'ids' => ['fake-profile-1', 'fake-profile-2'],
        ], 200);
    }

    // ==================== Profile Rules API Tests ====================

    public function test_60_member_profile_rules_list()
    {
        $this->callMemberApi('GET', "/api/v1/user/profiles/{$this->profileId}/rules", [], 200);
    }

    public function test_61_member_profile_rules_create()
    {
        $this->callMemberApi('POST', "/api/v1/user/profiles/{$this->profileId}/rules", [
            'list_type' => 'deny',
            'match_type' => 'exact',
            'domain' => 'newrule.example.com',
            'action' => 'block',
        ], 201);
    }

    public function test_62_member_profile_rules_batch_delete()
    {
        $this->callMemberApi('POST', "/api/v1/user/profiles/{$this->profileId}/rules/batch-delete", [
            'ids' => ['fake-rule-1', 'fake-rule-2'],
        ], 200);
    }

    public function test_63_member_profile_rule_update()
    {
        $rule = ProfileRule::create([
            'profile_id' => $this->profileId,
            'list_type' => 'deny',
            'match_type' => 'exact',
            'domain' => 'rule-update.example.com',
            'normalized_domain' => 'rule-update.example.com',
            'action' => 'block',
            'enabled' => true,
            'created_by' => $this->userId,
        ]);
        $this->callMemberApi('PUT', "/api/v1/user/profiles/{$this->profileId}/rules/{$rule->id}", [
            'domain' => 'rule-updated.example.com',
            'match_type' => 'exact',
            'list_type' => 'deny',
            'enabled' => true,
        ], 200);
    }

    public function test_64_member_profile_rule_delete()
    {
        $rule = ProfileRule::create([
            'profile_id' => $this->profileId,
            'list_type' => 'deny',
            'match_type' => 'exact',
            'domain' => 'rule-del.example.com',
            'normalized_domain' => 'rule-del.example.com',
            'action' => 'block',
            'enabled' => true,
            'created_by' => $this->userId,
        ]);
        $this->callMemberApi('DELETE', "/api/v1/user/profiles/{$this->profileId}/rules/{$rule->id}", [], 200);
    }

    // ==================== Profile Publish API Tests ====================

    public function test_70_member_profile_publish()
    {
        $this->callMemberApi('POST', "/api/v1/user/profiles/{$this->profileId}/publish", [
            'message' => 'Test publish',
        ], 200);
    }

    // ==================== Team API Tests ====================

    public function test_80_member_teams_list()
    {
        $this->callMemberApi('GET', '/api/v1/user/teams', [], 200);
    }

    public function test_81_member_teams_create()
    {
        $this->callMemberApi('POST', '/api/v1/user/teams', [
            'name' => 'New Team',
            'slug' => 'new-team-' . time(),
        ], 201);
    }

    public function test_82_member_team_detail()
    {
        $this->callMemberApi('GET', "/api/v1/user/teams/{$this->teamId}", [], 200);
    }

    public function test_83_member_team_update()
    {
        $this->callMemberApi('PUT', "/api/v1/user/teams/{$this->teamId}", [
            'name' => 'Updated Team',
        ], 200);
    }

    public function test_84_member_team_delete()
    {
        $team = Team::create([
            'name' => 'Temp Team',
            'slug' => 'temp-team-' . time(),
            'owner_id' => $this->userId,
        ]);
        TeamMember::create([
            'team_id' => $team->id,
            'user_id' => $this->userId,
            'role' => 'owner',
        ]);
        $this->callMemberApi('DELETE', "/api/v1/user/teams/{$team->id}", [], 200);
    }

    public function test_85_member_team_leave()
    {
        $newTeam = Team::create([
            'name' => 'Leave Test Team',
            'slug' => 'leave-team-' . time(),
            'owner_id' => $this->userId,
        ]);
        TeamMember::create([
            'team_id' => $newTeam->id,
            'user_id' => $this->userId,
            'role' => 'member',
        ]);
        $this->callMemberApi('POST', "/api/v1/user/teams/{$newTeam->id}/leave", [], 200);
    }

    public function test_86_member_team_transfer_ownership()
    {
        $newUser = User::create([
            'name' => 'New Member',
            'email' => 'newmember_' . time() . '@example.com',
            'password' => bcrypt('123456'),
            'role' => 'member',
            'plan_code' => 'free',
        ]);
        TeamMember::create([
            'team_id' => $this->teamId,
            'user_id' => $newUser->id,
            'role' => 'member',
        ]);
        $this->callMemberApi('POST', "/api/v1/user/teams/{$this->teamId}/transfer-ownership", [
            'new_owner_id' => $newUser->id,
        ], 200);
    }

    public function test_87_member_team_members_list()
    {
        $this->callMemberApi('GET', "/api/v1/user/teams/{$this->teamId}/members", [], 200);
    }

    public function test_88_member_team_member_update_role()
    {
        $newUser = User::create([
            'name' => 'Test Member 2',
            'email' => 'testmember2_' . time() . '@example.com',
            'password' => bcrypt('123456'),
            'role' => 'member',
            'plan_code' => 'free',
        ]);
        TeamMember::create([
            'team_id' => $this->teamId,
            'user_id' => $newUser->id,
            'role' => 'member',
        ]);
        $this->callMemberApi('PUT', "/api/v1/user/teams/{$this->teamId}/members/{$newUser->id}/role", [
            'role' => 'admin',
        ], 200);
    }

    public function test_89_member_team_member_remove()
    {
        $newUser = User::create([
            'name' => 'Test Member 3',
            'email' => 'testmember3_' . time() . '@example.com',
            'password' => bcrypt('123456'),
            'role' => 'member',
            'plan_code' => 'free',
        ]);
        TeamMember::create([
            'team_id' => $this->teamId,
            'user_id' => $newUser->id,
            'role' => 'member',
        ]);
        $this->callMemberApi('DELETE', "/api/v1/user/teams/{$this->teamId}/members/{$newUser->id}", [], 200);
    }

    public function test_90_member_team_switch()
    {
        $this->callMemberApi('POST', "/api/v1/user/teams/{$this->teamId}/switch", [], 200);
    }

    public function test_91_member_team_invitations_list()
    {
        $this->callMemberApi('GET', "/api/v1/user/teams/{$this->teamId}/invitations", [], 200);
    }

    public function test_92_member_team_invitations_create()
    {
        $this->callMemberApi('POST', "/api/v1/user/teams/{$this->teamId}/invitations", [
            'email' => 'invite_' . time() . '@example.com',
        ], 201);
    }

    public function test_93_member_team_invitation_cancel()
    {
        $invitation = TeamInvitation::create([
            'team_id' => $this->teamId,
            'email' => 'temp_invite_' . time() . '@example.com',
            'token_hash' => hash('sha256', 'test_token_' . time()),
            'invited_by' => $this->userId,
            'expires_at' => now()->addDay(),
        ]);
        $this->callMemberApi('DELETE', "/api/v1/user/teams/{$this->teamId}/invitations/{$invitation->id}", [], 200);
    }

    public function test_94_member_team_batch_cancel_invitations()
    {
        $this->callMemberApi('POST', "/api/v1/user/teams/{$this->teamId}/invitations/batch-cancel", [
            'ids' => ['fake-inv-1', 'fake-inv-2'],
        ], 200);
    }

    public function test_95_member_pending_invitations()
    {
        $this->callMemberApi('GET', '/api/v1/user/teams/invitations/pending', [], 200);
    }

    public function test_96_member_accept_invitation()
    {
        $invitee = User::create([
            'name' => 'Invitee',
            'email' => 'invitee_' . time() . '@example.com',
            'password' => bcrypt('123456'),
            'role' => 'member',
            'plan_code' => 'free',
        ]);
        $token = 'accept_tok_' . time();
        TeamInvitation::create([
            'team_id' => $this->teamId,
            'email' => $invitee->email,
            'token_hash' => \Illuminate\Support\Facades\Hash::make($token),
            'invited_by' => $this->userId,
            'expires_at' => now()->addDay(),
        ]);
        $inviteeToken = $invitee->createToken('invitee-token')->plainTextToken;
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $inviteeToken])
            ->postJson('/api/v1/user/teams/accept-invitation', [
                'token' => $token,
            ]);
        $response->assertStatus(200);
    }

    // ==================== API Key API Tests ====================

    public function test_100_member_api_keys_list()
    {
        $this->callMemberApi('GET', '/api/v1/user/api-keys', [], 200);
    }

    public function test_101_member_api_keys_create()
    {
        $this->callMemberApi('POST', '/api/v1/user/api-keys', [
            'name' => 'New API Key',
        ], 201);
    }

    public function test_102_member_api_key_delete()
    {
        $key = ApiKey::create([
            'user_id' => $this->userId,
            'name' => 'Test API Key Delete',
            'key_prefix' => 'pk_del_' . time(),
            'key_hash' => hash('sha256', 'pk_del_' . time()),
            'expires_at' => now()->addYear(),
        ]);
        $this->callMemberApi('DELETE', "/api/v1/user/api-keys/{$key->id}", [], 200);
    }

    // ==================== Admin API Tests ====================

    public function test_200_admin_overview()
    {
        $this->callAdminApi('GET', '/api/v1/admin/overview', [], 200);
    }

    public function test_201_admin_billing_stats()
    {
        $this->callAdminApi('GET', '/api/v1/admin/billing-stats', [], 200);
    }

    // ==================== Admin Users API Tests ====================

    public function test_210_admin_users_list()
    {
        $this->callAdminApi('GET', '/api/v1/admin/users', [], 200);
    }

    public function test_211_admin_users_create()
    {
        $this->callAdminApi('POST', '/api/v1/admin/users', [
            'name' => 'New Admin User',
            'email' => 'newadminuser_' . time() . '@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => 'member',
            'plan_code' => 'free',
        ], 201);
    }

    public function test_212_admin_user_detail()
    {
        $this->callAdminApi('GET', "/api/v1/admin/users/{$this->userId}", [], 200);
    }

    public function test_213_admin_user_update()
    {
        $this->callAdminApi('PUT', "/api/v1/admin/users/{$this->userId}", [
            'name' => 'Updated User Name',
        ], 200);
    }

    public function test_214_admin_user_delete()
    {
        $user = User::create([
            'name' => 'Temp Admin User',
            'email' => 'tempadminuser_' . time() . '@example.com',
            'password' => bcrypt('123456'),
            'role' => 'member',
            'plan_code' => 'free',
        ]);
        $this->callAdminApi('DELETE', "/api/v1/admin/users/{$user->id}", [], 200);
    }

    public function test_215_admin_user_disable()
    {
        $this->callAdminApi('POST', "/api/v1/admin/users/{$this->userId}/disable", [], 200);
    }

    public function test_216_admin_user_enable()
    {
        $this->callAdminApi('POST', "/api/v1/admin/users/{$this->userId}/enable", [], 200);
    }

    // ==================== Admin Nodes API Tests ====================

    public function test_220_admin_nodes_list()
    {
        $this->callAdminApi('GET', '/api/v1/admin/nodes', [], 200);
    }

    public function test_221_admin_nodes_create()
    {
        $this->callAdminApi('POST', '/api/v1/admin/nodes', [
            'node_name' => 'New Node ' . time(),
            'region' => 'us-west',
        ], 201);
    }

    public function test_222_admin_node_detail()
    {
        $node = Node::create([
            'id' => 'nd_test_' . time(),
            'node_name' => 'Test Node',
            'region' => 'asia-east',
            'public_ipv4' => '192.168.1.1',
            'status' => 'online',
        ]);
        $this->callAdminApi('GET', "/api/v1/admin/nodes/{$node->id}", [], 200);
    }

    public function test_223_admin_node_update()
    {
        $node = Node::create([
            'id' => 'nd_test_' . time(),
            'node_name' => 'Test Node',
            'region' => 'asia-east',
            'public_ipv4' => '192.168.1.1',
            'status' => 'online',
        ]);
        $this->callAdminApi('PUT', "/api/v1/admin/nodes/{$node->id}", [
            'node_name' => 'Updated Node',
        ], 200);
    }

    public function test_224_admin_node_delete()
    {
        $node = Node::create([
            'id' => 'nd_del_' . time(),
            'node_name' => 'Delete Node',
            'region' => 'eu-west',
            'public_ipv4' => '10.0.1.1',
            'status' => 'online',
        ]);
        $this->callAdminApi('DELETE', "/api/v1/admin/nodes/{$node->id}", [], 200);
    }

    public function test_225_admin_node_batch_destroy()
    {
        $node = Node::create([
            'id' => 'nd_batch_' . time(),
            'node_name' => 'Batch Node',
            'region' => 'eu-west',
            'public_ipv4' => '10.0.2.2',
            'status' => 'online',
        ]);
        $this->callAdminApi('POST', '/api/v1/admin/nodes/batch-destroy', [
            'ids' => [$node->id],
        ], 200);
    }

    public function test_226_admin_node_enable()
    {
        $node = Node::create([
            'id' => 'nd_test_' . time(),
            'node_name' => 'Test Node',
            'region' => 'asia-east',
            'public_ipv4' => '192.168.1.1',
            'status' => 'online',
        ]);
        $this->callAdminApi('POST', "/api/v1/admin/nodes/{$node->id}/enable", [], 200);
    }

    public function test_227_admin_node_disable()
    {
        $node = Node::create([
            'id' => 'nd_test_' . time(),
            'node_name' => 'Test Node',
            'region' => 'asia-east',
            'public_ipv4' => '192.168.1.1',
            'status' => 'online',
        ]);
        $this->callAdminApi('POST', "/api/v1/admin/nodes/{$node->id}/disable", [], 200);
    }

    public function test_228_admin_node_issue_token()
    {
        $node = Node::create([
            'id' => 'nd_test_' . time(),
            'node_name' => 'Test Node',
            'region' => 'asia-east',
            'public_ipv4' => '192.168.1.1',
            'status' => 'online',
        ]);
        $this->callAdminApi('POST', "/api/v1/admin/nodes/{$node->id}/tokens", [], 201);
    }

    public function test_229_admin_node_revoke_token()
    {
        $node = Node::create([
            'id' => 'nd_revoke_' . time(),
            'node_name' => 'Revoke Token Node',
            'region' => 'asia-east',
            'public_ipv4' => '192.168.2.2',
            'status' => 'online',
        ]);
        $token = \App\Models\NodeToken::create([
            'id' => 'ntk_revoke_' . time(),
            'node_id' => $node->id,
            'token_hash' => hash('sha256', 'token_' . time()),
            'hmac_key_hash' => hash('sha256', 'hmac_' . time()),
            'name' => 'revoke-test',
            'created_at' => now(),
        ]);
        $this->callAdminApi('POST', "/api/v1/admin/nodes/{$node->id}/tokens/{$token->id}/revoke", [], 200);
    }

    // ==================== Admin Audit Logs API Tests ====================

    public function test_230_admin_audit_logs()
    {
        $this->callAdminApi('GET', '/api/v1/admin/audit-logs', [], 200);
    }

    public function test_231_admin_console_audit_logs()
    {
        $this->callAdminApi('GET', '/api/v1/admin/console/audit-logs', [], 200);
    }

    public function test_232_admin_console_audit_logs_export()
    {
        $this->callAdminApi('GET', '/api/v1/admin/console/audit-logs/export', [], 200);
    }

    public function test_233_admin_console_audit_logs_batch_destroy()
    {
        $this->callAdminApi('POST', '/api/v1/admin/console/audit-logs/batch-destroy', [
            'ids' => ['fake-id-1', 'fake-id-2'],
        ], 200);
    }

    // ==================== Admin Alerts API Tests ====================

    public function test_240_admin_alerts_list()
    {
        $this->callAdminApi('GET', '/api/v1/admin/alerts', [], 200);
    }

    public function test_241_admin_alert_acknowledge()
    {
        $this->callAdminApi('POST', '/api/v1/admin/alerts/fake-alert-1/acknowledge', [], 200);
    }

    // ==================== Admin Teams API Tests ====================

    public function test_250_admin_teams_list()
    {
        $this->callAdminApi('GET', '/api/v1/admin/teams', [], 200);
    }

    public function test_251_admin_team_detail()
    {
        $this->callAdminApi('GET', "/api/v1/admin/teams/{$this->teamId}", [], 200);
    }

    public function test_252_admin_team_members()
    {
        $this->callAdminApi('GET', "/api/v1/admin/teams/{$this->teamId}/members", [], 200);
    }

    public function test_253_admin_team_disable()
    {
        $this->callAdminApi('POST', "/api/v1/admin/teams/{$this->teamId}/disable", [], 200);
    }

    public function test_254_admin_team_enable()
    {
        $this->callAdminApi('POST', "/api/v1/admin/teams/{$this->teamId}/enable", [], 200);
    }

    // ==================== Admin Devices API Tests ====================

    public function test_260_admin_devices_list()
    {
        $this->callAdminApi('GET', '/api/v1/admin/devices', [], 200);
    }

    public function test_261_admin_device_detail()
    {
        $this->callAdminApi('GET', "/api/v1/admin/devices/{$this->deviceId}", [], 200);
    }

    public function test_262_admin_device_delete()
    {
        $deviceUid = 'dev_test_' . time();
        $device = Device::create([
            'user_id' => $this->userId,
            'profile_id' => $this->profileId,
            'name' => 'Test Device ' . time(),
            'device_uid' => $deviceUid,
            'fingerprint' => hash('sha256', $deviceUid),
            'source' => 'manual',
            'protocol' => 'doh',
            'ip_hash' => hash('sha256', '127.0.0.1'),
            'last_seen_at' => now(),
        ]);
        $this->callAdminApi('DELETE', "/api/v1/admin/devices/{$device->id}", [], 200);
    }

    // ==================== Admin Billing API Tests ====================

    public function test_270_admin_billing_balance()
    {
        $this->callAdminApi('GET', "/api/v1/admin/billing/balance/{$this->userId}", [], 200);
    }

    public function test_271_admin_billing_charge()
    {
        $this->callAdminApi('POST', '/api/v1/admin/billing/charge', [
            'user_id' => $this->userId,
            'amount_minor' => 1000,
            'description' => 'Test charge',
        ], 201);
    }

    public function test_272_admin_billing_refund()
    {
        $this->callAdminApi('POST', '/api/v1/admin/billing/charge', [
            'user_id' => $this->userId,
            'amount_minor' => 1000,
            'description' => 'Seed balance for refund',
        ], 201);

        $this->callAdminApi('POST', '/api/v1/admin/billing/refund', [
            'user_id' => $this->userId,
            'amount_minor' => 500,
            'description' => 'Test refund',
        ], 201);
    }

    public function test_273_admin_billing_bills()
    {
        $this->callAdminApi('GET', '/api/v1/admin/billing/bills', [], 200);
    }

    public function test_274_admin_billing_export()
    {
        $this->callAdminApi('GET', '/api/v1/admin/billing/export', [], 200);
    }

    public function test_275_admin_plans_list()
    {
        $response = $this->callAdminApi('GET', '/api/v1/admin/plans', [], 200);
        $response->assertJsonStructure(['data' => [['code', 'name', 'prices']]]);
    }

    public function test_276_admin_plans_create()
    {
        $this->callAdminApi('POST', '/api/v1/admin/plans', [
            'code' => 'starter',
            'name' => 'Starter',
            'description' => 'Starter plan',
            'status' => 'active',
            'sort_order' => 40,
            'is_featured' => false,
            'features' => ['Basic support'],
            'limits' => ['monthly_queries' => 500000],
            'prices' => [
                ['billing_cycle' => 'monthly', 'currency' => 'USD', 'amount_minor' => 199, 'status' => 'active'],
            ],
        ], 201);
    }

    // ==================== Admin Query Logs API Tests ====================

    public function test_280_admin_query_logs()
    {
        $this->callAdminApi('GET', '/api/v1/admin/query-logs', [], 200);
    }

    // ==================== Admin System Config API Tests ====================

    public function test_290_admin_system_config_get()
    {
        $this->callAdminApi('GET', '/api/v1/admin/system-config', [], 200);
    }

    public function test_291_admin_system_config_update()
    {
        $this->callAdminApi('PUT', '/api/v1/admin/system-config', [
            'configs' => ['maintenance_mode' => 'false'],
        ], 200);
    }

    public function test_292_admin_system_config_update_accepts_direct_payload()
    {
        $this->callAdminApi('PUT', '/api/v1/admin/system-config', [
            'dns' => ['default_upstream' => '8.8.8.8:53'],
        ], 200);
    }

    // ==================== Admin GeoDNS API Tests ====================

    public function test_300_admin_geo_dns_list()
    {
        $this->callAdminApi('GET', '/api/v1/admin/geo-dns', [], 200);
    }

    public function test_301_admin_geo_dns_create()
    {
        $this->callAdminApi('POST', '/api/v1/admin/geo-dns', [
            'country' => 'US',
            'region' => 'us-east',
            'node_alias' => 'US East Geo',
            'public_ipv4' => '1.2.3.4',
        ], 201);
    }

    public function test_302_admin_geo_dns_detail()
    {
        $node = DnsGeodns::create([
            'node_code' => 'nd_geo2_' . time(),
            'node_alias' => 'Geo Node 2',
            'region' => 'geodns-us-east',
            'public_ipv4' => '1.2.3.4',
            'install_status' => 'installed',
            'current_config_version' => 1,
        ]);
        $this->callAdminApi('GET', "/api/v1/admin/geo-dns/{$node->id}", [], 200);
    }

    public function test_303_admin_geo_dns_update()
    {
        $node = DnsGeodns::create([
            'node_code' => 'nd_geo3_' . time(),
            'node_alias' => 'Geo Node 3',
            'region' => 'geodns-us-east',
            'public_ipv4' => '1.2.3.4',
            'install_status' => 'installed',
            'current_config_version' => 1,
        ]);
        $this->callAdminApi('PUT', "/api/v1/admin/geo-dns/{$node->id}", [
            'node_alias' => 'Updated Geo',
        ], 200);
    }

    public function test_304_admin_geo_dns_delete()
    {
        $node = DnsGeodns::create([
            'node_code' => 'nd_geo4_' . time(),
            'node_alias' => 'Geo Node 4',
            'region' => 'geodns-us-east',
            'public_ipv4' => '1.2.3.4',
            'install_status' => 'installed',
            'current_config_version' => 1,
        ]);
        $this->callAdminApi('DELETE', "/api/v1/admin/geo-dns/{$node->id}", [], 200);
    }

    public function test_305_admin_geo_dns_batch_destroy()
    {
        $node1 = DnsGeodns::create([
            'node_code' => 'nd_geo5_' . time(),
            'node_alias' => 'Geo Node 5',
            'region' => 'geodns-us-east',
            'public_ipv4' => '1.2.3.4',
            'install_status' => 'installed',
            'current_config_version' => 1,
        ]);
        $node2 = DnsGeodns::create([
            'node_code' => 'nd_geo6_' . time(),
            'node_alias' => 'Geo Node 6',
            'region' => 'geodns-us-west',
            'public_ipv4' => '5.6.7.8',
            'install_status' => 'installed',
            'current_config_version' => 1,
        ]);
        $this->callAdminApi('POST', '/api/v1/admin/geo-dns/batch-destroy', [
            'ids' => [$node1->id, $node2->id],
        ], 200);
    }

    // ==================== Admin Rules API Tests ====================

    public function test_310_admin_rules_list()
    {
        $this->callAdminApi('GET', '/api/v1/admin/rules', [], 200);
    }

    public function test_311_admin_rules_create()
    {
        $this->callAdminApi('POST', '/api/v1/admin/rules', [
            'name' => 'New Global Rule ' . time(),
            'type' => 'domain_list',
            'url' => 'https://example.com/list_' . time() . '.txt',
        ], 201);
    }

    public function test_312_admin_rule_detail()
    {
        $rule = RuleSource::create([
            'name' => 'Detail Rule',
            'type' => 'domain_list',
            'url' => 'https://example.com/detail_' . time() . '.txt',
            'enabled' => true,
        ]);
        $this->callAdminApi('GET', "/api/v1/admin/rules/{$rule->id}", [], 200);
    }

    public function test_313_admin_rule_update()
    {
        $rule = RuleSource::create([
            'name' => 'Update Rule',
            'type' => 'domain_list',
            'url' => 'https://example.com/update_' . time() . '.txt',
            'enabled' => true,
        ]);
        $this->callAdminApi('PUT', "/api/v1/admin/rules/{$rule->id}", [
            'enabled' => false,
        ], 200);
    }

    public function test_314_admin_rule_delete()
    {
        $rule = RuleSource::create([
            'name' => 'Temp Rule',
            'type' => 'domain_list',
            'url' => 'https://example.com/temp_' . time() . '.txt',
            'enabled' => true,
        ]);
        $this->callAdminApi('DELETE', "/api/v1/admin/rules/{$rule->id}", [], 200);
    }

    public function test_315_admin_rule_sync()
    {
        $rule = RuleSource::create([
            'name' => 'Sync Rule',
            'type' => 'domain_list',
            'url' => 'https://example.com/sync_' . time() . '.txt',
            'enabled' => true,
        ]);
        $this->callAdminApi('POST', "/api/v1/admin/rules/{$rule->id}/sync", [], 200);
    }

    public function test_316_admin_rules_batch_destroy()
    {
        $this->callAdminApi('POST', '/api/v1/admin/rules/batch-destroy', [
            'ids' => ['fake-rule-1', 'fake-rule-2'],
        ], 200);
    }

    // ==================== Admin Publishes API Tests ====================

    public function test_320_admin_publishes_list()
    {
        $this->callAdminApi('GET', '/api/v1/admin/publishes', [], 200);
    }

    public function test_321_admin_publishes_create()
    {
        $configVersion = \App\Models\ConfigVersion::create([
            'id' => 'cfg_test_' . time(),
            'version' => time(),
            'profile_id' => $this->profileId,
            'profile_version' => 1,
            'user_id' => $this->userId,
            'checksum' => 'sha256:test' . time(),
            'config_json' => ['key' => 'value'],
            'generated_at' => now(),
        ]);
        $this->callAdminApi('POST', '/api/v1/admin/publishes', [
            'message' => 'Test publish ' . time(),
            'config_version_id' => $configVersion->id,
            'profile_id' => $this->profileId,
        ], 201);
    }

    public function test_322_admin_publish_retry()
    {
        $configVersion = \App\Models\ConfigVersion::create([
            'id' => 'cfg_retry_' . time(),
            'version' => time(),
            'profile_id' => $this->profileId,
            'profile_version' => 1,
            'user_id' => $this->userId,
            'checksum' => 'sha256:retry' . time(),
            'config_json' => ['key' => 'value'],
            'generated_at' => now(),
        ]);
        $task = PublishTask::create([
            'config_version_id' => $configVersion->id,
            'profile_id' => $this->profileId,
            'status' => 'failed',
            'target_scope' => 'all_nodes',
            'message' => 'Test',
            'queued_at' => now(),
        ]);
        $this->callAdminApi('POST', "/api/v1/admin/publishes/{$task->id}/retry", [], 200);
    }

    public function test_323_admin_publish_cancel()
    {
        $configVersion = \App\Models\ConfigVersion::create([
            'id' => 'cfg_cancel_' . time(),
            'version' => time(),
            'profile_id' => $this->profileId,
            'profile_version' => 1,
            'user_id' => $this->userId,
            'checksum' => 'sha256:cancel' . time(),
            'config_json' => ['key' => 'value'],
            'generated_at' => now(),
        ]);
        $task = PublishTask::create([
            'config_version_id' => $configVersion->id,
            'profile_id' => $this->profileId,
            'status' => 'queued',
            'target_scope' => 'all_nodes',
            'message' => 'Test',
            'queued_at' => now(),
        ]);
        $this->callAdminApi('POST', "/api/v1/admin/publishes/{$task->id}/cancel", [], 200);
    }

    public function test_324_admin_publishes_batch_retry()
    {
        $configVersion = \App\Models\ConfigVersion::create([
            'id' => 'cfg_batch_retry_' . time(),
            'version' => time(),
            'profile_id' => $this->profileId,
            'profile_version' => 1,
            'user_id' => $this->userId,
            'checksum' => 'sha256:batch_retry' . time(),
            'config_json' => ['key' => 'value'],
            'generated_at' => now(),
        ]);
        $task = PublishTask::create([
            'config_version_id' => $configVersion->id,
            'profile_id' => $this->profileId,
            'status' => 'failed',
            'target_scope' => 'all_nodes',
            'message' => 'Test',
            'queued_at' => now(),
        ]);
        $this->callAdminApi('POST', '/api/v1/admin/publishes/batch-retry', [
            'ids' => [$task->id],
        ], 200);
    }

    public function test_325_admin_publishes_batch_cancel()
    {
        $configVersion = \App\Models\ConfigVersion::create([
            'id' => 'cfg_batch_cancel_' . time(),
            'version' => time(),
            'profile_id' => $this->profileId,
            'profile_version' => 1,
            'user_id' => $this->userId,
            'checksum' => 'sha256:batch_cancel' . time(),
            'config_json' => ['key' => 'value'],
            'generated_at' => now(),
        ]);
        $task = PublishTask::create([
            'config_version_id' => $configVersion->id,
            'profile_id' => $this->profileId,
            'status' => 'queued',
            'target_scope' => 'all_nodes',
            'message' => 'Test',
            'queued_at' => now(),
        ]);
        $this->callAdminApi('POST', '/api/v1/admin/publishes/batch-cancel', [
            'ids' => [$task->id],
        ], 200);
    }

    public function test_326_admin_publishes_cleanup_completed()
    {
        $this->callAdminApi('POST', '/api/v1/admin/publishes/cleanup-completed', [
            'older_than_days' => 30,
        ], 200);
    }

    // ==================== Internal API Tests ====================

    public function test_400_internal_profile_publishes()
    {
        $this->callInternalApi('POST', '/api/v1/internal/profile-publishes', [
            'profile_id' => $this->profileId,
            'profile_version' => 1,
            'checksum' => 'sha256:test123',
            'config_json' => ['key' => 'value'],
        ], 200);
    }

    public function test_401_internal_geodns_health_view()
    {
        $this->callInternalApi('GET', '/api/v1/internal/geodns/health-view', [], 200);
    }

    public function test_402_internal_query_logs()
    {
        $this->callInternalApi('GET', '/api/v1/internal/query-logs', [
            'user_id' => $this->userId,
        ], 200);
    }

    public function test_403_internal_query_analytics()
    {
        $this->callInternalApi('GET', '/api/v1/internal/query-analytics', [
            'user_id' => $this->userId,
        ], 200);
    }

    // ==================== Auth Failure Tests ====================

    public function test_500_auth_failure_no_token()
    {
        $response = $this->getJson('/api/v1/user/me');
        $response->assertStatus(401);

        $response = $this->getJson('/api/v1/admin/users');
        $response->assertStatus(401);
    }

    public function test_501_member_cannot_access_admin()
    {
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->userToken])
            ->getJson('/api/v1/admin/users');
        $response->assertStatus(401);
    }

    public function test_502_admin_cannot_access_member_me()
    {
        // Admin token uses 'admins' guard, cannot access member endpoints protected by 'users' guard.
        $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
            ->getJson('/api/v1/user/me');
        $response->assertStatus(401);
    }

    // ==================== Admin Finance & RBAC Tests ====================

    public function test_600_admin_finance_balances()
    {
        $this->callAdminApi('GET', '/api/v1/admin/finance/balances', [], 200);
    }

    public function test_601_admin_finance_recharges()
    {
        $this->callAdminApi('GET', '/api/v1/admin/finance/recharges', [], 200);
    }

    public function test_602_admin_finance_bills()
    {
        $this->callAdminApi('GET', '/api/v1/admin/finance/bills', [], 200);
    }

    public function test_603_admin_finance_refunds()
    {
        $this->callAdminApi('GET', '/api/v1/admin/finance/refunds', [], 200);
    }

    public function test_604_admin_rbac_roles()
    {
        $this->callAdminApi('GET', '/api/v1/admin/rbac/roles', [], 200);
    }

    public function test_605_admin_rbac_permissions()
    {
        $this->callAdminApi('GET', '/api/v1/admin/rbac/permissions', [], 200);
    }

    public function test_606_admin_rbac_admins()
    {
        $response = $this->callAdminApi('GET', '/api/v1/admin/rbac/admins', [], 200);
        $response->assertJsonStructure(['data' => [['id', 'username', 'email', 'role_list']]]);
    }

    public function test_607_admin_devices_batch_destroy()
    {
        $this->callAdminApi('POST', '/api/v1/admin/devices/batch-destroy', ['ids' => ['fake-id-1']], 200);
    }

    public function test_608_admin_alerts_batch_destroy()
    {
        $this->callAdminApi('POST', '/api/v1/admin/alerts/batch-destroy', ['ids' => ['fake-id']], 200);
    }

    public function test_609_admin_console_audit_logs()
    {
        $this->callAdminApi('GET', '/api/v1/admin/console/audit-logs', [], 200);
    }

    // ==================== Helper Methods ====================

    protected function callMemberApi(string $method, string $url, array $data = [], int $status = 200)
    {
        return $this->callApiWithToken($method, $url, $this->userToken, $data, $status);
    }

    protected function callAdminApi(string $method, string $url, array $data = [], int $status = 200)
    {
        return $this->callApiWithToken($method, $url, $this->adminToken, $data, $status);
    }

    protected function callInternalApi(string $method, string $url, array $data = [], int $status = 200)
    {
        $headers = ['Authorization' => 'Internal internal-local-token'];
        $method = strtoupper($method);
        switch ($method) {
            case 'GET':
                if (!empty($data)) {
                    $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($data);
                }
                $response = $this->withHeaders($headers)->getJson($url);
                break;
            case 'POST':
                $response = $this->withHeaders($headers)->postJson($url, $data);
                break;
            case 'PUT':
                $response = $this->withHeaders($headers)->putJson($url, $data);
                break;
            case 'DELETE':
                $response = $this->withHeaders($headers)->deleteJson($url, $data);
                break;
            default:
                $response = $this->withHeaders($headers)->getJson($url);
        }
        $response->assertStatus($status);
        return $response;
    }

    protected function callApiWithToken(string $method, string $url, ?string $token, array $data = [], int $status = 200)
    {
        $method = strtoupper($method);
        
        if ($token && $token !== 'internal_token') {
            $headers = ['Authorization' => 'Bearer ' . $token];
        } else {
            $headers = [];
        }

        switch ($method) {
            case 'GET':
                $response = $this->withHeaders($headers)->getJson($url);
                break;
            case 'POST':
                $response = $this->withHeaders($headers)->postJson($url, $data);
                break;
            case 'PUT':
                $response = $this->withHeaders($headers)->putJson($url, $data);
                break;
            case 'DELETE':
                $response = $this->withHeaders($headers)->deleteJson($url, $data);
                break;
            default:
                $response = $this->withHeaders($headers)->getJson($url);
        }

        $response->assertStatus($status);
        return $response;
    }
}
