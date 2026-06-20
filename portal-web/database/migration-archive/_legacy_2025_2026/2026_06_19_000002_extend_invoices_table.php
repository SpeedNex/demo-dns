<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P0-2/P0-3 修复：invoices 补齐 billing_type / billing_period_id / order_id / issued_at。
 * 金额字段统一为 amount_minor（不新增 amount 列，避免歧义）。
 */
return new class extends Migration {
    public function up(): void
    {
        $driver = DB::getDriverName();

        Schema::table('invoices', function (Blueprint $table): void {
            if (! Schema::hasColumn('invoices', 'billing_type')) {
                $table->string('billing_type', 30)->default('plan')
                    ->comment('plan / usage / adjustment')
                    ->after('type');
            }
            if (! Schema::hasColumn('invoices', 'billing_period_id')) {
                $table->unsignedBigInteger('billing_period_id')->nullable()->after('billing_type');
            }
            if (! Schema::hasColumn('invoices', 'order_id')) {
                $table->unsignedBigInteger('order_id')->nullable()->after('billing_period_id');
            }
            if (! Schema::hasColumn('invoices', 'issued_at')) {
                $table->timestamp('issued_at')->nullable()->after('paid_at');
            }
        });

        // 补齐索引（MySQL 8.0 不支持 IF NOT EXISTS，用 try/catch 容错）
        $prefix = DB::connection()->getTablePrefix();
        try {
            DB::statement('CREATE INDEX ' . $prefix . 'invoices_billing_period_idx ON ' . $prefix . 'invoices (billing_period_id)');
        } catch (\Throwable $e) {
            // index already exists
        }
        try {
            DB::statement('CREATE INDEX ' . $prefix . 'invoices_billing_type_idx ON ' . $prefix . 'invoices (billing_type)');
        } catch (\Throwable $e) {
            // index already exists
        }

        // 注释统一（MySQL 8.0 通过 MODIFY COLUMN COMMENT 设置）
        if ($driver === 'mysql') {
            $colInfo = DB::selectOne("SHOW COLUMNS FROM {$prefix}invoices WHERE Field = 'amount_minor'");
            if ($colInfo) {
                $colType = $colInfo->Type;
                $nullable = ((string) $colInfo->Null) === 'YES' ? 'NULL' : 'NOT NULL';
                $defaultClause = $colInfo->Default === null ? '' : " DEFAULT '{$colInfo->Default}'";
                DB::statement("ALTER TABLE {$prefix}invoices MODIFY COLUMN amount_minor {$colType} {$nullable}{$defaultClause} COMMENT '单位:分'");
            }
        }
    }

    public function down(): void
    {
        // 不删除扩展字段，保持前向兼容
    }
};
