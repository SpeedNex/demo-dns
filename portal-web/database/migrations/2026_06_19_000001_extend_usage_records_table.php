<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P0-1 修复：旧表 usage_records (2026_06_16_120000) 缺少新计费维度字段。
 * 重复的 2026_06_18_190000 已删除，本迁移通过 alter 补齐字段。
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('usage_records', function (Blueprint $table): void {
            if (! Schema::hasColumn('usage_records', 'profile_id')) {
                $table->string('profile_id', 30)->nullable()->after('user_id');
            }
            if (! Schema::hasColumn('usage_records', 'device_id')) {
                $table->string('device_id', 30)->nullable()->after('profile_id');
            }
            if (! Schema::hasColumn('usage_records', 'billing_category')) {
                $table->string('billing_category', 32)->default('normal_query')
                    ->comment('normal_query / encrypted_dns / dnssec')
                    ->after('device_id');
            }
            if (! Schema::hasColumn('usage_records', 'period_start')) {
                $table->timestamp('period_start')->nullable()->after('billing_category');
            }
            if (! Schema::hasColumn('usage_records', 'period_end')) {
                $table->timestamp('period_end')->nullable()->after('period_start');
            }
            if (! Schema::hasColumn('usage_records', 'amount_minor')) {
                $table->bigInteger('amount_minor')->default(0)->after('query_count')
                    ->comment('单位:分');
            }
            if (! Schema::hasColumn('usage_records', 'billing_period_id')) {
                $table->unsignedBigInteger('billing_period_id')->nullable()->after('amount_minor');
            }
        });

        DB::statement('CREATE INDEX IF NOT EXISTS usage_records_user_period_idx ON usage_records (user_id, period_start)');
        DB::statement('CREATE INDEX IF NOT EXISTS usage_records_billing_period_idx ON usage_records (billing_period_id)');
    }

    public function down(): void
    {
        // 不删除扩展字段，保持前向兼容
    }
};
