<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

/**
 * GeoDNS 地理调度节点
 *
 * 与 Node 共享 dns_nodes 表，通过 node_type='geodns' 过滤。
 * 2026-06-22: 原 dns_geodns 拆分表已废弃，统一使用 dns_nodes。
 */
class GeoDnsNode extends Node
{
    /**
     * 默认 scope：只查询 geodns 类型的节点。
     */
    protected static function booted(): void
    {
        parent::booted();

        static::addGlobalScope('geodns_type', function (Builder $builder): void {
            $builder->where('node_type', 'geodns');
        });
    }
}
