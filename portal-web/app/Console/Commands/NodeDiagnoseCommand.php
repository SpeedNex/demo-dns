<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Node;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * node:diagnose — 诊断节点注册/鉴权问题
 *
 * 用法:
 *   php artisan node:diagnose                    # 诊断所有节点
 *   php artisan node:diagnose --node-id=16       # 诊断指定节点
 *   php artisan node:diagnose --fix-api-key       # 修复所有节点的 api_key
 */
final class NodeDiagnoseCommand extends Command
{
    protected $signature = 'node:diagnose
        {--node-id= : 指定节点 ID}
        {--fix-api-key : 为所有节点生成/更新 api_key}';

    protected $description = 'Diagnose node registration and API key issues';

    public function handle(): int
    {
        $this->info('=== Node Diagnostic Report ===');

        // 1. 检查表结构
        $this->checkTableStructure();

        // 2. 检查节点数据
        $this->checkNodeData();

        // 3. 修复 api_key（如果需要）
        if ($this->option('fix-api-key')) {
            $this->fixApiKeys();
        }

        return 0;
    }

    private function checkTableStructure(): void
    {
        $this->info('');
        $this->info('--- Table Structure Check ---');

        $table = 'resolver_nodes';

        if (! Schema::hasTable($table)) {
            $this->error("Table '{$table}' does not exist!");
            return;
        }

        $this->info("Table '{$table}' exists.");

        // 检查关键列
        $requiredColumns = ['id', 'node_code', 'api_key', 'api_key_issued_at', 'install_status'];
        foreach ($requiredColumns as $col) {
            if (Schema::hasColumn($table, $col)) {
                $this->info("  ✓ Column '{$col}' exists");
            } else {
                $this->error("  ✗ Column '{$col}' MISSING!");
            }
        }
    }

    private function checkNodeData(): void
    {
        $this->info('');
        $this->info('--- Node Data Check ---');

        $query = Node::query();
        $nodeId = $this->option('node-id');

        if ($nodeId) {
            $query->where('id', $nodeId);
        }

        $nodes = $query->get();

        if ($nodes->isEmpty()) {
            $this->warn('No nodes found.');
            return;
        }

        $this->info("Found {$nodes->count()} node(s).");

        foreach ($nodes as $node) {
            $this->info('');
            $this->info("Node #{$node->id}:");
            $this->table(
                ['Field', 'Value'],
                [
                    ['node_code', $node->node_code ?? '(null)'],
                    ['node_alias', $node->node_alias ?? '(null)'],
                    ['install_status', $node->install_status ?? '(null)'],
                    ['api_key', $node->api_key ? '(hash set)' : '(null)'],
                    ['api_key_issued_at', $node->api_key_issued_at ?? '(null)'],
                    ['last_heartbeat_at', $node->last_heartbeat_at ?? '(null)'],
                    ['region', $node->region ?? '(null)'],
                ]
            );

            // 诊断问题
            $issues = [];
            if (empty($node->api_key)) {
                $issues[] = 'MISSING api_key - node cannot authenticate!';
            }
            if ($node->install_status === 'failed') {
                $issues[] = 'install_status=failed';
            }

            if (! empty($issues)) {
                $this->error('Issues:');
                foreach ($issues as $issue) {
                    $this->error("  - {$issue}");
                }
            } else {
                $this->info('  ✓ No issues found');
            }
        }
    }

    private function fixApiKeys(): void
    {
        $this->info('');
        $this->info('--- Fixing API Keys ---');

        $nodes = Node::all();

        if ($nodes->isEmpty()) {
            $this->warn('No nodes to fix.');
            return;
        }

        foreach ($nodes as $node) {
            // 生成新的 api_key
            $plainKey = 'ak_' . bin2hex(random_bytes(16));
            $hashedKey = hash('sha256', $plainKey);

            $node->api_key = $hashedKey;
            $node->api_key_issued_at = now();
            $node->save();

            $this->info("Node #{$node->id} ({$node->node_code}):");
            $this->info("  Plain API Key: {$plainKey}");
            $this->info("  → Use this key in resolver config: api_key: \"{$plainKey}\"");
            $this->info('');
        }

        $this->warn('IMPORTANT: Update all resolver configs with the new API keys above!');
    }
}
