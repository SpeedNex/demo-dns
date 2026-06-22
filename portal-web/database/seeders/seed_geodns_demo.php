<?php
require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Node;
use App\Models\GeoDnsMapping;

// 1. 创建当前服务器节点 (主节点)
$current = Node::updateOrCreate(
    ['node_code' => 'nd_localhost'],
    [
        'name' => '本地节点 (127.0.0.1)',
        'region' => 'CN',
        'country' => 'CN',
        'city' => '本地',
        'public_ipv4' => '127.0.0.1',
        'public_ipv6' => '::1',
        'supported_protocols' => ['dns-over-https', 'dns-over-tls', 'plain-dns'],
        'status' => 'online',
        'current_config_version' => 1,
        'desired_config_version' => 1,
        'last_heartbeat_at' => now(),
        'meta' => ['description' => '当前开发服务器', 'test' => true, 'is_current' => true],
    ]
);
echo "[1] 本地节点 ID={$current->id} code={$current->node_code}" . PHP_EOL;

// 2. 创建几个模拟地区节点
$nodeDefs = [
    ['node_code' => 'nd_cn_bj', 'name' => '北京节点 (CN-Beijing)', 'region' => 'CN', 'country' => 'CN', 'city' => 'Beijing', 'public_ipv4' => '1.2.3.4'],
    ['node_code' => 'nd_us_sj', 'name' => '圣何塞节点 (US-SJ)',    'region' => 'US', 'country' => 'US', 'city' => 'San Jose', 'public_ipv4' => '5.6.7.8'],
    ['node_code' => 'nd_eu_fr', 'name' => '法兰克福节点 (EU-Frankfurt)', 'region' => 'EU', 'country' => 'DE', 'city' => 'Frankfurt', 'public_ipv4' => '9.10.11.12'],
    ['node_code' => 'nd_sg',    'name' => '新加坡节点 (SG)',       'region' => 'SG', 'country' => 'SG', 'city' => 'Singapore', 'public_ipv4' => '13.14.15.16'],
];
foreach ($nodeDefs as $n) {
    $node = Node::updateOrCreate(
        ['node_code' => $n['node_code']],
        [
            'name' => $n['name'],
            'region' => $n['region'],
            'country' => $n['country'],
            'city' => $n['city'],
            'public_ipv4' => $n['public_ipv4'],
            'supported_protocols' => ['dns-over-https', 'dns-over-tls'],
            'status' => 'online',
            'current_config_version' => 1,
            'desired_config_version' => 1,
            'last_heartbeat_at' => now(),
            'meta' => ['test_data' => true],
        ]
    );
    echo "[+] {$node->node_code} → ID={$node->node_id} ({$node->node_alias})" . PHP_EOL;
}

// 3. 创建 geo_dns_mappings 路由规则
$local = Node::where('node_code', 'nd_localhost')->first();
$bj    = Node::where('node_code', 'nd_cn_bj')->first();
$usj   = Node::where('node_code', 'nd_us_sj')->first();
$eu    = Node::where('node_code', 'nd_eu_fr')->first();
$sg    = Node::where('node_code', 'nd_sg')->first();

$mappings = [
    ['example.com',         'CN', 'CN',     $bj,    'https://1.2.3.4:443/dns-query',      100],
    ['example.com',         'US', 'US',     $usj,   'https://5.6.7.8:443/dns-query',      100],
    ['example.com',         'DE', 'EU',     $eu,    'https://9.10.11.12:443/dns-query',    100],
    ['example.com',         'SG', 'SG',     $sg,    'https://13.14.15.16:443/dns-query',   80],
    ['ocerdns.com',         'CN', 'CN',     $local, 'http://127.0.0.1:8080/dns-query',     100],
    ['ocerdns.com',         null, 'global', $local, 'http://127.0.0.1:8080/dns-query',     50],
    ['dns.ocerdns.com',     'CN', 'CN',     $local, 'http://127.0.0.1:5353/dns-query',     100],
    ['dns.ocerdns.com',     null, 'global', $usj,   'https://5.6.7.8:443/dns-query',      60],
];
foreach ($mappings as $m) {
    [$domain, $country, $region, $node, $endpoint, $weight] = $m;
    $row = GeoDnsMapping::updateOrCreate(
        ['domain' => $domain, 'country' => $country, 'region' => $region],
        [
            'target_node_id' => $node?->id,
            'target_endpoint' => $endpoint,
            'weight' => $weight,
            'enabled' => true,
        ]
    );
    echo "[M] {$row->domain} | " . ($row->country ?? '*') . " | {$row->region} → node_id=" . ($row->target_node_id ?? '-') . " ({$node?->node_alias})" . PHP_EOL;
}

echo "---" . PHP_EOL;
echo 'Total nodes: ' . Node::count() . PHP_EOL;
echo 'Online nodes: ' . Node::where('status', 'online')->count() . PHP_EOL;
echo 'Total mappings: ' . GeoDnsMapping::count() . PHP_EOL;
