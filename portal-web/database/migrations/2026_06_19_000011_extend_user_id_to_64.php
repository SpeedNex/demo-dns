<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 修复 /api/v1/member/upgrade 500：
 * User::id = "usr_" + 28 chars = 32 chars；
 * 但 dns_subscriptions/dns_invoices/dns_wallet_transactions/dns_usage_records.user_id 字段只有 30，
 * 触发 MySQL 1406 Data too long for column。
 *
 * 统一扩展到 64，兼容未来更长的 ID。
 */
return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'dns_subscriptions',
            'dns_invoices',
            'dns_wallet_transactions',
            'dns_usage_records',
            'dns_orders',
            'dns_api_keys',
            'dns_billing_periods',
            'dns_payment_transactions',
            'dns_policy_snapshots',
        ];

        foreach ($tables as $table) {
            // 跳过不存在的表
            $exists = DB::select(
                "SELECT 1 FROM information_schema.columns WHERE table_name = ? AND column_name = 'user_id' LIMIT 1",
                [$table]
            );
            if (! $exists) {
                continue;
            }
            // MySQL 不允许在有外键约束的列上直接 MODIFY，先获取外键名 + 临时禁用
            $fks = DB::select(
                "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
                 WHERE TABLE_NAME = ? AND COLUMN_NAME = 'user_id' AND REFERENCED_TABLE_NAME IS NOT NULL",
                [$table]
            );
            $fkNames = array_map(fn ($f) => $f->CONSTRAINT_NAME, $fks);
            // 关闭外键检查
            DB::statement('SET FOREIGN_KEY_CHECKS = 0');
            try {
                DB::statement("ALTER TABLE `{$table}` MODIFY COLUMN `user_id` varchar(64)");
            } finally {
                DB::statement('SET FOREIGN_KEY_CHECKS = 1');
            }
        }
    }

    public function down(): void
    {
        $statements = [
            'ALTER TABLE `dns_subscriptions`        MODIFY COLUMN `user_id` varchar(30)',
            'ALTER TABLE `dns_invoices`             MODIFY COLUMN `user_id` varchar(30)',
            'ALTER TABLE `dns_wallet_transactions`  MODIFY COLUMN `user_id` varchar(30)',
            'ALTER TABLE `dns_usage_records`        MODIFY COLUMN `user_id` varchar(30)',
            'ALTER TABLE `dns_orders`               MODIFY COLUMN `user_id` varchar(30)',
            'ALTER TABLE `dns_api_keys`             MODIFY COLUMN `user_id` varchar(30)',
            'ALTER TABLE `dns_billing_periods`      MODIFY COLUMN `user_id` varchar(30)',
            'ALTER TABLE `dns_payment_transactions` MODIFY COLUMN `user_id` varchar(30)',
            'ALTER TABLE `dns_policy_snapshots`     MODIFY COLUMN `user_id` varchar(30)',
        ];
        foreach ($statements as $sql) {
            try {
                DB::statement($sql);
            } catch (\Throwable $e) {
                // ignore on rollback
            }
        }
    }
};
