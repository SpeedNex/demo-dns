<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Profile\MemberCatalogService;
use App\Models\SystemConfig;
use Illuminate\Database\Seeder;

final class MemberFeatureCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $service = new MemberCatalogService();
        $defaults = $service->defaults();

        $stored = SystemConfig::query()->where('config_key', MemberCatalogService::CONFIG_KEY)->first();

        if ($stored) {
            $stored->update([
                'config_value' => $defaults,
                'description' => '会员目录默认配置（安全/隐私/家长）',
            ]);
        } else {
            SystemConfig::query()->create([
                'config_key' => MemberCatalogService::CONFIG_KEY,
                'config_value' => $defaults,
                'description' => '会员目录默认配置（安全/隐私/家长）',
            ]);
        }
    }
}
