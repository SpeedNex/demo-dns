<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * UI.md P0#5: dns_usage_records.device_id 是 nullable，但 uniq_usage_aggregate
 *   唯一索引包含 device_id。MySQL 把 NULL 视为不同值 → 同一 user/profile/period
 *   可写入多条 device_id=NULL 记录 → 重复计费。
 *
 * 修复：将 NULL 历史的 device_id 回填为 0，再把列改为 NOT NULL DEFAULT 0。
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('usage_records')) {
            return;
        }

        // 1) 历史 NULL device_id 全部回填为 0（应用层 key 也用 0 表示"无设备"）
        DB::table('usage_records')
            ->whereNull('device_id')
            ->update(['device_id' => 0]);

        // 2) 收紧列为 NOT NULL DEFAULT 0
        Schema::table('usage_records', function (Blueprint $table) {
            $table->unsignedBigInteger('device_id')->default(0)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('usage_records')) {
            return;
        }

        Schema::table('usage_records', function (Blueprint $table) {
            $table->unsignedBigInteger('device_id')->nullable()->default(null)->change();
        });
    }
};
