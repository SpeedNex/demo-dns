<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 删除废弃表并创建 dns_geodns_tokens
     *
     * 变更说明：
     * - dns_geo_dns_mappings：原 GeoDNS 地域映射表，已被 GeoDnsMapping 模型废弃
     * - dns_dns_resolver：原 resolver 拆分表，已统一使用 dns_nodes
     * - dns_geodns_tokens：新增，用于 geodns 节点 token 管理，结构参照 dns_node_tokens
     *
     * 注意：dns_geodns 表作为调度器表保留不动。
     */
    public function up(): void
    {
        // 注意：DDL 语句（DROP TABLE / CREATE TABLE）在 MySQL 中会隐式提交事务，
        // 因此不能包裹在 DB::transaction() 中，需按顺序独立执行。

        // =========================================================
        // 1. 删除 dns_geo_dns_mappings
        // =========================================================
        // 先移除 FK 约束，避免 drop table 时报错
        if ($this->hasForeignKey('geo_dns_mappings', 'fk_geo_node')) {
            Schema::table('geo_dns_mappings', function (Blueprint $table) {
                $table->dropForeign('fk_geo_node');
                $table->dropIndex('idx_geo_node');
            });
        }
        Schema::dropIfExists('geo_dns_mappings');

        // =========================================================
        // 2. 删除 dns_dns_resolver（精确表名，避免前缀二次追加）
        // =========================================================
        DB::statement('DROP TABLE IF EXISTS dns_dns_resolver');

        // =========================================================
        // 3. 创建 dns_geodns_tokens（前缀自动追加为 dns_geodns_tokens）
        //    结构参考 dns_node_tokens，用于 geodns 节点鉴权
        // =========================================================
        Schema::create('geodns_tokens', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('geodns_node_id');
            $table->string('token_prefix', 20);
            $table->char('token_hash', 64);
            $table->string('hmac_key_hash', 128)->nullable();
            $table->text('hmac_secret_encrypted')->nullable();
            $table->json('scopes')->nullable();
            $table->enum('status', ['active', 'revoked', 'expired'])->default('active');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoke_reason', 255)->nullable();
            $table->unsignedBigInteger('created_by_admin_id')->nullable();
            $table->timestamps();

            $table->unique('token_prefix', 'uniq_geodns_tokens_prefix');
            $table->unique('token_hash', 'uniq_geodns_tokens_hash');
            $table->index('geodns_node_id', 'idx_geodns_tokens_node');

            $table->foreign('geodns_node_id', 'fk_geodns_tokens_node')
                ->references('id')
                ->on('nodes')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->foreign('created_by_admin_id', 'fk_geodns_tokens_creator')
                ->references('admin_id')
                ->on('admins')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });
    }

    /**
     * 回滚：删除 geodns_tokens，重新创建三张废弃表
     */
    public function down(): void
    {
        // 注意：DDL 语句会隐式提交事务，不能包裹在 DB::transaction() 中

        // 1. 删除新表
        Schema::dropIfExists('geodns_tokens');

        // 2. 重新创建 geo_dns_mappings
        if (! Schema::hasTable('geo_dns_mappings')) {
            Schema::create('geo_dns_mappings', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('domain', 255);
                $table->string('country', 8)->nullable();
                $table->string('region', 40)->nullable();
                $table->unsignedBigInteger('target_node_id')->nullable();
                $table->string('target_endpoint', 255)->nullable();
                $table->integer('weight')->default(100);
                $table->boolean('enabled')->default(true);
                $table->timestamps();
                $table->index('domain', 'idx_geo_domain');
                $table->index('country', 'idx_geo_country');
                $table->index('target_node_id', 'idx_geo_node');
                $table->foreign('target_node_id', 'fk_geo_node')
                    ->references('id')
                    ->on('nodes')
                    ->nullOnDelete()
                    ->cascadeOnUpdate();
            });
        }

        // 3. 重新创建 dns_dns_resolver（基础结构）
        DB::statement('CREATE TABLE IF NOT EXISTS dns_dns_resolver (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            node_id BIGINT UNSIGNED NOT NULL,
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    /**
     * 检查指定表是否包含指定外键
     */
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
};
