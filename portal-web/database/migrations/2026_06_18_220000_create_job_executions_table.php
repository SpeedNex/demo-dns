<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UI.md #83 — Job 执行记录表。
 *
 * 覆盖：usage_aggregation / billing_generation / policy_publish / finance_verify。
 * 连续失败 3 次 → 触发告警 (#84)。
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('job_executions', function (Blueprint $table) {
            $table->id();
            $table->string('job_type', 64);
            $table->string('status', 20)->default('pending')
                ->comment('pending / running / success / failed');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['job_type', 'started_at']);
            $table->index(['job_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_executions');
    }
};
