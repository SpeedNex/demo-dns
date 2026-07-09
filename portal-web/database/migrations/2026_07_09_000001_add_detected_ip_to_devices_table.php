<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->string('detected_ip', 45)->nullable()->after('ip_hash')
                ->comment('设备自动检测到的公网 IP（仅在隐私允许时记录明文）');
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn('detected_ip');
        });
    }
};
