<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // UI.md #51: 订单中心
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('user_id', 30);
            $table->string('order_no', 50)->unique();
            $table->string('plan_code', 30);
            $table->string('status', 20)->default('pending')->comment('pending / paid / cancelled / refunded');
            $table->bigInteger('payable_amount_minor');
            $table->string('currency', 3)->default('USD');
            $table->string('description', 255)->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('status');
            $table->index('created_at');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
