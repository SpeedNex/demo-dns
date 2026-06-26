<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 变更说明：
 * 1. dns_nodes.id -> node_id
 * 2. dns_geodns.id -> server_id
 * 3. dns_nodes 删除 name 列
 */
return new class extends Migration
{
    public function up(): void
    {
        $prefix = DB::getTablePrefix();
        $fks = [
            ["{$prefix}node_heartbeats", "fk_node_heartbeats_node"],
            ["{$prefix}profile_versions", "fk_profile_versions_node"],
            ["{$prefix}node_tokens",     "fk_node_tokens_node"],
            ["{$prefix}task_executions", "fk_task_exec_node"],
            ["{$prefix}geo_dns_mappings","fk_geo_node"],
            ["{$prefix}geodns",          "fk_geodns_node"],
        ];
        foreach ($fks as [$table, $fkName]) {
            if ($this->fkExists($table, $fkName)) {
                DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$fkName}`");
            }
        }

        // 2. RENAME nodes.id → node_id (skip if already renamed)
        if (Schema::hasColumn('nodes', 'id')) {
            DB::statement("ALTER TABLE `{$prefix}nodes` CHANGE COLUMN `id` `node_id` bigint unsigned NOT NULL AUTO_INCREMENT");
        }

        // 3. RENAME geodns.id → server_id (skip if already renamed)
        if (Schema::hasTable("geodns") && Schema::hasColumn('geodns', 'id')) {
            DB::statement("ALTER TABLE `{$prefix}geodns` CHANGE COLUMN `id` `server_id` bigint unsigned NOT NULL AUTO_INCREMENT");
        }

        // 4. DROP nodes.name (skip if already dropped)
        if (Schema::hasColumn("nodes", "name")) {
            Schema::table("nodes", function ($table): void {
                $table->dropColumn("name");
            });
        }
        DB::statement("ALTER TABLE `{$prefix}node_heartbeats` ADD CONSTRAINT `fk_node_heartbeats_node` FOREIGN KEY (`node_id`) REFERENCES `{$prefix}nodes` (`node_id`) ON DELETE CASCADE ON UPDATE CASCADE");
        DB::statement("ALTER TABLE `{$prefix}profile_versions` ADD CONSTRAINT `fk_profile_versions_node` FOREIGN KEY (`target_node_id`) REFERENCES `{$prefix}nodes` (`node_id`) ON DELETE CASCADE ON UPDATE CASCADE");
        DB::statement("ALTER TABLE `{$prefix}node_tokens` ADD CONSTRAINT `fk_node_tokens_node` FOREIGN KEY (`node_id`) REFERENCES `{$prefix}nodes` (`node_id`) ON DELETE CASCADE ON UPDATE CASCADE");
        DB::statement("ALTER TABLE `{$prefix}task_executions` ADD CONSTRAINT `fk_task_exec_node` FOREIGN KEY (`node_id`) REFERENCES `{$prefix}nodes` (`node_id`) ON DELETE CASCADE ON UPDATE CASCADE");
        if (Schema::hasTable('geo_dns_mappings')) {
            DB::statement("ALTER TABLE `{$prefix}geo_dns_mappings` ADD CONSTRAINT `fk_geo_node` FOREIGN KEY (`target_node_id`) REFERENCES `{$prefix}nodes` (`node_id`) ON DELETE SET NULL ON UPDATE CASCADE");
        }
        if (Schema::hasTable("geodns")) {
            DB::statement("ALTER TABLE `{$prefix}geodns` ADD CONSTRAINT `fk_geodns_node` FOREIGN KEY (`node_id`) REFERENCES `{$prefix}nodes` (`node_id`) ON DELETE CASCADE ON UPDATE CASCADE");
        }
    }

    public function down(): void
    {
        $prefix = DB::getTablePrefix();
        $fks = [
            ["{$prefix}node_heartbeats", "fk_node_heartbeats_node"],
            ["{$prefix}profile_versions", "fk_profile_versions_node"],
            ["{$prefix}node_tokens",     "fk_node_tokens_node"],
            ["{$prefix}task_executions", "fk_task_exec_node"],
            ["{$prefix}geo_dns_mappings","fk_geo_node"],
            ["{$prefix}geodns",          "fk_geodns_node"],
        ];
        foreach ($fks as [$table, $fkName]) {
            if ($this->fkExists($table, $fkName)) {
                DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$fkName}`");
            }
        }
        DB::statement("ALTER TABLE `{$prefix}nodes` CHANGE COLUMN `node_id` `id` bigint unsigned NOT NULL AUTO_INCREMENT");
        if (Schema::hasTable("geodns")) {
            DB::statement("ALTER TABLE `{$prefix}geodns` CHANGE COLUMN `server_id` `id` bigint unsigned NOT NULL AUTO_INCREMENT");
        }
        if (! Schema::hasColumn("nodes", "name")) {
            Schema::table("nodes", function ($table): void {
                $table->string("name", 120)->after("node_type");
            });
        }
        DB::statement("ALTER TABLE `{$prefix}node_heartbeats` ADD CONSTRAINT `fk_node_heartbeats_node` FOREIGN KEY (`node_id`) REFERENCES `{$prefix}nodes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE");
        DB::statement("ALTER TABLE `{$prefix}profile_versions` ADD CONSTRAINT `fk_profile_versions_node` FOREIGN KEY (`target_node_id`) REFERENCES `{$prefix}nodes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE");
        DB::statement("ALTER TABLE `{$prefix}node_tokens` ADD CONSTRAINT `fk_node_tokens_node` FOREIGN KEY (`node_id`) REFERENCES `{$prefix}nodes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE");
        DB::statement("ALTER TABLE `{$prefix}task_executions` ADD CONSTRAINT `fk_task_exec_node` FOREIGN KEY (`node_id`) REFERENCES `{$prefix}nodes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE");
        if (Schema::hasTable('geo_dns_mappings')) {
            DB::statement("ALTER TABLE `{$prefix}geo_dns_mappings` ADD CONSTRAINT `fk_geo_node` FOREIGN KEY (`target_node_id`) REFERENCES `{$prefix}nodes` (`id`) ON DELETE SET NULL ON UPDATE CASCADE");
        }
        if (Schema::hasTable("geodns")) {
            DB::statement("ALTER TABLE `{$prefix}geodns` ADD CONSTRAINT `fk_geodns_node` FOREIGN KEY (`node_id`) REFERENCES `{$prefix}nodes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE");
        }
    }

    private function fkExists(string $table, string $fkName): bool
    {
        $row = DB::selectOne(
            "SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = ?",
            [$table, $fkName, "FOREIGN KEY"],
        );
        return $row !== null;
    }
};
