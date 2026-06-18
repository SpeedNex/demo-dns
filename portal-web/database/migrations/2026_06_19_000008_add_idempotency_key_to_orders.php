<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P0-B1: orders 幂等键。
 * 唯一索引 (user_id, idempotency_key) → 防止用户双击产生双订单。
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('orders', 'idempotency_key')) {
                $table->string('idempotency_key', 80)->nullable()->after('order_no');
            }
        });
        // PostgreSQL 唯一索引（partial：只对非空键生效，允许历史数据）
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS orders_user_idem_unique ON orders (user_id, idempotency_key) WHERE idempotency_key IS NOT NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS orders_user_idem_unique');
        // 保留列：向前兼容
    }
};
