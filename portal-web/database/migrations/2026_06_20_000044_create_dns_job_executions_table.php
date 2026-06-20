<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('job_executions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('job_name', 80);
            $table->dateTime('started_at');
            $table->dateTime('finished_at')->nullable();
            $table->enum('status', ['running','succeeded','failed'])->default('running');
            $table->integer('duration_ms')->nullable();
            $table->string('error_message', 1000)->nullable();
            $table->json('meta')->nullable();
            $table->index('job_name', 'idx_job_exec_name');
            $table->index('status', 'idx_job_exec_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_executions');
    }
};
