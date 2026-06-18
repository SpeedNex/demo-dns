<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // UI.md #53: 支付中心
        // 状态机：pending → success → failed → refunded
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('user_id', 36);
            $table->unsignedBigInteger('order_id')->nullable();
            $table->string('provider', 30)->default('stripe');
            $table->string('provider_session_id', 200)->nullable()->comment('Stripe Checkout Session id');
            $table->string('provider_payment_intent_id', 200)->nullable();
            $table->string('status', 20)->default('pending')->comment('pending / success / failed / refunded');
            $table->bigInteger('amount_minor');
            $table->string('currency', 3)->default('USD');
            $table->json('meta')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('order_id');
            $table->index('status');
            $table->index('provider_session_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
