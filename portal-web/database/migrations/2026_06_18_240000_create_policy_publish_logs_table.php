<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UI.md #62 — Policy 发布日志。
 *
 * 记录：snapshot → publish → node → ack
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('policy_publish_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('snapshot_id');
            $table->string('node_id', 50);
            $table->string('status', 20)->default('pending')
                ->comment('pending / acked / failed');
            $table->timestamp('ack_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['snapshot_id', 'node_id']);
            $table->index(['node_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('policy_publish_logs');
    }
};
