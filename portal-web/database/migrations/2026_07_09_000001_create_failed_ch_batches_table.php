<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('failed_ch_batches', static function (Blueprint $table): void {
            $table->id();
            $table->string('batch_id', 64)->unique()->comment('原始 batch_id，用于幂等去重');
            $table->unsignedInteger('node_id')->comment('上报节点 ID');
            $table->json('dns_logs')->comment('失败的 dns_logs 数据');
            $table->json('usage_events')->nullable()->comment('失败的 usage_events 数据');
            $table->string('error_message', 512)->nullable()->comment('失败原因');
            $table->unsignedTinyInteger('retry_count')->default(0)->comment('已重试次数');
            $table->timestamp('last_retried_at')->nullable()->comment('最后重试时间');
            $table->timestamp('resolved_at')->nullable()->comment('解决时间');
            $table->timestamps();

            $table->index(['resolved_at', 'retry_count'], 'idx_retry');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('failed_ch_batches');
    }
};
