<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 恢复 dns_billing_periods 表。
 * 该表在 2026_06_26_000001 中被误删，但 QuotaCheckCommand / UsageBillingService 仍在使用。
 * 用于按月份追踪用户用量，是超额计费和配额检查的核心依赖。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_periods', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->dateTime('period_start');
            $table->dateTime('period_end');
            $table->enum('status', ['open', 'closed', 'billed'])->default('open');
            $table->timestamp('closed_at')->nullable();
            $table->unsignedBigInteger('billing_id')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'period_start', 'period_end'], 'uniq_billing_period');
            $table->index('user_id', 'idx_billing_periods_user');
            $table->index('status', 'idx_billing_periods_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_periods');
    }
};