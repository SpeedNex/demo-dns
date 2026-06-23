<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 回退变更：将 nodes.node_id -> id, geodns.server_id -> id
 *
 * 这使得：
 * 1. nodes 表主键统一为 id（与大多数 Laravel 约定一致）
 * 2. 路由模型绑定使用 id 而非 node_code
 * 3. 前端删除/编辑操作直接使用 id
 */
return new class extends Migration
{
    public function up(): void
    {
        $prefix = DB::getTablePrefix();

        // 1. 收集并删除所有引用 nodes.node_id 的外键
        $fksToNodes = [
            ["{$prefix}node_heartbeats", "fk_node_heartbeats_node"],
            ["{$prefix}config_versions", "fk_config_versions_node"],
            ["{$prefix}node_tokens",     "fk_node_tokens_node"],
            ["{$prefix}task_executions", "fk_task_exec_node"],
            ["{$prefix}geo_dns_mappings","fk_geo_node"],
            ["{$prefix}geodns",          "fk_geodns_node"],
        ];
        foreach ($fksToNodes as [$table, $fkName]) {
            if ($this->fkExists($table, $fkName)) {
                DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$fkName}`");
            }
        }

        // 2. nodes.node_id -> id
        if (Schema::hasColumn('nodes', 'node_id')) {
            DB::statement("ALTER TABLE `{$prefix}nodes` CHANGE COLUMN `node_id` `id` bigint unsigned NOT NULL AUTO_INCREMENT");
        }

        // 3. geodns.server_id -> id
        if (Schema::hasTable("geodns") && Schema::hasColumn('geodns', 'server_id')) {
            DB::statement("ALTER TABLE `{$prefix}geodns` CHANGE COLUMN `server_id` `id` bigint unsigned NOT NULL AUTO_INCREMENT");
        }

        // 4. 重建外键（nodes.id）
        DB::statement("ALTER TABLE `{$prefix}node_heartbeats` ADD CONSTRAINT `fk_node_heartbeats_node` FOREIGN KEY (`node_id`) REFERENCES `{$prefix}nodes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE");
        DB::statement("ALTER TABLE `{$prefix}config_versions` ADD CONSTRAINT `fk_config_versions_node` FOREIGN KEY (`target_node_id`) REFERENCES `{$prefix}nodes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE");
        DB::statement("ALTER TABLE `{$prefix}node_tokens` ADD CONSTRAINT `fk_node_tokens_node` FOREIGN KEY (`node_id`) REFERENCES `{$prefix}nodes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE");
        DB::statement("ALTER TABLE `{$prefix}task_executions` ADD CONSTRAINT `fk_task_exec_node` FOREIGN KEY (`node_id`) REFERENCES `{$prefix}nodes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE");
        if (Schema::hasTable('geo_dns_mappings')) {
            DB::statement("ALTER TABLE `{$prefix}geo_dns_mappings` ADD CONSTRAINT `fk_geo_node` FOREIGN KEY (`target_node_id`) REFERENCES `{$prefix}nodes` (`id`) ON DELETE SET NULL ON UPDATE CASCADE");
        }
        if (Schema::hasTable("geodns")) {
            DB::statement("ALTER TABLE `{$prefix}geodns` ADD CONSTRAINT `fk_geodns_node` FOREIGN KEY (`node_id`) REFERENCES `{$prefix}nodes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE");
        }
    }

    public function down(): void
    {
        $prefix = DB::getTablePrefix();

        // 1. 收集并删除所有引用 nodes.id 的外键
        $fksToNodes = [
            ["{$prefix}node_heartbeats", "fk_node_heartbeats_node"],
            ["{$prefix}config_versions", "fk_config_versions_node"],
            ["{$prefix}node_tokens",     "fk_node_tokens_node"],
            ["{$prefix}task_executions", "fk_task_exec_node"],
            ["{$prefix}geo_dns_mappings","fk_geo_node"],
            ["{$prefix}geodns",          "fk_geodns_node"],
        ];
        foreach ($fksToNodes as [$table, $fkName]) {
            if ($this->fkExists($table, $fkName)) {
                DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$fkName}`");
            }
        }

        // 2. nodes.id -> node_id
        if (Schema::hasColumn('nodes', 'id')) {
            DB::statement("ALTER TABLE `{$prefix}nodes` CHANGE COLUMN `id` `node_id` bigint unsigned NOT NULL AUTO_INCREMENT");
        }

        // 3. geodns.id -> server_id
        if (Schema::hasTable("geodns") && Schema::hasColumn('geodns', 'id')) {
            DB::statement("ALTER TABLE `{$prefix}geodns` CHANGE COLUMN `id` `server_id` bigint unsigned NOT NULL AUTO_INCREMENT");
        }

        // 4. 重建外键（nodes.node_id）
        DB::statement("ALTER TABLE `{$prefix}node_heartbeats` ADD CONSTRAINT `fk_node_heartbeats_node` FOREIGN KEY (`node_id`) REFERENCES `{$prefix}nodes` (`node_id`) ON DELETE CASCADE ON UPDATE CASCADE");
        DB::statement("ALTER TABLE `{$prefix}config_versions` ADD CONSTRAINT `fk_config_versions_node` FOREIGN KEY (`target_node_id`) REFERENCES `{$prefix}nodes` (`node_id`) ON DELETE CASCADE ON UPDATE CASCADE");
        DB::statement("ALTER TABLE `{$prefix}node_tokens` ADD CONSTRAINT `fk_node_tokens_node` FOREIGN KEY (`node_id`) REFERENCES `{$prefix}nodes` (`node_id`) ON DELETE CASCADE ON UPDATE CASCADE");
        DB::statement("ALTER TABLE `{$prefix}task_executions` ADD CONSTRAINT `fk_task_exec_node` FOREIGN KEY (`node_id`) REFERENCES `{$prefix}nodes` (`node_id`) ON DELETE CASCADE ON UPDATE CASCADE");
        if (Schema::hasTable('geo_dns_mappings')) {
            DB::statement("ALTER TABLE `{$prefix}geo_dns_mappings` ADD CONSTRAINT `fk_geo_node` FOREIGN KEY (`target_node_id`) REFERENCES `{$prefix}nodes` (`node_id`) ON DELETE SET NULL ON UPDATE CASCADE");
        }
        if (Schema::hasTable("geodns")) {
            DB::statement("ALTER TABLE `{$prefix}geodns` ADD CONSTRAINT `fk_geodns_node` FOREIGN KEY (`node_id`) REFERENCES `{$prefix}nodes` (`node_id`) ON DELETE CASCADE ON UPDATE CASCADE");
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
