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
        $exists = DB::selectOne(
            "SELECT 1 FROM pg_indexes WHERE indexname = 'usage_records_aggregate_unique'"
        );
        if ($exists === null) {
            DB::statement('CREATE UNIQUE INDEX usage_records_aggregate_unique ON usage_records (user_id, profile_id, device_id, billing_category, billing_period_id)');
        }
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS usage_records_aggregate_unique');
    }
};
