<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 钱包充值 (wallet_topup) 订单没有对应的 plan/plan_price，允许 plan_id 和 plan_price_id 为 NULL。
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('plan_id')->nullable()->change();
            $table->unsignedBigInteger('plan_price_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('plan_id')->nullable(false)->change();
            $table->unsignedBigInteger('plan_price_id')->nullable(false)->change();
        });
    }
};
