<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * UI.md P0#6: dns_admin_audit_logs.actor_admin_id 当前是 NOT NULL + FK→admins。
 * 系统级操作 (JobRunner::alert 等) 的 actorId 是字符串 'system'，无法映射到 admins 行，
 * 旧实现 (int)'system' = 0 触发 FK 违反或伪造超级管理员。
 *
 * 修复：
 * 1) 解除 FK + NOT NULL，使 actor_admin_id 可空（系统级 actor 留 null）
 * 2) 在 AdminAuditLog::record() 中：数字 actorId → 正常 FK；字符串 actorId → 留 null + actor_username 填字符串
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('admin_audit_logs')) {
            return;
        }

        // 1) 解除 FK（避免修改可空性时 MySQL 8 触发 3780 错误）
        Schema::table('admin_audit_logs', function (Blueprint $table) {
            $table->dropForeign('fk_audit_actor');
        });

        // 2) 改列为 nullable
        Schema::table('admin_audit_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('actor_admin_id')->nullable()->change();
        });

        // 3) 重建 FK（这次允许 NULL 端点）
        Schema::table('admin_audit_logs', function (Blueprint $table) {
            $table->foreign('actor_admin_id', 'fk_audit_actor')
                ->references('admin_id')->on('admins')
                ->restrictOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('admin_audit_logs')) {
            return;
        }

        Schema::table('admin_audit_logs', function (Blueprint $table) {
            $table->dropForeign('fk_audit_actor');
        });
        Schema::table('admin_audit_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('actor_admin_id')->nullable(false)->change();
        });
        Schema::table('admin_audit_logs', function (Blueprint $table) {
            $table->foreign('actor_admin_id', 'fk_audit_actor')
                ->references('admin_id')->on('admins')
                ->restrictOnDelete()->cascadeOnUpdate();
        });
    }
};
