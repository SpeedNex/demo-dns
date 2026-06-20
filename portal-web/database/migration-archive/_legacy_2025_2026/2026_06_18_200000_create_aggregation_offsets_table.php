<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UI.md #68 — 聚合偏移量表。
 *
 * 记录 job 最后处理位置，支持重跑/补算，防止数据丢失。
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('aggregation_offsets', function (Blueprint $table) {
            $table->id();
            $table->string('job_type', 64)->unique()
                ->comment('usage_aggregation / billing_generation / policy_publish / finance_verify');
            $table->timestamp('last_processed_at')->nullable();
            $table->string('last_processed_id', 64)->nullable();
            $table->string('status', 20)->default('idle')->comment('idle / running / failed');
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aggregation_offsets');
    }
};
