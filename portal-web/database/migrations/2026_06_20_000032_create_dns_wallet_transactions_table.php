<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('wallet_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('billing_id')->nullable();
            $table->string('transaction_no', 40);
            $table->enum('type', ['credit','debit','refund','adjustment']);
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3)->default('USD');
            $table->unsignedBigInteger('balance_after_minor');
            $table->enum('source', ['topup','subscription','usage','refund','manual'])->default('topup');
            $table->string('description', 255)->nullable();
            $table->string('idempotency_key', 80);
            $table->enum('status', ['pending','succeeded','failed','cancelled'])->default('pending');
            $table->timestamps();
            $table->unique('transaction_no', 'uniq_wallet_tx_no');
            $table->unique('idempotency_key', 'uniq_wallet_tx_idempotency');
            $table->index('wallet_id', 'idx_wallet_tx_wallet');
            $table->index('user_id', 'idx_wallet_tx_user');
            $table->index('billing_id', 'idx_wallet_tx_billing');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
