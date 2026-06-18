<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P0-5 修复：V1 货币统一 USD。
 * 历史 CNY 余额由一次性数据迁移脚本按 1 USD = 7.2 CNY 折算（独立运行）。
 */
return new class extends Migration {
    public function up(): void
    {
        $prefix = DB::connection()->getTablePrefix();
        $tables = [
            'wallets' => 'currency',
            'wallet_transactions' => 'currency',
            'invoices' => 'currency',
            'orders' => 'currency',
            'payment_transactions' => 'currency',
            'plan_prices' => 'currency',
        ];
        foreach ($tables as $table => $col) {
            if (! Schema::hasColumn($table, $col)) {
                continue;
            }
            DB::statement("UPDATE {$prefix}{$table} SET {$col} = 'USD' WHERE {$col} IS NULL OR {$col} = ''");
            $colInfo = DB::selectOne("SHOW COLUMNS FROM {$prefix}{$table} WHERE Field = ?", [$col]);
            if ($colInfo) {
                // 提取列类型（保留 Null/Default 等子句之外的部分）
                $colType = $colInfo->Type;
                $nullable = ((string) $colInfo->Null) === 'YES' ? 'NULL' : 'NOT NULL';
                DB::statement("ALTER TABLE {$prefix}{$table} MODIFY COLUMN {$col} {$colType} {$nullable} DEFAULT 'USD'");
            }
        }
        if (Schema::hasColumn('users', 'currency')) {
            DB::statement("UPDATE {$prefix}users SET currency = 'USD' WHERE currency IS NULL OR currency = ''");
            $colInfo = DB::selectOne("SHOW COLUMNS FROM {$prefix}users WHERE Field = 'currency'");
            if ($colInfo) {
                $colType = $colInfo->Type;
                $nullable = ((string) $colInfo->Null) === 'YES' ? 'NULL' : 'NOT NULL';
                DB::statement("ALTER TABLE {$prefix}users MODIFY COLUMN currency {$colType} {$nullable} DEFAULT 'USD'");
            }
        }
    }

    public function down(): void
    {
        // 不可回滚（破坏性）
    }
};
