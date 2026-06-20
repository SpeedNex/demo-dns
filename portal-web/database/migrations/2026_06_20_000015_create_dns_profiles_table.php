<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('profiles', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->char('profile_uid', 6);
            $table->unsignedBigInteger('user_id');
            $table->string('name', 120);
            $table->string('description', 500)->nullable();
            $table->boolean('is_default')->default(false);
            $table->enum('status', ['active','paused','closed'])->default('active');
            $table->boolean('security_enabled')->default(true);
            $table->boolean('privacy_enabled')->default(true);
            $table->boolean('parental_enabled')->default(false);
            $table->boolean('safesearch_enabled')->default(false);
            $table->integer('log_retention_days')->default(24);
            $table->integer('version')->default(1);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique('profile_uid', 'uniq_profiles_uid');
            $table->unique(['user_id','name'], 'uniq_profiles_user_name');
            $table->index('user_id', 'idx_profiles_user');
            $table->foreign('user_id', 'fk_profiles_user')->references('uid')->on('users')->cascadeOnDelete()->cascadeOnUpdate();
        });

        // profile_uid 必须是 6 位 hex。SQLite 不支持这里的 ALTER TABLE + REGEXP 约束，
        // 测试环境由应用层 Profile::generateProfileUid() 保证，MySQL 生产环境再加库约束。
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE dns_profiles ADD CONSTRAINT chk_profiles_uid
                CHECK (profile_uid REGEXP '^[0-9a-f]{6}$')");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('profiles');
    }
};
