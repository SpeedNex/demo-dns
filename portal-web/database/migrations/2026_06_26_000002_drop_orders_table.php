<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 删除 dns_orders 订单表。
 * SaaS 订阅模式下，用户购买流程改为：
 *   Subscription → Checkout → Payment → Invoice → Profile 升级
 * 不再需要独立的订单表。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('orders');
    }

    public function down(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('order_no', 40);
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('plan_id');
            $table->unsignedBigInteger('plan_price_id');
            $table->enum('billing_cycle', ['monthly', 'yearly'])->default('monthly');
            $table->char('currency', 3)->default('USD');
            $table->string('plan_code_snapshot', 40);
            $table->unsignedBigInteger('original_amount_minor');
            $table->unsignedBigInteger('discount_amount_minor')->default(0);
            $table->unsignedBigInteger('payable_amount_minor');
            $table->string('idempotency_key', 80);
            $table->enum('status', ['pending', 'paid', 'cancelled', 'refunded', 'failed', 'expired'])->default('pending');
            $table->string('provider', 40)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->unique('order_no', 'uniq_orders_no');
            $table->unique('idempotency_key', 'uniq_orders_idempotency');
            $table->index('user_id', 'idx_orders_user');
            $table->index('plan_id', 'idx_orders_plan');
            $table->index('status', 'idx_orders_status');
        });
    }
};