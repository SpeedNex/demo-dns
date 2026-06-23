<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * 表重命名：dns_node_tokens → dns_resolver_node_tokens，dns_node_heartbeats → dns_resolver_node_heartbeats。
 *
 * 统一 resolver 相关子表的命名规范，与 dns_resolver_nodes 保持一致。
 * 重命名子表不影响外键约束（MySQL 自动处理）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('node_tokens', 'resolver_node_tokens');
        Schema::rename('node_heartbeats', 'resolver_node_heartbeats');
    }

    public function down(): void
    {
        Schema::rename('resolver_node_tokens', 'node_tokens');
        Schema::rename('resolver_node_heartbeats', 'node_heartbeats');
    }
};
