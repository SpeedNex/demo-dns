<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UI.md #57 — 账单明细表。
 *
 * 规则：billing.amount = SUM(billing_items.amount)
 * item_type: plan / usage / adjustment
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('billing_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('billing_id');
            $table->string('item_type', 32)->comment('plan / usage / adjustment');
            $table->string('item_name', 255);
            $table->unsignedBigInteger('quantity')->default(1);
            $table->bigInteger('unit_price');
            $table->bigInteger('amount');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index('billing_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_items');
    }
};
