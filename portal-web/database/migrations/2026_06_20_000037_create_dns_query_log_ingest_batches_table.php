<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('query_log_ingest_batches', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('node_id')->nullable();
            $table->string('batch_id', 80);
            $table->integer('event_count')->default(0);
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->enum('status', ['received','processing','succeeded','failed','partial'])->default('received');
            $table->string('error_message', 500)->nullable();
            $table->boolean('forwarded_to_clickhouse')->default(false);
            $table->timestamps();
            $table->unique('batch_id', 'uniq_ingest_batch');
            $table->index('node_id', 'idx_ingest_node');
            $table->index('status', 'idx_ingest_status');
            $table->foreign('node_id', 'fk_ingest_node')->references('id')->on('nodes')->nullOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('query_log_ingest_batches');
    }
};
