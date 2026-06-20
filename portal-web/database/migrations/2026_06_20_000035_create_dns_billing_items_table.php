<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('billing_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('billing_id');
            $table->enum('item_type', ['subscription','usage','wallet_topup','credit','adjustment']);
            $table->enum('source_type', ['subscription','usage_record','wallet_transaction'])->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('description', 255)->nullable();
            $table->decimal('quantity', 20, 4)->default(1);
            $table->unsignedBigInteger('unit_price_minor')->default(0);
            $table->unsignedBigInteger('amount_minor');
            $table->timestamps();
            $table->index('billing_id', 'idx_billing_items_billing');
            $table->index(['source_type','source_id'], 'idx_billing_items_source');
            $table->foreign('billing_id', 'fk_billing_items_billing')->references('id')->on('billings')->cascadeOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_items');
    }
};
