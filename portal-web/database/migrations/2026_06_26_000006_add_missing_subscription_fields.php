<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 补充 dns_subscriptions 表缺失字段：
 *   - monthly_query_limit (月度配额限制)
 *   - grace_until (宽限期截止)
 *   - suspended_at (暂停时间)
 *   同时扩展 status 枚举，添加 trialing 和 suspended 状态。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('subscriptions', 'monthly_query_limit')) {
                $table->unsignedBigInteger('monthly_query_limit')->nullable()->after('plan_code');
            }
            if (! Schema::hasColumn('subscriptions', 'grace_until')) {
                $table->timestamp('grace_until')->nullable()->after('current_period_end');
            }
            if (! Schema::hasColumn('subscriptions', 'suspended_at')) {
                $table->timestamp('suspended_at')->nullable()->after('grace_until');
            }
        });

        // 扩展 status 枚举：添加 trialing 和 suspended
        DB::statement("ALTER TABLE dns_subscriptions MODIFY COLUMN status ENUM('pending','active','trialing','past_due','suspended','cancelled','expired') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        // 恢复原始枚举
        DB::statement("ALTER TABLE dns_subscriptions MODIFY COLUMN status ENUM('pending','active','past_due','cancelled','expired') NOT NULL DEFAULT 'pending'");

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['suspended_at', 'grace_until', 'monthly_query_limit']);
        });
    }
};