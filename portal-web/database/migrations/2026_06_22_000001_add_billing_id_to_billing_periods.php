<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UI.md P0#2: UsageBillingService::generateBillingsForClosedPeriods()
 *   whereNull('billing_id')  +  update(['billing_id' => $billingId])
 * 但 2026_06_20_000033_create_dns_billing_periods_table.php 没有 billing_id 列，
 * 导致 billing:generate 命令直接 SQL 错误。
 *
 * 修复：补充 billing_id 列（nullable，账单生成后回填）。
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('billing_periods') && ! Schema::hasColumn('billing_periods', 'billing_id')) {
            Schema::table('billing_periods', function (Blueprint $table) {
                $table->unsignedBigInteger('billing_id')->nullable()->after('closed_at');
                $table->index('billing_id', 'idx_billing_periods_billing');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('billing_periods') && Schema::hasColumn('billing_periods', 'billing_id')) {
            Schema::table('billing_periods', function (Blueprint $table) {
                $table->dropIndex('idx_billing_periods_billing');
                $table->dropColumn('billing_id');
            });
        }
    }
};
