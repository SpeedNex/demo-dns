<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 为 subscriptions 表添加 quota_status 字段。
 * 用于 Free 套餐超额自动停用机制：
 *   - normal: 正常（未超额）
 *   - exceeded: 查询量超过套餐月度限额
 *
 * quota:check Command 每5分钟检测用量，超标时更新此字段，
 * 并触发 Profile 重新发布，使 resolver 在下次拉取配置后拒绝解析。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('subscriptions') && ! Schema::hasColumn('subscriptions', 'quota_status')) {
            Schema::table('subscriptions', function (Blueprint $table): void {
                $table->string('quota_status', 20)->default('normal')->after('status');
                $table->index('quota_status', 'idx_subscriptions_quota_status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('subscriptions') && Schema::hasColumn('subscriptions', 'quota_status')) {
            Schema::table('subscriptions', function (Blueprint $table): void {
                $table->dropIndex('idx_subscriptions_quota_status');
                $table->dropColumn('quota_status');
            });
        }
    }
};