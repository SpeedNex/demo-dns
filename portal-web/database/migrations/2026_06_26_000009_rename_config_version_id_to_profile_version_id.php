<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 检查表是否存在且有 config_version_id 字段
        if (Schema::hasColumn('dns_publish_tasks', 'config_version_id')) {
            // 1. 先删除旧外键
            Schema::table('dns_publish_tasks', function (Blueprint $table) {
                $table->dropForeign('fk_publish_tasks_cv');
                $table->dropIndex('idx_publish_tasks_cv');
            });

            // 2. 重命名字段
            DB::statement('ALTER TABLE dns_publish_tasks CHANGE COLUMN config_version_id profile_version_id BIGINT UNSIGNED NOT NULL');

            // 3. 添加新外键
            Schema::table('dns_publish_tasks', function (Blueprint $table) {
                $table->index('profile_version_id', 'idx_publish_tasks_pv');
                $table->foreign('profile_version_id', 'fk_publish_tasks_pv')
                    ->references('id')
                    ->on('profile_versions')
                    ->cascadeOnDelete()
                    ->cascadeOnUpdate();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('dns_publish_tasks', 'profile_version_id')) {
            // 回滚：删除新外键
            Schema::table('dns_publish_tasks', function (Blueprint $table) {
                $table->dropForeign('fk_publish_tasks_pv');
                $table->dropIndex('idx_publish_tasks_pv');
            });

            // 回滚：重命名字段
            DB::statement('ALTER TABLE dns_publish_tasks CHANGE COLUMN profile_version_id config_version_id BIGINT UNSIGNED NOT NULL');

            // 回滚：添加旧外键
            Schema::table('dns_publish_tasks', function (Blueprint $table) {
                $table->index('config_version_id', 'idx_publish_tasks_cv');
                $table->foreign('config_version_id', 'fk_publish_tasks_cv')
                    ->references('id')
                    ->on('profile_versions')
                    ->cascadeOnDelete()
                    ->cascadeOnUpdate();
            });
        }
    }
};
