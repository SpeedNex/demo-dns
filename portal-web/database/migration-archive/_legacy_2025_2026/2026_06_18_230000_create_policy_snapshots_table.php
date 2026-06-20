<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UI.md #63 — Policy Snapshot。
 *
 * Resolver 不再实时查询多个业务表拼接策略，
 * 改为读取 policy_snapshots.payload_json。
 *
 * 状态机：draft → published
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('policy_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('user_id', 30);
            $table->unsignedBigInteger('version');
            $table->json('payload_json');
            $table->string('status', 20)->default('draft')
                ->comment('draft / published');
            $table->timestamp('published_at')->nullable();
            $table->string('published_by', 50)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'version']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('policy_snapshots');
    }
};
