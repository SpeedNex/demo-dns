<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P0-B1: orders 幂等键。
 * 唯一索引 (user_id, idempotency_key) → 防止用户双击产生双订单。
 *
 * MySQL UNIQUE 允许多个 NULL，因此省略 PG 风格的 partial WHERE。
 */
return new class extends Migration {
    public function up(): void
    {
        $prefix = DB::connection()->getTablePrefix();
        Schema::table('orders', function (Blueprint $table) use ($prefix): void {
            if (! Schema::hasColumn('orders', 'idempotency_key')) {
                $table->string('idempotency_key', 80)->nullable()->after('order_no');
            }
        });
        DB::statement('CREATE UNIQUE INDEX ' . $prefix . 'orders_user_idem_unique ON ' . $prefix . 'orders (user_id, idempotency_key)');
    }

    public function down(): void
    {
        $prefix = DB::connection()->getTablePrefix();
        DB::statement('DROP INDEX ' . $prefix . 'orders_user_idem_unique ON ' . $prefix . 'orders');
        // 保留列：向前兼容
    }
};
