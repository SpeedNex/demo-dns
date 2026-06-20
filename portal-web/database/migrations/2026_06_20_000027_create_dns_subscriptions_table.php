<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('plan_id');
            $table->enum('status', ['pending','active','past_due','cancelled','expired'])->default('pending');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->boolean('auto_renew')->default(true);
            $table->timestamps();
            $table->unique('order_id', 'uniq_subscriptions_order');
            $table->index('user_id', 'idx_subscriptions_user');
            $table->index('plan_id', 'idx_subscriptions_plan');
            $table->index('status', 'idx_subscriptions_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
