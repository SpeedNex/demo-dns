<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

/**
 * DNS 解析器节点
 *
 * 与 Node 共享 dns_nodes 表，通过 node_type='resolver' 过滤。
 * 2026-06-22: 原 dns_resolver 拆分表已废弃，统一使用 dns_nodes。
 */
class ResolverNode extends Node
{
    /**
     * 默认 scope：只查询 resolver 类型的节点。
     * 所有 ResolverNode::query() 都自动加 WHERE node_type='resolver'。
     */
    protected static function booted(): void
    {
        parent::booted();

        static::addGlobalScope('resolver_type', function (Builder $builder): void {
            $builder->where('node_type', 'resolver');
        });
    }
}
