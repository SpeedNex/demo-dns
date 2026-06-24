<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('task_executions', function (Blueprint $table) {
            // UI.md P0#1: 真实库使用字符串主键 (PublishService 生成 texec_xxx)，
            // 与 TaskExecution::$keyType='string' 和 booted() ULID 生成对齐。
            $table->string('id', 32)->primary();
            $table->unsignedBigInteger('publish_task_id');
            $table->unsignedBigInteger('node_id');
            $table->integer('config_version')->default(0);
            $table->enum('status', ['pending', 'pulled', 'applied', 'ack', 'failed', 'skipped'])->default('pending');
            $table->string('checksum', 128)->nullable();
            $table->string('error_code', 64)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('pulled_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
            $table->index('publish_task_id', 'idx_task_exec_pubtask');
            $table->index('node_id', 'idx_task_exec_node');
            $table->index('status', 'idx_task_exec_status');
            $table->foreign('publish_task_id', 'fk_task_exec_pubtask')
                ->references('id')->on('publish_tasks')
                ->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('node_id', 'fk_task_exec_node')
                ->references('id')->on('nodes')
                ->cascadeOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_executions');
    }
};
