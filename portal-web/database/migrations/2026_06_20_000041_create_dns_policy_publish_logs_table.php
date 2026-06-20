<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('policy_publish_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('profile_id');
            $table->unsignedBigInteger('policy_snapshot_id')->nullable();
            $table->enum('action', ['publish','rollback','republish'])->default('publish');
            $table->enum('status', ['pending','success','failed'])->default('pending');
            $table->integer('target_node_count')->default(0);
            $table->integer('success_node_count')->default(0);
            $table->string('error_message', 500)->nullable();
            $table->unsignedBigInteger('published_by')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index('profile_id', 'idx_policy_logs_profile');
            $table->index('policy_snapshot_id', 'idx_policy_logs_snapshot');
            $table->foreign('profile_id', 'fk_policy_logs_profile')->references('id')->on('profiles')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('policy_snapshot_id', 'fk_policy_logs_snapshot')->references('id')->on('policy_snapshots')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('published_by', 'fk_policy_logs_publisher')->references('admin_id')->on('admins')->nullOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('policy_publish_logs');
    }
};
