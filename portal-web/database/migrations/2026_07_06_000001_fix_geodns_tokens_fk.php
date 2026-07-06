<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 修复 dns_geodns_tokens.geodns_node_id 的外键引用
 *
 * 历史问题（2026-07-06 发现）：
 *   - 2026-06-22 migration 创建 dns_geodns_tokens 时，错误地把 geodns_node_id
 *     FK 引用到了 dns_resolver_nodes.id（"nodes" 表）
 *   - 业务语义上 geodns 是独立实体（装在 156.234.133.8 等独立服务器），
 *     不属于 resolver 节点。geodns_node_id 应该引用 dns_geodns.id（"geodns" 表）
 *
 * 修复方案：
 *   - 删除原 FK fk_geodns_tokens_node
 *   - 重建 FK 引用 dns_geodns.id
 *
 * 数据迁移：
 *   - 当前 dns_geodns_tokens 表 0 行（生产 DB 验证）
 *   - 无需数据迁移
 *
 * 回滚：恢复引用 dns_resolver_nodes.id
 */
return new class extends Migration
{
    public function up(): void
    {
        // 删旧 FK（不管存不存在都先尝试）
        Schema::table('geodns_tokens', function (Blueprint $table): void {
            $table->dropForeign('fk_geodns_tokens_node');
        });

        // 重建 FK 引用 dns_geodns.id
        Schema::table('geodns_tokens', function (Blueprint $table): void {
            $table->foreign('geodns_node_id', 'fk_geodns_tokens_node')
                ->references('id')
                ->on('geodns')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        // 回滚：恢复引用 dns_resolver_nodes.id
        Schema::table('geodns_tokens', function (Blueprint $table): void {
            $table->dropForeign('fk_geodns_tokens_node');
        });

        Schema::table('geodns_tokens', function (Blueprint $table): void {
            $table->foreign('geodns_node_id', 'fk_geodns_tokens_node')
                ->references('id')
                ->on('nodes')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });
    }
};
