<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Node\ConfigAcknowledgementService;
use App\Domain\ConfigVersion\ConfigAckService;
use App\Models\Node;
use App\Models\PublishTask;
use App\Models\TaskExecution;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * 回归测试 2026-07-06 #14 PublishTask 状态机。
 *
 * 修复前：仅判断 failed_count == 0 就标 succeeded，导致 pending 节点未完成时显示"成功"。
 * 修复后：按 target/applied/failed/pending 四要素计算状态：
 *   - pending > 0                                    → running
 *   - applied == target (>0) && failed == 0          → succeeded
 *   - applied == 0 && failed >= target               → failed
 *   - applied > 0  && failed > 0                     → partial
 *   - 其它                                            → running
 * completed_at 仅当 (applied + failed) >= target 时才置 now()，否则保持 null。
 */
final class PublishTaskStateMachineTest extends TestCase
{
    use RefreshDatabase;

    private ConfigAcknowledgementService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ConfigAcknowledgementService(new ConfigAckService());
    }

    private function makeInstalledNode(string $suffix = 'A'): Node
    {
        return Node::create([
            'node_code'     => 'regression_' . strtolower($suffix),
            'node_alias'    => 'Regression Node ' . $suffix,
            'install_status'=> 'installed',
            'last_heartbeat_at' => now(),
        ]);
    }

    private function makePublishTask(int $target, array $executionsSeed): PublishTask
    {
        $task = PublishTask::create([
            'profile_version_id'  => null,
            'profile_id'          => null,
            'status'              => 'running',
            'target_scope'        => 'all_nodes',
            'target_filter'       => [],
            'target_node_count'   => $target,
            'applied_node_count'  => 0,
            'failed_node_count'   => 0,
            'retry_count'         => 0,
            'message'             => 'seed',
            'queued_at'           => now(),
        ]);

        foreach ($executionsSeed as $seed) {
            TaskExecution::create([
                'id'                => (string) Str::ulid(),
                'publish_task_id'   => $task->id,
                'node_id'           => $seed['node_id'],
                'config_version'    => 100,
                'status'            => $seed['status'],
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);
        }

        return $task;
    }

    public function test_pending_keeps_status_running_not_succeeded(): void
    {
        $n1 = $this->makeInstalledNode('P1');
        $n2 = $this->makeInstalledNode('P2');
        $n3 = $this->makeInstalledNode('P3');
        $task = $this->makePublishTask(3, [
            ['node_id' => $n1->id, 'status' => 'pending'],
            ['node_id' => $n2->id, 'status' => 'pending'],
            ['node_id' => $n3->id, 'status' => 'pending'],
        ]);

        $this->service->acknowledge($n1, [
            'config_version' => 100,
            'status'         => 'applied',
        ]);

        $task->refresh();
        $this->assertSame('running', $task->status, 'pending>0 时不能为 succeeded');
        $this->assertNull($task->completed_at, '未全部 ACK 时 completed_at 必须为 null');
    }

    public function test_all_applied_no_failed_marks_succeeded(): void
    {
        $n1 = $this->makeInstalledNode('A1');
        $n2 = $this->makeInstalledNode('A2');
        $n3 = $this->makeInstalledNode('A3');
        $task = $this->makePublishTask(3, [
            ['node_id' => $n1->id, 'status' => 'applied'],
            ['node_id' => $n2->id, 'status' => 'applied'],
            ['node_id' => $n3->id, 'status' => 'pending'],
        ]);

        $this->service->acknowledge($n3, [
            'config_version' => 100,
            'status'         => 'applied',
        ]);

        $task->refresh();
        $this->assertSame('succeeded', $task->status);
        $this->assertNotNull($task->completed_at);
    }

    public function test_all_failed_marks_failed(): void
    {
        $n1 = $this->makeInstalledNode('F1');
        $n2 = $this->makeInstalledNode('F2');
        $n3 = $this->makeInstalledNode('F3');
        $task = $this->makePublishTask(3, [
            ['node_id' => $n1->id, 'status' => 'failed'],
            ['node_id' => $n2->id, 'status' => 'failed'],
            ['node_id' => $n3->id, 'status' => 'pending'],
        ]);

        $this->service->acknowledge($n3, [
            'config_version' => 100,
            'status'         => 'failed',
        ]);

        $task->refresh();
        $this->assertSame('failed', $task->status);
        $this->assertNotNull($task->completed_at);
    }

    public function test_mixed_applied_and_failed_marks_partial(): void
    {
        $n1 = $this->makeInstalledNode('M1');
        $n2 = $this->makeInstalledNode('M2');
        $n3 = $this->makeInstalledNode('M3');
        $task = $this->makePublishTask(3, [
            ['node_id' => $n1->id, 'status' => 'applied'],
            ['node_id' => $n2->id, 'status' => 'failed'],
            ['node_id' => $n3->id, 'status' => 'pending'],
        ]);

        $this->service->acknowledge($n3, [
            'config_version' => 100,
            'status'         => 'failed',
        ]);

        $task->refresh();
        $this->assertSame('partial', $task->status);
        $this->assertNotNull($task->completed_at);
    }
}
