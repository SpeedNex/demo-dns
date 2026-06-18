<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 修复 /api/v1/member/upgrade 500：
 * User::id = "usr_" + 28 chars = 32 chars；
 * 但 dns_subscriptions/dns_invoices/dns_wallet_transactions/dns_usage_records.user_id 字段只有 30，
 * 触发 PostgreSQL SQLSTATE[22001] String data, right truncated。
 *
 * 统一扩展到 64，兼容未来更长的 ID。
 */
return new class extends Migration
{
    public function up(): void
    {
        $statements = [
            'ALTER TABLE dns_subscriptions      ALTER COLUMN user_id TYPE varchar(64)',
            'ALTER TABLE dns_invoices           ALTER COLUMN user_id TYPE varchar(64)',
            'ALTER TABLE dns_wallet_transactions ALTER COLUMN user_id TYPE varchar(64)',
            'ALTER TABLE dns_usage_records      ALTER COLUMN user_id TYPE varchar(64)',
            'ALTER TABLE dns_orders             ALTER COLUMN user_id TYPE varchar(64)',
            'ALTER TABLE dns_api_keys           ALTER COLUMN user_id TYPE varchar(64)',
            'ALTER TABLE dns_billing_periods    ALTER COLUMN user_id TYPE varchar(64)',
            'ALTER TABLE dns_payment_transactions ALTER COLUMN user_id TYPE varchar(64)',
            'ALTER TABLE dns_policy_snapshots   ALTER COLUMN user_id TYPE varchar(64)',
        ];

        foreach ($statements as $sql) {
            // 跳过不存在的表，避免本地/测试环境报错
            $table = str_replace('ALTER TABLE ', '', explode(' ALTER', $sql)[0]);
            try {
                $exists = DB::select("SELECT to_regclass(?) AS t", [$table]);
                if (! $exists || ! $exists[0]->t) {
                    continue;
                }
            } catch (\Throwable $e) {
                // to_regclass 不支持的版本降级为 information_schema
                $exists = DB::select(
                    "SELECT 1 FROM information_schema.columns WHERE table_name = ? AND column_name = 'user_id' LIMIT 1",
                    [$table]
                );
                if (! $exists) {
                    continue;
                }
            }
            DB::statement($sql);
        }
    }

    public function down(): void
    {
        $statements = [
            'ALTER TABLE dns_subscriptions      ALTER COLUMN user_id TYPE varchar(30)',
            'ALTER TABLE dns_invoices           ALTER COLUMN user_id TYPE varchar(30)',
            'ALTER TABLE dns_wallet_transactions ALTER COLUMN user_id TYPE varchar(30)',
            'ALTER TABLE dns_usage_records      ALTER COLUMN user_id TYPE varchar(30)',
            'ALTER TABLE dns_orders             ALTER COLUMN user_id TYPE varchar(30)',
            'ALTER TABLE dns_api_keys           ALTER COLUMN user_id TYPE varchar(30)',
            'ALTER TABLE dns_billing_periods    ALTER COLUMN user_id TYPE varchar(30)',
            'ALTER TABLE dns_payment_transactions ALTER COLUMN user_id TYPE varchar(30)',
            'ALTER TABLE dns_policy_snapshots   ALTER COLUMN user_id TYPE varchar(30)',
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
