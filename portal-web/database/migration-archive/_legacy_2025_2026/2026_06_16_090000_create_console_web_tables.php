<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nodes', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('node_name', 100)->unique();
            $table->string('status', 30)->default('pending');
            $table->string('region', 80);
            $table->string('country', 2)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('provider', 80)->nullable();
            $table->string('public_ipv4', 45)->nullable();
            $table->string('public_ipv6', 45)->nullable();
            $table->string('hostname', 255)->nullable();
            $table->json('supported_protocols');
            $table->string('version', 50)->nullable();
            $table->unsignedBigInteger('current_config_version')->default(0);
            $table->unsignedBigInteger('desired_config_version')->default(0);
            $table->unsignedInteger('weight')->default(100);
            $table->unsignedInteger('capacity_qps')->default(5000);
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('disabled_at')->nullable();
            $table->json('labels');
            $table->timestamps();
        });

        Schema::create('node_tokens', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('node_id', 40);
            $table->string('token_hash', 255)->unique();
            $table->string('name', 100)->default('default');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('created_at');
            $table->unique(['node_id', 'name']);
            $table->foreign('node_id')->references('id')->on('nodes')->cascadeOnDelete();
        });

        Schema::create('node_heartbeats', function (Blueprint $table): void {
            $table->id();
            $table->string('node_id', 40);
            $table->string('status', 30);
            $table->unsignedBigInteger('uptime_seconds')->default(0);
            $table->string('version', 50)->nullable();
            $table->unsignedBigInteger('current_config_version')->default(0);
            $table->unsignedInteger('profiles_loaded')->default(0);
            $table->timestamp('last_config_pull_at')->nullable();
            $table->timestamp('last_log_flush_at')->nullable();
            $table->timestamp('reported_at');
            $table->timestamp('created_at');
            $table->foreign('node_id')->references('id')->on('nodes')->cascadeOnDelete();
        });

        Schema::create('config_versions', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->unsignedBigInteger('version')->unique();
            $table->string('profile_id', 40);
            $table->unsignedBigInteger('profile_version');
            $table->string('user_id', 40);
            $table->string('team_id', 40)->nullable();
            $table->string('status', 30)->default('ready');
            $table->string('checksum', 100);
            $table->json('config_json');
            $table->unsignedInteger('config_size_bytes')->default(0);
            $table->string('generated_by', 50)->default('portal-web');
            $table->timestamp('generated_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('publish_tasks', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('config_version_id', 40);
            $table->string('profile_id', 40);
            $table->string('status', 30)->default('queued');
            $table->string('target_scope', 30)->default('all_nodes');
            $table->json('target_filter');
            $table->unsignedInteger('target_node_count')->default(0);
            $table->unsignedInteger('applied_node_count')->default(0);
            $table->unsignedInteger('failed_node_count')->default(0);
            $table->unsignedInteger('retry_count')->default(0);
            $table->string('message', 255)->nullable();
            $table->text('latest_error')->nullable();
            $table->timestamp('queued_at');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->foreign('config_version_id')->references('id')->on('config_versions')->cascadeOnDelete();
        });

        Schema::create('task_executions', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('publish_task_id', 40);
            $table->string('node_id', 40);
            $table->unsignedBigInteger('config_version');
            $table->string('status', 30)->default('pending');
            $table->string('checksum', 100)->nullable();
            $table->string('error_code', 80)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('pulled_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
            $table->unique(['publish_task_id', 'node_id']);
            $table->foreign('publish_task_id')->references('id')->on('publish_tasks')->cascadeOnDelete();
            $table->foreign('node_id')->references('id')->on('nodes')->cascadeOnDelete();
        });

        Schema::create('query_log_ingest_batches', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('batch_id', 100)->unique();
            $table->string('node_id', 40);
            $table->unsignedInteger('item_count');
            $table->string('content_sha256', 100);
            $table->timestamp('usage_exported_at')->nullable();
            $table->string('status', 30)->default('accepted');
            $table->text('error_message')->nullable();
            $table->timestamp('received_at');
            $table->timestamp('written_at')->nullable();
            $table->foreign('node_id')->references('id')->on('nodes')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('query_log_ingest_batches');
        Schema::dropIfExists('task_executions');
        Schema::dropIfExists('publish_tasks');
        Schema::dropIfExists('config_versions');
        Schema::dropIfExists('node_heartbeats');
        Schema::dropIfExists('node_tokens');
        Schema::dropIfExists('nodes');
    }
};
