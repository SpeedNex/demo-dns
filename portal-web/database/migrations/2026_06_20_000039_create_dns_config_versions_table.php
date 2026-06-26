<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('profile_versions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->enum('target_scope', ['global','node','profile'])->default('node');
            $table->unsignedBigInteger('target_node_id')->nullable();
            $table->unsignedBigInteger('target_profile_id')->nullable();
            $table->integer('version');
            $table->json('config_json');
            $table->string('checksum', 64);
            $table->unsignedBigInteger('published_by')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->unique(['target_scope','target_node_id','target_profile_id','version'], 'uniq_profile_version');
            $table->index('target_node_id', 'idx_profile_versions_node');
            $table->index('target_profile_id', 'idx_profile_versions_profile');
            $table->foreign('target_node_id', 'fk_profile_versions_node')->references('id')->on('nodes')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('target_profile_id', 'fk_profile_versions_profile')->references('id')->on('profiles')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('published_by', 'fk_profile_versions_publisher')->references('admin_id')->on('admins')->nullOnDelete()->cascadeOnUpdate();
        });

        // V2.3: MySQL 8 不允许 CHECK 约束和 FK 引用同列 (ERROR 3823)
        // chk_profile_target 的语义由应用层 ProfileVersionObserver::saving 校验保证
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_versions');
    }
};
