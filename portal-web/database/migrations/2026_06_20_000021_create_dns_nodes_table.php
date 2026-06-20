<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('nodes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('node_code', 64);
            $table->string('name', 120);
            $table->string('region', 40)->nullable();
            $table->string('country', 8)->nullable();
            $table->string('city', 80)->nullable();
            $table->string('public_ipv4', 45)->nullable();
            $table->string('public_ipv6', 64)->nullable();
            $table->json('supported_protocols')->nullable();
            $table->enum('status', ['pending','online','offline','degraded','maintenance','disabled','retired'])->default('pending');
            $table->integer('desired_config_version')->default(1);
            $table->integer('current_config_version')->default(0);
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->timestamp('last_log_flush_at')->nullable();
            $table->json('meta')->nullable();
            $table->unsignedBigInteger('created_by_admin_id')->nullable();
            $table->timestamps();
            $table->unique('node_code', 'uniq_nodes_code');
            $table->index('status', 'idx_nodes_status');
            $table->index('region', 'idx_nodes_region');
            $table->foreign('created_by_admin_id', 'fk_nodes_creator')->references('admin_id')->on('admins')->nullOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nodes');
    }
};
