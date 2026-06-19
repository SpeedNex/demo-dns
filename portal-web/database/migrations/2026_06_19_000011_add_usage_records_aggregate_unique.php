<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P0-B7: usage_records 聚合唯一约束。
 * 同一 (user_id, profile_id, device_id, billing_category, billing_period_id) 只能聚合一次。
 */
return new class extends Migration {
    public function up(): void
    {
        $prefix = DB::connection()->getTablePrefix();
        try {
            DB::statement('CREATE UNIQUE INDEX ' . $prefix . 'usage_records_aggregate_unique ON ' . $prefix . 'usage_records (user_id, profile_id, device_id, billing_category, billing_period_id)');
        } catch (\Throwable $e) {
            // already exists
        }
    }

    public function down(): void
    {
        $prefix = DB::connection()->getTablePrefix();
        try {
            if (DB::getDriverName() === 'sqlite') {
                DB::statement('DROP INDEX ' . $prefix . 'usage_records_aggregate_unique');
            } else {
                DB::statement('DROP INDEX ' . $prefix . 'usage_records_aggregate_unique ON ' . $prefix . 'usage_records');
            }
        } catch (\Throwable $e) {
            // ignore
        }
    }
};
