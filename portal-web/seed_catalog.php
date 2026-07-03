<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Domain\Profile\MemberCatalogService;
use App\Models\SystemConfig;

$service = new MemberCatalogService();
$defaults = $service->defaults();

$stored = SystemConfig::query()->where('config_key', MemberCatalogService::CONFIG_KEY)->first();

if ($stored) {
    $stored->config_value = $defaults;
    $stored->description = '会员目录默认配置（安全/隐私/家长）';
    $stored->save();
    echo "Updated id={$stored->id}\n";
} else {
    $created = SystemConfig::query()->create([
        'config_key' => MemberCatalogService::CONFIG_KEY,
        'config_value' => $defaults,
        'description' => '会员目录默认配置（安全/隐私/家长）',
    ]);
    echo "Created id={$created->id}\n";
}

echo "Done!\n";
