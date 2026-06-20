<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('plan_prices', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('plan_id');
            $table->enum('billing_cycle', ['monthly','yearly'])->default('monthly');
            $table->char('currency', 3)->default('USD');
            $table->unsignedBigInteger('amount_minor');
            $table->string('stripe_price_id', 120)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['plan_id','billing_cycle','currency'], 'uniq_plan_price');
            $table->foreign('plan_id', 'fk_plan_prices_plan')->references('id')->on('plans')->cascadeOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_prices');
    }
};
