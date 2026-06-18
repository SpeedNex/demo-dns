<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P0-4 修复：wallet_transactions 补齐 wallet_id / transaction_no / balance_after。
 * UI.md #54/#55 钱包流水必须写入变更后余额。
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table): void {
            if (! Schema::hasColumn('wallet_transactions', 'wallet_id')) {
                $table->unsignedBigInteger('wallet_id')->nullable()->after('user_id');
            }
            if (! Schema::hasColumn('wallet_transactions', 'transaction_no')) {
                $table->string('transaction_no', 64)->nullable()->after('wallet_id');
            }
            if (! Schema::hasColumn('wallet_transactions', 'balance_after')) {
                $table->bigInteger('balance_after')->nullable()
                    ->comment('单位:分,变更后余额')
                    ->after('amount_minor');
            }
        });

        $this->safeIndex('wallet_transactions', 'wallet_transactions_wallet_id_idx', '(wallet_id)');
        $this->safeIndex('wallet_transactions', 'wallet_transactions_txno_uq', '(transaction_no)', true);
    }

    public function down(): void
    {
        // 不删除扩展字段
    }

    private function safeIndex(string $table, string $name, string $cols, bool $unique = false): void
    {
        $exists = DB::select("SELECT 1 FROM pg_indexes WHERE indexname = ?", [$name]);
        if (count($exists) > 0) {
            return;
        }
        $kw = $unique ? 'CREATE UNIQUE INDEX' : 'CREATE INDEX';
        DB::statement("{$kw} {$name} ON {$table} {$cols}");
    }
};
