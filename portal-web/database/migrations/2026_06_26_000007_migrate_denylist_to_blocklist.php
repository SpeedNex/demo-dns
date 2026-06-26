<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. 先扩展 ENUM 定义，允许 blocklist 值（不改变数据）
        DB::statement("ALTER TABLE dns_profile_rules MODIFY COLUMN list_type ENUM('allowlist', 'denylist', 'blocklist') NOT NULL");

        // 2. 更新现有数据 denylist -> blocklist
        DB::statement("UPDATE dns_profile_rules SET list_type = 'blocklist' WHERE list_type = 'denylist'");

        // 3. 同步更新 dns_profile_versions 表中的 config_json（如果表存在）
        if (DB::getTablePrefix() . 'dns_profile_versions') {
            try {
                DB::statement("
                    UPDATE dns_profile_versions
                    SET config_json = REPLACE(config_json, '\"denylist\"', '\"blocklist\"')
                    WHERE config_json LIKE '%denylist%'
                ");
            } catch (\Exception $e) {
                // 表可能不存在，静默忽略
            }
        }

        // 4. 清理 ENUM，移除 denylist
        DB::statement("ALTER TABLE dns_profile_rules MODIFY COLUMN list_type ENUM('allowlist', 'blocklist') NOT NULL");
    }

    public function down(): void
    {
        // 回滚：先扩展 ENUM
        DB::statement("ALTER TABLE dns_profile_rules MODIFY COLUMN list_type ENUM('allowlist', 'blocklist', 'denylist') NOT NULL");

        // 回滚数据
        DB::statement("UPDATE dns_profile_rules SET list_type = 'denylist' WHERE list_type = 'blocklist'");

        // 回滚 config_json
        if (DB::getTablePrefix() . 'dns_profile_versions') {
            try {
                DB::statement("
                    UPDATE dns_profile_versions
                    SET config_json = REPLACE(config_json, '\"blocklist\"', '\"denylist\"')
                    WHERE config_json LIKE '%blocklist%'
                ");
            } catch (\Exception $e) {
                // 表可能不存在，静默忽略
            }
        }

        // 恢复原 ENUM
        DB::statement("ALTER TABLE dns_profile_rules MODIFY COLUMN list_type ENUM('allowlist', 'denylist') NOT NULL");
    }
};
