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

        $stored = SystemConfig::query()->where('config_key', MemberCatalogService::CONFIG_KEY)->first();
        $config = $stored?->config_value ?? [];

        // 若线上完全无配置，直接写入完整默认值
        if (! is_array($config) || $config === []) {
            SystemConfig::query()->updateOrCreate(
                ['config_key' => MemberCatalogService::CONFIG_KEY],
                [
                    'config_value' => $defaults,
                    'description' => '会员目录默认配置（安全/隐私/家长）',
                ]
            );

            return;
        }

        // 仅合并本次需要补充的系统项，不覆盖管理员自定义内容
        $config = $this->mergeDevices($config, $defaults);
        $config = $this->mergeAppPresets($config, $defaults);

        $stored->update([
            'config_value' => $config,
            'description' => '会员目录默认配置（安全/隐私/家长）',
        ]);
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

    /**
     * 合并深度跟踪保护的默认设备列表。
     */
    private function mergeDevices(array $config, array $defaults): array
    {
        $defaultDevices = $this->findDeepTrackingDevices($defaults);
        if ($defaultDevices === null) {
            return $config;
        }

        foreach ($config['privacy_blocklists'] ?? [] as $index => $item) {
            if (($item['key'] ?? '') === 'deep_tracking_protection') {
                $config['privacy_blocklists'][$index]['devices'] = $defaultDevices;
                $config['privacy_blocklists'][$index]['field_type'] = 'multi';
                break;
            }
        }

        return $config;
    }

    /**
     * 合并家长控制应用预设的默认选项列表。
     */
    private function mergeAppPresets(array $config, array $defaults): array
    {
        $defaultOptions = $this->findAppPresetOptions($defaults);
        if ($defaultOptions === null) {
            return $config;
        }

        foreach ($config['parental_presets'] ?? [] as $index => $item) {
            if (($item['key'] ?? '') === 'app_presets') {
                $config['parental_presets'][$index]['options'] = $defaultOptions;
                $config['parental_presets'][$index]['field_type'] = 'multi';
                break;
            }
        }

        return $config;
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    private function findDeepTrackingDevices(array $defaults): ?array
    {
        foreach ($defaults['privacy_blocklists'] ?? [] as $item) {
            if (($item['key'] ?? '') === 'deep_tracking_protection' && isset($item['devices'])) {
                return $item['devices'];
            }
        }

        return null;
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    private function findAppPresetOptions(array $defaults): ?array
    {
        foreach ($defaults['parental_presets'] ?? [] as $item) {
            if (($item['key'] ?? '') === 'app_presets' && isset($item['options'])) {
                return $item['options'];
            }
        }

        return null;
    }
};
