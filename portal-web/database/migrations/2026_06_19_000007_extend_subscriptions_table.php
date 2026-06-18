<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P0-7 修复：subscriptions 补齐 grace_until / suspended_at / expired_at / plan_code_old。
 * 完整状态机: active / trialing / past_due / suspended / expired / cancelled
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            if (! Schema::hasColumn('subscriptions', 'grace_until')) {
                $table->timestamp('grace_until')->nullable()->after('trial_ends_at');
            }
            if (! Schema::hasColumn('subscriptions', 'suspended_at')) {
                $table->timestamp('suspended_at')->nullable()->after('cancelled_at');
            }
            if (! Schema::hasColumn('subscriptions', 'expired_at')) {
                $table->timestamp('expired_at')->nullable()->after('suspended_at');
            }
            if (! Schema::hasColumn('subscriptions', 'plan_code_old')) {
                $table->string('plan_code_old', 30)->nullable()->after('plan_code');
            }
        });
    }

    public function down(): void
    {
        // 不删除扩展字段，保持前向兼容
    }
};
