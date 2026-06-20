<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('alerts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('code', 80);
            $table->enum('level', ['info','warning','error','critical'])->default('warning');
            $table->enum('source', ['node','billing','usage','system','security'])->default('system');
            $table->string('subject_type', 80)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('title', 200);
            $table->text('message')->nullable();
            $table->json('payload')->nullable();
            $table->enum('status', ['open','acknowledged','resolved','closed'])->default('open');
            $table->unsignedBigInteger('acknowledged_by')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index('code', 'idx_alerts_code');
            $table->index('status', 'idx_alerts_status');
            $table->index(['subject_type','subject_id'], 'idx_alerts_subject');
            $table->foreign('acknowledged_by', 'fk_alerts_acknowledged_by')->references('admin_id')->on('admins')->nullOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};
