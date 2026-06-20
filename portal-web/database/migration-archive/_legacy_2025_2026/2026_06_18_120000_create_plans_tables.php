<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 120);
            $table->string('description', 255)->nullable();
            $table->string('status', 20)->default('active');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->string('badge', 50)->nullable();
            $table->json('features')->nullable();
            $table->json('limits')->nullable();
            $table->timestamps();
        });

        Schema::create('plan_prices', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('plan_id');
            $table->string('billing_cycle', 20)->default('monthly');
            $table->string('currency', 8)->default('USD');
            $table->bigInteger('amount_minor')->default(0);
            $table->bigInteger('original_amount_minor')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->unique(['plan_id', 'billing_cycle', 'currency'], 'uniq_plan_prices_plan_cycle_currency');
            $table->foreign('plan_id')->references('id')->on('plans')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_prices');
        Schema::dropIfExists('plans');
    }
};
