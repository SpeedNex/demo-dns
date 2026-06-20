<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('query_log_entries', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('ingest_batch_id')->nullable();
            $table->unsignedBigInteger('node_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('profile_id')->nullable();
            $table->unsignedBigInteger('device_id')->nullable();
            $table->string('query_name', 255);
            $table->string('query_type', 16)->nullable();
            $table->string('action', 32)->nullable();
            $table->string('reason', 64)->nullable();
            $table->string('category', 64)->nullable();
            $table->string('client_ip', 64)->nullable();
            $table->integer('rcode')->default(0);
            $table->integer('latency_ms')->default(0);
            $table->timestamp('queried_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['user_id', 'queried_at'], 'idx_qle_user_time');
            $table->index(['profile_id', 'queried_at'], 'idx_qle_profile_time');
            $table->index('query_name', 'idx_qle_qname');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('query_log_entries');
    }
};
