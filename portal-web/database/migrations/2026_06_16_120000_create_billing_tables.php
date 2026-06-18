<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('user_id', 36);
            $table->string('type', 30)->comment('charge / refund / usage_deduction / upgrade / downgrade');
            $table->bigInteger('amount_minor');
            $table->string('currency', 3)->default('CNY');
            $table->string('description', 255)->nullable();
            $table->string('status', 20)->default('completed');
            $table->string('reference_type', 50)->nullable();
            $table->string('reference_id', 100)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('type');
            $table->index('created_at');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('user_id', 36);
            $table->string('invoice_no', 50)->unique();
            $table->bigInteger('amount_minor');
            $table->string('currency', 3)->default('CNY');
            $table->string('status', 20)->default('pending')->comment('pending / paid / cancelled / refunded');
            $table->string('type', 30)->default('subscription')->comment('subscription / charge / refund');
            $table->string('description', 255)->nullable();
            $table->boolean('finalized')->default(false);
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('status');
            $table->index('created_at');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('user_id', 36)->unique();
            $table->string('plan_code', 30)->default('free');
            $table->string('status', 20)->default('active');
            $table->bigInteger('monthly_query_limit')->nullable()->comment('null = unlimited');
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('usage_records', function (Blueprint $table) {
            $table->id();
            $table->string('user_id', 36);
            $table->string('plan_code', 30)->default('free');
            $table->string('period', 7)->comment('YYYY-MM');
            $table->bigInteger('query_count')->default(0);
            $table->bigInteger('blocked_count')->default(0);
            $table->timestamp('calculated_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'period']);
            $table->index('period');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_records');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('wallet_transactions');
    }
};
