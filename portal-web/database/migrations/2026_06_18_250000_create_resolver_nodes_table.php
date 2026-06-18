<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UI.md #61 — Resolver 节点版本控制。
 *
 * 后台按 policy_version 定位策略未生效原因。
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('resolver_nodes', function (Blueprint $table) {
            $table->id();
            $table->string('node_id', 50)->unique();
            $table->string('node_name', 100);
            $table->string('region', 20)->nullable();
            $table->unsignedBigInteger('policy_version')->default(0);
            $table->timestamp('last_sync_at')->nullable();
            $table->string('status', 20)->default('offline')
                ->comment('online / offline / error');
            $table->string('ip_address', 45)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('policy_version');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resolver_nodes');
    }
};
