<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P0-B2: payment_transactions 幂等键。
 * 唯一索引 (provider, provider_session_id) → 防止重复 webhook 重复入账。
 */
return new class extends Migration {
    public function up(): void
    {
        $prefix = DB::connection()->getTablePrefix();
        try {
            DB::statement('CREATE UNIQUE INDEX ' . $prefix . 'payment_tx_provider_session_unique ON ' . $prefix . 'payment_transactions (provider, provider_session_id)');
        } catch (\Throwable $e) {
            // already exists
        }
    }

    public function down(): void
    {
        $prefix = DB::connection()->getTablePrefix();
        try {
            if (DB::getDriverName() === 'sqlite') {
                DB::statement('DROP INDEX ' . $prefix . 'payment_tx_provider_session_unique');
            } else {
                DB::statement('DROP INDEX ' . $prefix . 'payment_tx_provider_session_unique ON ' . $prefix . 'payment_transactions');
            }
        } catch (\Throwable $e) {
            // ignore
        }
    }
};
