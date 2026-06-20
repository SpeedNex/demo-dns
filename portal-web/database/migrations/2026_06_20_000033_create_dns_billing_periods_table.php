<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('billing_periods', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->dateTime('period_start');
            $table->dateTime('period_end');
            $table->enum('status', ['open','closed','billed'])->default('open');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id','period_start','period_end'], 'uniq_billing_period');
            $table->index('user_id', 'idx_billing_periods_user');
            $table->index('status', 'idx_billing_periods_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_periods');
    }
};
