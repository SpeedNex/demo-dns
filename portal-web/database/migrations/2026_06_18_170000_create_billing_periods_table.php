<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UI.md #70 — 账单周期表。
 *
 * 出账窗口：每条 DNS usage 聚合到对应 period，period 关闭后生成 billing。
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('billing_periods', function (Blueprint $table) {
            $table->id();
            $table->string('user_id', 30);
            $table->string('currency', 3)->default('USD');
            $table->timestamp('period_start');
            $table->timestamp('period_end');
            $table->string('status', 20)->default('open')
                ->comment('open / closed / billed');
            $table->unsignedBigInteger('billing_id')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'period_start']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_periods');
    }
};
