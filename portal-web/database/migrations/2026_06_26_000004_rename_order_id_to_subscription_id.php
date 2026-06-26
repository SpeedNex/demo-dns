<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 将 dns_payment_transactions.order_id 重命名为 subscription_id。
 * 订单表已删除，支付交易直接关联订阅。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->dropIndex('idx_pt_order');
            $table->renameColumn('order_id', 'subscription_id');
            $table->index('subscription_id', 'idx_pt_subscription');
        });
    }

    public function down(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->dropIndex('idx_pt_subscription');
            $table->renameColumn('subscription_id', 'order_id');
            $table->index('order_id', 'idx_pt_order');
        });
    }
};