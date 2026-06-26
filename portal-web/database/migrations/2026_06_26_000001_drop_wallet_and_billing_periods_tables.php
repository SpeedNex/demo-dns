<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 删除 SaaS 订阅模式中不需要的预付费钱包体系表：
 *   - dns_wallets          钱包余额
 *   - dns_wallet_transactions  钱包流水（含退款记录）
 *
 * 注意：dns_billing_periods 保留不动，QuotaCheckCommand / UsageBillingService 仍在使用。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('wallets');
    }

    public function down(): void
    {
        // 重建 dns_wallets
        Schema::create('wallets', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('balance_minor')->default(0);
            $table->unsignedBigInteger('frozen_minor')->default(0);
            $table->char('currency', 3)->default('USD');
            $table->enum('status', ['active', 'frozen', 'closed'])->default('active');
            $table->timestamps();
            $table->unique('user_id', 'uniq_wallets_user');
            $table->foreign('user_id', 'fk_wallets_user')->references('uid')->on('users')->cascadeOnDelete()->cascadeOnUpdate();
        });

        // 重建 dns_wallet_transactions
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('wallet_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('billing_id')->nullable();
            $table->string('transaction_no', 40);
            $table->enum('type', ['credit', 'debit', 'refund', 'adjustment']);
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3)->default('USD');
            $table->unsignedBigInteger('balance_after_minor');
            $table->enum('source', ['topup', 'subscription', 'usage', 'refund', 'manual'])->default('topup');
            $table->string('description', 255)->nullable();
            $table->string('idempotency_key', 80);
            $table->enum('status', ['pending', 'succeeded', 'failed', 'cancelled'])->default('pending');
            $table->timestamps();
            $table->unique('transaction_no', 'uniq_wallet_tx_no');
            $table->unique('idempotency_key', 'uniq_wallet_tx_idempotency');
            $table->index('wallet_id', 'idx_wallet_tx_wallet');
            $table->index('user_id', 'idx_wallet_tx_user');
            $table->index('billing_id', 'idx_wallet_tx_billing');
        });
    }
};