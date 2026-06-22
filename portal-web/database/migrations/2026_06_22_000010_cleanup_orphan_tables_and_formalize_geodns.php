<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 数据库清理与规范化
     *
     * 变更说明：
     * 1. 删除 dns_resolver_nodes_view —— 孤立视图（迁移文件已删，但视图片残留）
     * 2. 删除 dns_navigation_catalogs —— 已被 dns_admin_menu_rule 替代
     * 3. 删除 dns_invoices —— 死表，无 Model、无代码引用、0 行数据
     * 4. 规范化 dns_geodns —— 骨架表补全正式结构（调度器表）
     */
    public function up(): void
    {
        // =========================================================
        // 1. 删除孤立视图 dns_resolver_nodes_view
        // =========================================================
        DB::statement('DROP VIEW IF EXISTS dns_resolver_nodes_view');

        // =========================================================
        // 2. 删除 dns_navigation_catalogs（被 dns_admin_menu_rule 替代）
        // =========================================================
        Schema::dropIfExists('navigation_catalogs');

        // =========================================================
        // 3. 删除 dns_invoices（死表，无代码引用）
        // =========================================================
        Schema::dropIfExists('invoices');

        // =========================================================
        // 4. 规范化 dns_geodns —— 骨架表补全正式列
        //    当前仅有：id, node_id, created_at, updated_at
        // =========================================================
        if (! Schema::hasTable('geodns')) {
            Schema::create('geodns', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('node_id');
                $table->string('domain', 255)->nullable();
                $table->string('region', 40)->nullable();
                $table->integer('weight')->default(100);
                $table->string('status', 20)->default('active');
                $table->timestamp('last_sync_at')->nullable();
                $table->timestamps();

                $table->index('node_id', 'idx_geodns_node');
                $table->index('region', 'idx_geodns_region');
                $table->index('status', 'idx_geodns_status');
                $table->foreign('node_id', 'fk_geodns_node')
                    ->references('id')->on('nodes')
                    ->cascadeOnDelete()->cascadeOnUpdate();
            });
        } else {
            Schema::table('geodns', function (Blueprint $table) {
                if (! Schema::hasColumn('geodns', 'domain')) {
                    $table->string('domain', 255)->nullable()->after('node_id');
                }
                if (! Schema::hasColumn('geodns', 'region')) {
                    $table->string('region', 40)->nullable()->after('domain');
                }
                if (! Schema::hasColumn('geodns', 'weight')) {
                    $table->integer('weight')->default(100)->after('region');
                }
                if (! Schema::hasColumn('geodns', 'status')) {
                    $table->string('status', 20)->default('active')->after('weight');
                }
                if (! Schema::hasColumn('geodns', 'last_sync_at')) {
                    $table->timestamp('last_sync_at')->nullable()->after('status');
                }

                if (! $this->hasIndex('geodns', 'idx_geodns_node')) {
                    $table->index('node_id', 'idx_geodns_node');
                }
                if (! $this->hasIndex('geodns', 'idx_geodns_region')) {
                    $table->index('region', 'idx_geodns_region');
                }
                if (! $this->hasIndex('geodns', 'idx_geodns_status')) {
                    $table->index('status', 'idx_geodns_status');
                }

                if (! $this->hasForeignKey('geodns', 'fk_geodns_node')) {
                    $table->foreign('node_id', 'fk_geodns_node')
                        ->references('id')->on('nodes')
                        ->cascadeOnDelete()->cascadeOnUpdate();
                }
            });
        }
    }

    public function down(): void
    {
        // 1. 重新创建 dns_resolver_nodes_view
        DB::statement('CREATE OR REPLACE VIEW dns_resolver_nodes_view AS
            SELECT
                `dns_nodes`.`id` AS `node_id`,
                `dns_nodes`.`node_code` AS `node_code`,
                `dns_nodes`.`name` AS `node_name`,
                `dns_nodes`.`region` AS `region`,
                `dns_nodes`.`public_ipv4` AS `ip_address`,
                `dns_nodes`.`current_config_version` AS `policy_version`,
                `dns_nodes`.`last_heartbeat_at` AS `last_sync_at`,
                CASE
                    WHEN (`dns_nodes`.`last_heartbeat_at` IS NULL) THEN \'offline\'
                    WHEN (`dns_nodes`.`last_heartbeat_at` >= (NOW() - INTERVAL 5 MINUTE)) THEN \'online\'
                    WHEN (`dns_nodes`.`last_heartbeat_at` >= (NOW() - INTERVAL 30 MINUTE)) THEN \'degraded\'
                    ELSE \'offline\'
                END AS `status`,
                `dns_nodes`.`meta` AS `meta`,
                `dns_nodes`.`last_heartbeat_at` AS `last_heartbeat_at`,
                `dns_nodes`.`created_at` AS `created_at`,
                `dns_nodes`.`updated_at` AS `updated_at`
            FROM `dns_nodes`
            WHERE (`dns_nodes`.`node_type` = \'resolver\')');

        // 2. 重新创建 dns_navigation_catalogs
        if (! Schema::hasTable('navigation_catalogs')) {
            Schema::create('navigation_catalogs', function (Blueprint $table) {
                $table->increments('id');
                $table->string('key', 80);
                $table->string('label_key', 160);
                $table->string('group_key', 50)->nullable();
                $table->string('path', 300)->nullable();
                $table->string('icon', 100)->nullable();
                $table->integer('sort_order')->default(0);
                $table->boolean('visible')->default(true);
                $table->timestamps();
                $table->unique('key', 'uniq_navigation_key');
            });
        }

        // 3. 重新创建 dns_invoices（基础结构）
        if (! Schema::hasTable('invoices')) {
            Schema::create('invoices', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('order_id')->nullable();
                $table->string('invoice_no', 64)->unique();
                $table->string('currency', 10);
                $table->unsignedBigInteger('total_minor');
                $table->unsignedBigInteger('tax_minor');
                $table->unsignedBigInteger('discount_minor');
                $table->string('status', 20);
                $table->json('items')->nullable();
                $table->timestamp('issued_at')->nullable();
                $table->timestamp('finalized_at')->nullable();
                $table->timestamps();
                $table->index('user_id');
                $table->index('order_id');
                $table->index('status');
            });
        }

        // 4. dns_geodns —— 回滚新增的列和约束
        if (Schema::hasTable('geodns')) {
            Schema::table('geodns', function (Blueprint $table) {
                $table->dropIndex(['node_id', 'region', 'status']);
            });
            $columns = ['last_sync_at', 'status', 'weight', 'region', 'domain'];
            foreach ($columns as $col) {
                if (Schema::hasColumn('geodns', $col)) {
                    Schema::table('geodns', function (Blueprint $table) use ($col) {
                        $table->dropColumn($col);
                    });
                }
            }
        }
    }

    private function hasForeignKey(string $table, string $fkName): bool
    {
        if (! Schema::hasTable($table)) {
            return false;
        }
        $schemaName = DB::connection()->getDatabaseName();
        $prefix = DB::connection()->getTablePrefix();
        $fullTableName = $prefix.$table;
        $fk = DB::selectOne(
            'SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = ?',
            [$schemaName, $fullTableName, $fkName, 'FOREIGN KEY']
        );
        return $fk !== null;
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        if (! Schema::hasTable($table)) {
            return false;
        }
        $schemaName = DB::connection()->getDatabaseName();
        $prefix = DB::connection()->getTablePrefix();
        $fullTableName = $prefix.$table;
        $idx = DB::selectOne(
            'SELECT INDEX_NAME FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?',
            [$schemaName, $fullTableName, $indexName]
        );
        return $idx !== null;
    }
};
