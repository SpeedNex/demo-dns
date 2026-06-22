<?php
require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Node;
use App\Models\GeoDnsMapping;

// 删除我刚才添加的 nd_localhost（已有 dev-local-01 和 nd_local_mac 都指 127.0.0.1）
$deleted = Node::where('node_code', 'nd_localhost')->delete();
echo "Deleted nd_localhost: {$deleted}" . PHP_EOL;

// 检查 127.0.0.1 的现有节点
$localNodes = Node::where('public_ipv4', '127.0.0.1')->get();
echo "--- 127.0.0.1 nodes ---" . PHP_EOL;
foreach ($localNodes as $n) {
    echo "  [{$n->node_id}] {$n->node_code} {$n->node_alias} | status={$n->status}" . PHP_EOL;
}

echo "---" . PHP_EOL;
echo 'Final nodes: ' . Node::count() . PHP_EOL;
foreach (Node::all() as $n) {
    echo "  [{$n->node_id}] {$n->node_code} | {$n->node_alias} | {$n->public_ipv4} | {$n->status}" . PHP_EOL;
}
echo "Mappings: " . GeoDnsMapping::count() . PHP_EOL;
