<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Profile;
use App\Models\ProfileRule;
use App\Models\RuleItem;
use App\Models\RuleSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 回归测试 2026-07-06 #18 删除接口路径。
 *
 * 修复前：前端调 `/admin/member-catalogs/rules/{id}` 等错误路径，route 404。
 * 修复后：BlacklistWhitelist 调 `/admin/member-rules/{id}` + `batch-destroy`，
 *         RuleItems 调 `/admin/rules/items/{id}` + `batch-destroy`，与 routes/v1/admin 实际注册一致。
 */
final class AdminDeleteRouteTest extends TestCase
{
    use RefreshDatabase;

    private string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();

        // 显式灌入 admin_permissions（避免依赖 AdminRbacSeeder，测试间解耦）
        $now = now();
        $perms = [
            // 顶层 admin group 中间件 `permission:admin.access` 必装
            ['code' => 'admin.access',      'resource' => 'admin',   'action' => 'access'],
            ['code' => 'admin.users.write', 'resource' => 'users',   'action' => 'write'],
            ['code' => 'admin.users.read',  'resource' => 'users',   'action' => 'read'],
            ['code' => 'admin.rules.write', 'resource' => 'rules',   'action' => 'write'],
            ['code' => 'admin.rules.read',  'resource' => 'rules',   'action' => 'read'],
        ];
        $permIds = [];
        foreach ($perms as $p) {
            $permIds[$p['code']] = \Illuminate\Support\Facades\DB::table('admin_permissions')->insertGetId([
                'code'        => $p['code'],
                'resource'    => $p['resource'],
                'action'      => $p['action'],
                'description' => $p['action'] . ' ' . $p['resource'],
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
        }

        $admin = Admin::create([
            'email'    => 'admin-regression@regression.local',
            'username' => 'admin_reg',
            'password' => bcrypt('Password123!'),
            'status'   => 'active', // PermissionService::isActiveAdmin 必须 status=active 才放行
        ]);

        $role = \App\Models\AdminRole::create([
            'code'        => 'super_admin',
            'name'        => 'Super Admin',
            'description' => 'regression test role',
            'is_builtin'  => true,
        ]);
        foreach ($permIds as $permId) {
            \Illuminate\Support\Facades\DB::table('admin_role_permissions')->insert([
                'admin_role_id'       => $role->id,
                'admin_permission_id' => $permId,
            ]);
        }
        \Illuminate\Support\Facades\DB::table('admin_user_roles')->insert([
            'admin_id'     => $admin->admin_id,
            'admin_role_id' => $role->id,
        ]);

        $this->adminToken = $admin->createToken('reg')->plainTextToken;
    }

    private function authHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->adminToken,
            'Accept'        => 'application/json',
        ];
    }

    private function makeProfile(): Profile
    {
        $user = \App\Models\User::create([
            'email'    => 'member-regression@regression.local',
            'username' => 'member_reg',
            'password' => bcrypt('Password123!'),
        ]);
        $p = new Profile();
        $p->profile_id = 'ee0001';
        $p->user_id    = $user->uid;
        $p->name       = 'reg';
        $p->save();
        return $p;
    }

    public function test_member_rule_delete_routes_match_controller(): void
    {
        $profile = $this->makeProfile();
        $rule = ProfileRule::create([
            'profile_id' => $profile->id,
            'list_type'  => 'blocklist',
            'match_type' => 'exact',
            'domain'     => 'ads.example.com',
            'action'     => 'block',
            'enabled'    => true,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->deleteJson('/api/v1/admin/member-rules/' . $rule->id);
        $response->assertStatus(200)
            ->assertJsonPath('data.deleted', true);
        $this->assertDatabaseMissing('profile_rules', ['id' => $rule->id]);
    }

    public function test_member_rule_batch_destroy_routes_match_controller(): void
    {
        $profile = $this->makeProfile();
        $a = ProfileRule::create([
            'profile_id' => $profile->id,
            'list_type'  => 'blocklist',
            'match_type' => 'exact',
            'domain'     => 'a.example.com',
            'action'     => 'block',
            'enabled'    => true,
        ]);
        $b = ProfileRule::create([
            'profile_id' => $profile->id,
            'list_type'  => 'blocklist',
            'match_type' => 'exact',
            'domain'     => 'b.example.com',
            'action'     => 'block',
            'enabled'    => true,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/admin/member-rules/batch-destroy', [
                'ids' => [(string) $a->id, (string) $b->id],
            ]);
        $response->assertStatus(200)
            ->assertJsonPath('data.deleted', 2);
        $this->assertDatabaseMissing('profile_rules', ['id' => $a->id]);
        $this->assertDatabaseMissing('profile_rules', ['id' => $b->id]);
    }

    public function test_rule_item_delete_routes_match_controller(): void
    {
        $src = RuleSource::create([
            'name'    => 'regression-src',
            'format'  => 'domains',
            'enabled' => true,
        ]);
        $item = RuleItem::create([
            'rule_source_id' => $src->id,
            'domain'         => 'evil.example.com',
            'action'         => 'block',
            'category'       => 'malware',
            'enabled'        => true,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->deleteJson('/api/v1/admin/rules/items/' . $item->id);
        $response->assertStatus(200)
            ->assertJsonPath('data.deleted', true);
        $this->assertDatabaseMissing('rule_items', ['id' => $item->id]);
    }

    public function test_rule_item_batch_destroy_routes_match_controller(): void
    {
        $src = RuleSource::create([
            'name'    => 'regression-src-2',
            'format'  => 'domains',
            'enabled' => true,
        ]);
        $a = RuleItem::create([
            'rule_source_id' => $src->id,
            'domain'         => 'a1.example.com',
            'action'         => 'block',
            'category'       => 'malware',
            'enabled'        => true,
        ]);
        $b = RuleItem::create([
            'rule_source_id' => $src->id,
            'domain'         => 'a2.example.com',
            'action'         => 'block',
            'category'       => 'phishing',
            'enabled'        => true,
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/admin/rules/items/batch-destroy', [
                'ids' => [$a->id, $b->id],
            ]);
        $response->assertStatus(200)
            ->assertJsonPath('data.deleted', 2);
        $this->assertDatabaseMissing('rule_items', ['id' => $a->id]);
        $this->assertDatabaseMissing('rule_items', ['id' => $b->id]);
    }
}
