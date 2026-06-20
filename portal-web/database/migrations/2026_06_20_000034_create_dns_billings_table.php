<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('billings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('billing_no', 40);
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('subscription_id')->nullable();
            $table->unsignedBigInteger('billing_period_id')->nullable();
            $table->char('currency', 3)->default('USD');
            $table->unsignedBigInteger('subtotal_minor')->default(0);
            $table->unsignedBigInteger('discount_minor')->default(0);
            $table->unsignedBigInteger('tax_minor')->default(0);
            $table->unsignedBigInteger('total_minor');
            $table->enum('status', ['draft','pending','paid','overdue','cancelled'])->default('draft');
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->unique('billing_no', 'uniq_billings_no');
            $table->index('user_id', 'idx_billings_user');
            $table->index('subscription_id', 'idx_billings_subscription');
            $table->index('billing_period_id', 'idx_billings_period');
            $table->index('status', 'idx_billings_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billings');
    }
};
