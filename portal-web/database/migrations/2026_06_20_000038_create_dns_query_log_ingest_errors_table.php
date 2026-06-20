<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('query_log_ingest_errors', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('batch_id')->nullable();
            $table->unsignedBigInteger('node_id')->nullable();
            $table->string('error_type', 80);
            $table->string('error_message', 1000)->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->nullable();
            $table->index('batch_id', 'idx_ingest_errors_batch');
            $table->index('node_id', 'idx_ingest_errors_node');
            $table->foreign('batch_id', 'fk_ingest_errors_batch')->references('id')->on('query_log_ingest_batches')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('node_id', 'fk_ingest_errors_node')->references('id')->on('nodes')->nullOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('query_log_ingest_errors');
    }
};
