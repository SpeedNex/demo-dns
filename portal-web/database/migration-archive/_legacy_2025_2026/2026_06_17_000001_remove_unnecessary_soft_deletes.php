<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * P2: 移除不必要的软删除字段
     *
     * 审查报告建议：
     * - team_members: 成员退出直接 DELETE，历史由 team 自身的审计日志追踪
     * - profile_rules: 历史在 profile_versions 中，不需要软删除
     *
     * 注意：如果业务需要"误删恢复"能力，请跳过此迁移
     */
    public function up(): void
    {
        // team_members: 移除软删除，成员退出直接 DELETE
        if (Schema::hasColumn('team_members', 'deleted_at')) {
            Schema::table('team_members', function (Blueprint $table): void {
                $table->dropSoftDeletes();
            });
        }

        // profile_rules: 移除软删除，历史由 profile_versions 承载
        if (Schema::hasColumn('profile_rules', 'deleted_at')) {
            Schema::table('profile_rules', function (Blueprint $table): void {
                $table->dropSoftDeletes();
            });
        }

        // teams/profiles/users 软删除保留（用户要求）
        // 如需移除，取消下方注释：
        // Schema::table('teams', function (Blueprint $table): void {
        //     $table->dropSoftDeletes();
        // });
        // Schema::table('profiles', function (Blueprint $table): void {
        //     $table->dropSoftDeletes();
        // });
        // Schema::table('users', function (Blueprint $table): void {
        //     $table->dropSoftDeletes();
        // });
    }

    public function down(): void
    {
        // 如果需要回滚，需要重新添加软删除列
        // 注意：这不能恢复已删除的数据
        Schema::table('team_members', function (Blueprint $table): void {
            $table->softDeletes();
        });

        Schema::table('profile_rules', function (Blueprint $table): void {
            $table->softDeletes();
        });
    }
};
