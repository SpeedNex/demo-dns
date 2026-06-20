<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('node_heartbeats', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('node_id');
            $table->enum('status', ['online','degraded','offline'])->default('online');
            $table->unsignedBigInteger('uptime_seconds')->default(0);
            $table->string('version', 40)->nullable();
            $table->integer('current_config_version')->default(0);
            $table->integer('profiles_loaded')->default(0);
            $table->timestamp('last_config_pull_at')->nullable();
            $table->timestamp('last_log_flush_at')->nullable();
            $table->timestamp('reported_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index('node_id', 'idx_node_heartbeats_node');
            $table->index('reported_at', 'idx_node_heartbeats_reported');
            $table->foreign('node_id', 'fk_node_heartbeats_node')->references('id')->on('nodes')->cascadeOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('node_heartbeats');
    }
};
