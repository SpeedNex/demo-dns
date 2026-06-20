<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('usage_records', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('profile_id');
            $table->unsignedBigInteger('device_id')->nullable();
            $table->string('billing_category', 30)->default('query');
            $table->unsignedBigInteger('billing_period_id');
            $table->unsignedBigInteger('query_count')->default(0);
            $table->unsignedBigInteger('blocked_count')->default(0);
            $table->unsignedBigInteger('amount_minor')->default(0);
            $table->char('currency', 3)->default('USD');
            $table->timestamp('last_aggregated_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['user_id', 'profile_id', 'device_id', 'billing_category', 'billing_period_id'],
                'uniq_usage_aggregate'
            );
            $table->index('user_id', 'idx_usage_user');
            $table->index('profile_id', 'idx_usage_profile');
            $table->index('device_id', 'idx_usage_device');
            $table->index('billing_period_id', 'idx_usage_period');

            $table->foreign('user_id', 'fk_usage_user')
                ->references('uid')->on('users')
                ->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('profile_id', 'fk_usage_profile')
                ->references('id')->on('profiles')
                ->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('billing_period_id', 'fk_usage_period')
                ->references('id')->on('billing_periods')
                ->restrictOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_records');
    }
};
