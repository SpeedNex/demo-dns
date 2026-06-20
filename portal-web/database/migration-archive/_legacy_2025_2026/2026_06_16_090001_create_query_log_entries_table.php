<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('query_log_entries', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('ingest_batch_id', 40);
            $table->string('node_id', 40);
            $table->string('user_id', 40)->nullable();
            $table->string('profile_id', 40)->nullable();
            $table->string('device_id', 80)->nullable();
            $table->string('query_name', 255);
            $table->string('query_type', 20)->nullable();
            $table->string('action', 20);
            $table->string('reason', 80)->nullable();
            $table->string('category', 80)->nullable();
            $table->string('client_ip', 64)->nullable();
            $table->unsignedInteger('rcode')->default(0);
            $table->unsignedInteger('latency_ms')->default(0);
            $table->timestamp('queried_at')->nullable();
            $table->timestamp('created_at');

            $table->foreign('ingest_batch_id')->references('id')->on('query_log_ingest_batches')->cascadeOnDelete();
            $table->foreign('node_id')->references('id')->on('nodes')->cascadeOnDelete();
            $table->index(['user_id', 'queried_at'], 'idx_query_log_entries_user_time');
            $table->index(['profile_id', 'queried_at'], 'idx_query_log_entries_profile_time');
            $table->index(['action', 'queried_at'], 'idx_query_log_entries_action_time');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('query_log_entries');
    }
};
