<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 表重命名：dns_nodes → dns_resolver_nodes，新建 dns_geodns。
 *
 * 变更说明：
 * 1. 创建 dns_geodns 表（GeoDNS 调度解析器专用，不是节点）
 * 2. 将 nodes 表重命名为 resolver_nodes（实际表名 dns_nodes → dns_resolver_nodes）
 *
 * 外键处理：先删除引用 nodes 的外键，重命名后再重建（指向 resolver_nodes）
 */
return new class extends Migration
{
    public function up(): void
    {
        $prefix = DB::connection()->getTablePrefix();
        $driver = DB::connection()->getDriverName();

        // =========================================================
        // 1. 创建 geodns 表（GeoDNS 调度解析器专用）
        // =========================================================
        Schema::create('geodns', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('node_code', 64);
            $table->string('node_alias', 100)->nullable();
            $table->string('region', 40)->nullable();
            $table->string('country', 8)->nullable();
            $table->string('city', 80)->nullable();
            $table->string('domain', 255)->nullable();
            $table->string('public_ipv4', 45)->nullable();
            $table->string('public_ipv6', 64)->nullable();
            $table->json('supported_protocols')->nullable();
            $table->integer('weight')->default(100);
            $table->integer('capacity_qps')->default(0);
            $table->string('install_status', 20)->default('pending');
            $table->integer('desired_config_version')->default(1);
            $table->integer('current_config_version')->default(0);
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->timestamp('last_log_flush_at')->nullable();
            $table->timestamp('last_installed_at')->nullable();
            $table->string('last_listen_addr', 80)->nullable();
            $table->string('api_key', 64)->nullable();
            $table->timestamp('api_key_issued_at')->nullable();
            $table->json('meta')->nullable();
            $table->unsignedBigInteger('created_by_admin_id')->nullable();
            $table->timestamps();

            $table->unique('node_code', 'uniq_geodns_code');
            $table->index('region', 'idx_geodns_region');
            $table->index('install_status', 'idx_geodns_status');
            $table->foreign('created_by_admin_id', 'fk_geodns_creator')
                ->references('admin_id')->on('admins')
                ->nullOnDelete()->cascadeOnUpdate();
        });

        // =========================================================
        // 2. 处理 FKs + 重命名 nodes → resolver_nodes
        // =========================================================
        Schema::disableForeignKeyConstraints();

        // 删除引用 dns_nodes 的外键
        if ($driver === 'mysql') {
            $fksToDrop = [
                ["{$prefix}node_heartbeats", 'fk_node_heartbeats_node'],
                ["{$prefix}config_versions", 'fk_config_versions_node'],
                ["{$prefix}node_tokens", 'fk_node_tokens_node'],
                ["{$prefix}task_executions", 'fk_task_exec_node'],
            ];
            foreach ($fksToDrop as [$table, $fkName]) {
                DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$fkName}`");
            }
        }

        // 重命名：dns_nodes → dns_resolver_nodes
        Schema::rename('nodes', 'resolver_nodes');

        // 重建外键指向 dns_resolver_nodes
        if ($driver === 'mysql') {
            $this->rebuildForeignKeys($prefix);
        }

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        $prefix = DB::connection()->getTablePrefix();
        $driver = DB::connection()->getDriverName();

        Schema::disableForeignKeyConstraints();

        if ($driver === 'mysql') {
            $fksToDrop = [
                ["{$prefix}node_heartbeats", 'fk_node_heartbeats_node'],
                ["{$prefix}config_versions", 'fk_config_versions_node'],
                ["{$prefix}node_tokens", 'fk_node_tokens_node'],
                ["{$prefix}task_executions", 'fk_task_exec_node'],
            ];
            foreach ($fksToDrop as [$table, $fkName]) {
                DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$fkName}`");
            }
        }

        Schema::rename('resolver_nodes', 'nodes');

        if ($driver === 'mysql') {
            $this->rebuildOldForeignKeys($prefix);
        }

        Schema::enableForeignKeyConstraints();

        Schema::dropIfExists('geodns');
    }

    private function rebuildForeignKeys(string $prefix): void
    {
        DB::statement("ALTER TABLE `{$prefix}node_heartbeats` ADD CONSTRAINT `fk_node_heartbeats_node` FOREIGN KEY (`node_id`) REFERENCES `{$prefix}resolver_nodes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE");
        DB::statement("ALTER TABLE `{$prefix}config_versions` ADD CONSTRAINT `fk_config_versions_node` FOREIGN KEY (`target_node_id`) REFERENCES `{$prefix}resolver_nodes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE");
        DB::statement("ALTER TABLE `{$prefix}node_tokens` ADD CONSTRAINT `fk_node_tokens_node` FOREIGN KEY (`node_id`) REFERENCES `{$prefix}resolver_nodes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE");
        DB::statement("ALTER TABLE `{$prefix}task_executions` ADD CONSTRAINT `fk_task_exec_node` FOREIGN KEY (`node_id`) REFERENCES `{$prefix}resolver_nodes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE");
    }

    private function rebuildOldForeignKeys(string $prefix): void
    {
        DB::statement("ALTER TABLE `{$prefix}node_heartbeats` ADD CONSTRAINT `fk_node_heartbeats_node` FOREIGN KEY (`node_id`) REFERENCES `{$prefix}nodes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE");
        DB::statement("ALTER TABLE `{$prefix}config_versions` ADD CONSTRAINT `fk_config_versions_node` FOREIGN KEY (`target_node_id`) REFERENCES `{$prefix}nodes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE");
        DB::statement("ALTER TABLE `{$prefix}node_tokens` ADD CONSTRAINT `fk_node_tokens_node` FOREIGN KEY (`node_id`) REFERENCES `{$prefix}nodes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE");
        DB::statement("ALTER TABLE `{$prefix}task_executions` ADD CONSTRAINT `fk_task_exec_node` FOREIGN KEY (`node_id`) REFERENCES `{$prefix}nodes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE");
    }
};
