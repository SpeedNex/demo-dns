<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->string('device_os', 50)->nullable()->after('source_ip')->comment('Device OS: macOS, iOS, Windows, Android, Linux, etc.');
            $table->string('device_type', 30)->nullable()->after('source_ip')->comment('Device category: desktop, mobile, tablet, router, other');
            $table->index('device_type', 'idx_devices_device_type');
            $table->index('device_os', 'idx_devices_device_os');
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropIndex('idx_devices_device_type');
            $table->dropIndex('idx_devices_device_os');
            $table->dropColumn('device_type');
            $table->dropColumn('device_os');
        });
    }
};