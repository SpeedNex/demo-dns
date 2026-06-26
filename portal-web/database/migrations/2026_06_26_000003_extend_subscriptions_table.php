<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 扩展 dns_subscriptions 表，支持 SaaS 订阅模式：
 *   新增 subscription_no（订阅号）、billing_cycle（月付/年付）、
 *   amount_minor（金额）、currency（币种）、provider（支付渠道）、
 *   provider_session_id（Stripe checkout session）、
 *   cancel_at_period_end（取消标记）、meta（快照 JSON）。
 *   同时删除 order_id 的唯一索引（订单表已删除）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            // 删除 order_id 唯一索引（订单表已删除，subscription 不再依赖 order）
            if ($this->hasIndex('subscriptions', 'uniq_subscriptions_order')) {
                $table->dropUnique('uniq_subscriptions_order');
            }

            // 新增 SaaS 订阅字段（plan_code 可能已存在）
            if (! Schema::hasColumn('subscriptions', 'subscription_no')) {
                $table->string('subscription_no', 32)->nullable()->after('id');
            }
            if (! Schema::hasColumn('subscriptions', 'billing_cycle')) {
                $table->string('billing_cycle', 20)->default('monthly')->after('plan_code');
            }
            if (! Schema::hasColumn('subscriptions', 'amount_minor')) {
                $table->unsignedBigInteger('amount_minor')->default(0)->after('billing_cycle');
            }
            if (! Schema::hasColumn('subscriptions', 'currency')) {
                $table->char('currency', 3)->default('USD')->after('amount_minor');
            }
            if (! Schema::hasColumn('subscriptions', 'provider')) {
                $table->string('provider', 20)->nullable()->after('currency');
            }
            if (! Schema::hasColumn('subscriptions', 'provider_session_id')) {
                $table->string('provider_session_id', 255)->nullable()->after('provider');
            }
            if (! Schema::hasColumn('subscriptions', 'cancel_at_period_end')) {
                $table->boolean('cancel_at_period_end')->default(false)->after('auto_renew');
            }
            if (! Schema::hasColumn('subscriptions', 'meta')) {
                $table->json('meta')->nullable()->after('cancel_at_period_end');
            }

            // 索引
            if (! $this->hasIndex('subscriptions', 'uniq_subscriptions_no')) {
                $table->unique('subscription_no', 'uniq_subscriptions_no');
            }
            if (! $this->hasIndex('subscriptions', 'idx_subscriptions_plan_code')) {
                $table->index('plan_code', 'idx_subscriptions_plan_code');
            }
            if (! $this->hasIndex('subscriptions', 'idx_subscriptions_billing_cycle')) {
                $table->index('billing_cycle', 'idx_subscriptions_billing_cycle');
            }
        });
    }

    private function hasIndex(string $table, string $index): bool
    {
        $rows = DB::select("SHOW INDEX FROM dns_{$table} WHERE Key_name = ?", [$index]);
        return ! empty($rows);
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex('idx_subscriptions_billing_cycle');
            $table->dropIndex('idx_subscriptions_plan_code');
            $table->dropUnique('uniq_subscriptions_no');

            $table->dropColumn([
                'meta',
                'cancel_at_period_end',
                'provider_session_id',
                'provider',
                'currency',
                'amount_minor',
                'billing_cycle',
                'plan_code',
                'subscription_no',
            ]);

            // 恢复 order_id 唯一索引
            $table->unique('order_id', 'uniq_subscriptions_order');
        });
    }
};