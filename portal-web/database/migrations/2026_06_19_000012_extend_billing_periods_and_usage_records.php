<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('billing_periods', function (Blueprint $table): void {
            if (! Schema::hasColumn('billing_periods', 'period_code')) {
                $table->string('period_code', 7)->nullable()->after('currency');
            }
        });

        Schema::table('usage_records', function (Blueprint $table): void {
            if (! Schema::hasColumn('usage_records', 'period')) {
                $table->string('period', 7)->nullable()->after('billing_category');
            }
            if (! Schema::hasColumn('usage_records', 'plan_code')) {
                $table->string('plan_code', 30)->nullable()->after('period');
            }
        });

        $prefix = DB::connection()->getTablePrefix();
        // MySQL 8.0 不支持 IF NOT EXISTS，用 try/catch 容错
        try {
            DB::statement('CREATE UNIQUE INDEX ' . $prefix . 'billing_periods_user_period_unique ON ' . $prefix . 'billing_periods (user_id, period_start)');
        } catch (\Throwable $e) {
            // index already exists
        }
    }

    public function down(): void
    {
        // forward only
    }
};
