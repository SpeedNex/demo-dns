<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_configs', function (Blueprint $table): void {
            $table->string('key', 80)->primary();
            $table->json('value');
            $table->string('updated_by', 80)->nullable();
            $table->timestamps();
        });

        Schema::create('geo_dns_mappings', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('country', 2);
            $table->string('region', 80);
            $table->string('node_id', 40);
            $table->unsignedInteger('priority')->default(0);
            $table->unsignedInteger('weight')->default(100);
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            $table->foreign('node_id')->references('id')->on('nodes')->cascadeOnDelete();
            $table->index(['country', 'enabled']);
        });

        Schema::create('rule_sources', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('name', 100);
            $table->string('type', 40);
            $table->string('url', 500);
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('rule_count')->default(0);
            $table->timestamp('last_synced_at')->nullable();
            $table->string('last_sync_status', 30)->nullable();
            $table->text('last_sync_message')->nullable();
            $table->timestamps();
        });

        Schema::create('admin_audit_logs', function (Blueprint $table): void {
            $table->string('id', 40)->primary();
            $table->string('actor_id', 80)->nullable();
            $table->string('actor_name', 100)->nullable();
            $table->string('action', 80);
            $table->string('target_type', 80)->nullable();
            $table->string('target_id', 80)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('created_at');
            $table->index(['action', 'created_at']);
            $table->index(['target_type', 'target_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_audit_logs');
        Schema::dropIfExists('rule_sources');
        Schema::dropIfExists('geo_dns_mappings');
        Schema::dropIfExists('system_configs');
    }
};
