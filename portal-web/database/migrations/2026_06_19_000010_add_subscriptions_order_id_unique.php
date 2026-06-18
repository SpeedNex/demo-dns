<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P0-B5: subscriptions.order_id 唯一。
 * 同一订单只能开一次订阅（防重复 webhook）。
 */
return new class extends Migration {
    public function up(): void
    {
        $prefix = DB::connection()->getTablePrefix();
        Schema::table('subscriptions', function (Blueprint $table) use ($prefix): void {
            if (! Schema::hasColumn('subscriptions', 'order_id')) {
                $table->unsignedBigInteger('order_id')->nullable()->after('user_id');
            }
        });
        // 唯一索引：同一 order_id 只能产生一个 subscription
        $exists = DB::selectOne(
            "SELECT 1 FROM pg_indexes WHERE indexname = '" . $prefix . "subscriptions_order_id_unique'"
        );
        if ($exists === null) {
            DB::statement('CREATE UNIQUE INDEX ' . $prefix . 'subscriptions_order_id_unique ON ' . $prefix . 'subscriptions (order_id) WHERE order_id IS NOT NULL');
        }
    }

    public function down(): void
    {
        $prefix = DB::connection()->getTablePrefix();
        DB::statement('DROP INDEX IF EXISTS ' . $prefix . 'subscriptions_order_id_unique');
    }
};
