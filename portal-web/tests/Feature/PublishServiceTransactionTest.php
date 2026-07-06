<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Publish\PublishService;
use App\Models\Node;
use App\Models\Profile;
use App\Models\ProfileVersion;
use App\Models\PublishTask;
use App\Models\TaskExecution;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 回归测试 2026-07-06 #15 #16 PublishService 事务+version 并发安全。
 *
 * 修复前：
 *   - 无事务包裹 → 半成品 ProfileVersion/PublishTask/TaskExecution 风险
 *   - version 用 max(id)+1 推算 → 并发时不同事务可能取到同一 version
 * 修复后：
 *   - 整体 DB::transaction()，失败回滚
 *   - version = 新插入行 ProfileVersion.id（自增），由 InnoDB 自增锁保证唯一
 *   - 目标节点 Node::online()->lockForUpdate() 行锁，避免与 ACK 流程并发修改
 */
final class PublishServiceTransactionTest extends TestCase
{
    use RefreshDatabase;

    private PublishService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PublishService();
    }

    private function makeOnlineNode(string $suffix): Node
    {
        return Node::create([
            'node_code'         => 'tx_publish_' . strtolower($suffix),
            'node_alias'        => 'Publish Test ' . $suffix,
            'install_status'    => 'installed',
            'last_heartbeat_at' => now(),
        ]);
    }

    private function makeProfile(string $profileId): Profile
    {
        $user = \App\Models\User::create([
            'email'    => $profileId . '@regression.local',
            'username' => $profileId,
            'password' => bcrypt('Password123!'),
        ]);
        $p = new Profile();
        $p->profile_id = $profileId;
        $p->user_id    = $user->uid;
        $p->name = 'regression ' . $profileId;
        $p->save();
        return $p;
    }

    public function test_version_increments_monotonically_across_publishes(): void
    {
        $this->makeOnlineNode('A');
        $this->makeProfile('aaaa11');

        $v1 = $this->service->recordPublish('aaaa11', 1, 'cs1', ['profile_id' => 'aaaa11', 'rules' => []]);
        $v2 = $this->service->recordPublish('aaaa11', 2, 'cs2', ['profile_id' => 'aaaa11', 'rules' => []]);
        $v3 = $this->service->recordPublish('aaaa11', 3, 'cs3', ['profile_id' => 'aaaa11', 'rules' => []]);

        $this->assertGreaterThan($v1['config_version'], $v2['config_version']);
        $this->assertGreaterThan($v2['config_version'], $v3['config_version']);
    }

    public function test_publish_creates_linked_profile_version_publish_task_and_executions(): void
    {
        $n1 = $this->makeOnlineNode('B1');
        $n2 = $this->makeOnlineNode('B2');
        $this->makeProfile('bbbb22');

        $result = $this->service->recordPublish('bbbb22', 1, 'cs_full', [
            'profile_id' => 'bbbb22',
            'rules'      => ['ads.example.com'],
        ]);

        $this->assertSame('queued', $result['status']);
        $this->assertSame('cs_full', $result['checksum']);

        $pv = ProfileVersion::find($result['config_version']);
        $this->assertNotNull($pv, 'ProfileVersion 必须按 config_version 创建');
        $this->assertSame($result['config_version'], (int) $pv->version, 'ProfileVersion.version 与 config_version 一致');

        $task = PublishTask::find($result['publish_id']);
        $this->assertNotNull($task, 'PublishTask 必须创建');
        $this->assertSame($pv->id, $task->profile_version_id);
        $this->assertSame(2, $task->target_node_count);
        $this->assertSame('queued', $task->status);

        $execs = TaskExecution::where('publish_task_id', $task->id)->get();
        $this->assertCount(2, $execs);
        $execNodeIds = $execs->pluck('node_id')->sort()->values()->all();
        $this->assertSame([$n1->id, $n2->id], $execNodeIds);
        $this->assertSame('pending', $execs->first()->status);
    }

    public function test_desired_config_version_updated_on_all_installed_nodes(): void
    {
        $online = $this->makeOnlineNode('C1');
        $offline = Node::create([
            'node_code'         => 'tx_publish_offline',
            'node_alias'        => 'Offline',
            'install_status'    => 'installed',
            'last_heartbeat_at' => now()->subMinutes(10), // 早已超时
        ]);
        $this->makeProfile('cccc33');

        $result = $this->service->recordPublish('cccc33', 1, 'cs_c', [
            'profile_id' => 'cccc33',
        ]);

        $this->assertSame($result['config_version'], (int) $online->fresh()->desired_config_version);
        $this->assertSame($result['config_version'], (int) $offline->fresh()->desired_config_version);
    }

    public function test_failed_publish_rolls_back_atomically(): void
    {
        $this->makeOnlineNode('D1');
        $this->makeProfile('dddd44');

        $beforePv = ProfileVersion::count();
        $beforeTask = PublishTask::count();
        $beforeExec = TaskExecution::count();

        $this->service->recordPublish('dddd44', 1, 'cs_d', [
            'profile_id' => 'dddd44',
        ]);

        $this->assertSame($beforePv + 1, ProfileVersion::count());
        $this->assertSame($beforeTask + 1, PublishTask::count());
        $this->assertSame($beforeExec + 1, TaskExecution::count());

        $badPayload = ['profile_id' => 'dddd44', 'bad' => "\xED\xA0\x80"];

        try {
            $this->service->recordPublish('dddd44', 2, 'cs_e', $badPayload);
            $this->fail('Expected RuntimeException for invalid UTF-8');
        } catch (\RuntimeException) {
            // 期望失败
        }

        $this->assertSame($beforePv + 1, ProfileVersion::count(), '失败时 ProfileVersion 必须回滚（不增加）');
        $this->assertSame($beforeTask + 1, PublishTask::count(), '失败时 PublishTask 必须回滚');
        $this->assertSame($beforeExec + 1, TaskExecution::count(), '失败时 TaskExecution 必须回滚');
    }
}
