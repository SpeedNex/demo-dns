<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('admin_audit_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('actor_admin_id');
            $table->string('actor_username', 100)->nullable();
            $table->string('action', 80);
            $table->string('target_type', 80)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('created_at');
            $table->index('actor_admin_id', 'idx_audit_actor');
            $table->index('action', 'idx_audit_action');
            $table->index(['target_type','target_id'], 'idx_audit_target');
            $table->index('created_at', 'idx_audit_created');
            $table->foreign('actor_admin_id', 'fk_audit_actor')->references('admin_id')->on('admins')->restrictOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_audit_logs');
    }
};
