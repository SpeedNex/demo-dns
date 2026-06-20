<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('user_id');
            $table->string('provider', 40);
            $table->string('provider_session_id', 255)->nullable();
            $table->string('provider_payment_intent_id', 255)->nullable();
            $table->string('provider_charge_id', 255)->nullable();
            $table->char('currency', 3)->default('USD');
            $table->unsignedBigInteger('amount_minor');
            $table->enum('status', ['created','processing','succeeded','failed','cancelled','refunded'])->default('created');
            $table->string('failure_code', 80)->nullable();
            $table->string('failure_message', 500)->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();
            $table->index(['provider','provider_session_id'], 'uniq_pt_session');
            $table->index(['provider','provider_payment_intent_id'], 'uniq_pt_intent');
            $table->index('order_id', 'idx_pt_order');
            $table->index('user_id', 'idx_pt_user');
            $table->index('status', 'idx_pt_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
