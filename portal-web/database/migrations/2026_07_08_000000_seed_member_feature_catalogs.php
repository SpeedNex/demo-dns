<?php

declare(strict_types=1);

use App\Domain\Profile\MemberCatalogService;
use App\Models\SystemConfig;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('system_configs')) {
            return;
        }

        $service = new MemberCatalogService();
        $defaults = $service->defaults();

        SystemConfig::query()->updateOrCreate(
            ['config_key' => MemberCatalogService::CONFIG_KEY],
            [
                'config_value' => $defaults,
                'description' => '会员目录默认配置（安全/隐私/家长）',
            ]
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('system_configs')) {
            return;
        }

        SystemConfig::query()
            ->where('config_key', MemberCatalogService::CONFIG_KEY)
            ->delete();
    }
};
