<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 2026-06-22: 修复 dns_resolver_nodes_view 缺失问题。
 *
 * UI.md P0-#1: AdminPolicyController / NodeRegistryService 仍依赖
 * ResolverNode Model (table=dns_resolver_nodes_view)，但 dns_resolver_nodes_view
 * 视图未创建。
 *
 * 最小修改：建一个基于 dns_nodes 的兼容视图，保留旧字段名 (node_id, node_name,
 * policy_version, last_sync_at, status, ip_address, meta)，让 ResolverNode
 * Model 不需改动即可运行。
 */
return new class extends Migration
{
    public function up(): void
    {
        // 仅当原视图不存在时创建（防止重跑）
        $exists = DB::selectOne(
            "SELECT TABLE_NAME FROM information_schema.VIEWS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dns_resolver_nodes_view'"
        );

        if ($exists) {
            return;
        }

        // status 字段需要从 last_heartbeat_at 派生（online/degraded/offline）
        // 简单起见：last_heartbeat_at 在 5 分钟内 -> online；30 分钟内 -> degraded；否则 offline
        DB::statement(<<<'SQL'
CREATE VIEW `dns_resolver_nodes_view` AS
SELECT
    `id` AS `node_id`,
    `node_code` AS `node_code`,
    `name` AS `node_name`,
    `region` AS `region`,
    `public_ipv4` AS `ip_address`,
    `current_config_version` AS `policy_version`,
    `last_heartbeat_at` AS `last_sync_at`,
    CASE
        WHEN `last_heartbeat_at` IS NULL THEN 'offline'
        WHEN `last_heartbeat_at` >= (NOW() - INTERVAL 5 MINUTE) THEN 'online'
        WHEN `last_heartbeat_at` >= (NOW() - INTERVAL 30 MINUTE) THEN 'degraded'
        ELSE 'offline'
    END AS `status`,
    `meta` AS `meta`,
    `last_heartbeat_at` AS `last_heartbeat_at`,
    `created_at` AS `created_at`,
    `updated_at` AS `updated_at`
FROM `dns_nodes`
WHERE `node_type` = 'resolver'
SQL
        );
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS `dns_resolver_nodes_view`');
    }
};
