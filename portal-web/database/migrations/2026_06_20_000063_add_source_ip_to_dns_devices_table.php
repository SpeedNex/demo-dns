<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * 给 dns_devices 加 source_ip 字段，用于按 IP 路由多 profile。
     * ProfileConfigBuilder::mapDevice 已经支持
     * $device['source_ip'] 字段，但 dns_devices 之前只有 ip_hash。
     */
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->string('source_ip', 45)->nullable()->after('ip_hash')->comment('plaintext client IP used for profile->IP routing in cached profiles; do not log');
            $table->index('source_ip', 'idx_devices_source_ip');
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropIndex('idx_devices_source_ip');
            $table->dropColumn('source_ip');
        });
    }
};
