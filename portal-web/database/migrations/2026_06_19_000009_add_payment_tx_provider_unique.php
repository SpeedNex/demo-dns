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
        // 索引已存在则跳过
        $exists = DB::selectOne(
            "SELECT 1 FROM pg_indexes WHERE indexname = '" . $prefix . "payment_tx_provider_session_unique'"
        );
        if ($exists === null) {
            DB::statement('CREATE UNIQUE INDEX ' . $prefix . 'payment_tx_provider_session_unique ON ' . $prefix . 'payment_transactions (provider, provider_session_id) WHERE provider_session_id IS NOT NULL');
        }
    }

    public function down(): void
    {
        $prefix = DB::connection()->getTablePrefix();
        DB::statement('DROP INDEX IF EXISTS ' . $prefix . 'payment_tx_provider_session_unique');
    }
};
