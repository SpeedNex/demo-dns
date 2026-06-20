<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('balance_minor')->default(0);
            $table->unsignedBigInteger('frozen_minor')->default(0);
            $table->char('currency', 3)->default('USD');
            $table->enum('status', ['active','frozen','closed'])->default('active');
            $table->timestamps();
            $table->unique('user_id', 'uniq_wallets_user');
            $table->foreign('user_id', 'fk_wallets_user')->references('uid')->on('users')->cascadeOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
