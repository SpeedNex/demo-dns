<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('publish_tasks', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('config_version_id');
            $table->unsignedBigInteger('profile_id')->nullable();
            $table->enum('status', ['queued', 'running', 'succeeded', 'partial', 'failed'])->default('queued');
            $table->enum('target_scope', ['all_nodes', 'profile', 'tag', 'node'])->default('all_nodes');
            $table->json('target_filter')->nullable();
            $table->integer('target_node_count')->default(0);
            $table->integer('applied_node_count')->default(0);
            $table->integer('failed_node_count')->default(0);
            $table->integer('retry_count')->default(0);
            $table->string('message', 500)->nullable();
            $table->text('latest_error')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index('status', 'idx_publish_tasks_status');
            $table->index('config_version_id', 'idx_publish_tasks_cv');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('publish_tasks');
    }
};
