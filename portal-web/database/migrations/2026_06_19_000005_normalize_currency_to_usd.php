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
            DB::statement("ALTER TABLE {$prefix}{$table} ALTER COLUMN {$col} SET DEFAULT 'USD'");
        }
        if (Schema::hasColumn('users', 'currency')) {
            DB::statement("UPDATE {$prefix}users SET currency = 'USD' WHERE currency IS NULL OR currency = ''");
            DB::statement("ALTER TABLE {$prefix}users ALTER COLUMN currency SET DEFAULT 'USD'");
        }
    }

    public function down(): void
    {
        // 不可回滚（破坏性）
    }
};
