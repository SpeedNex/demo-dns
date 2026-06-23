<?php

declare(strict_types=1);

namespace App\Domain\Node;

use App\Models\Node;
use Illuminate\Support\Carbon;

/**
 * UI.md #48 / #61 — Resolver 节点注册/心跳/版本同步。
 *
 * 2026-06-23: 删除 node_type 字段，节点类型由 region 字段区分。
 * resolver 节点 region 以 'resolver-' 开头。
 */
final class NodeRegistryService
{
    public function registerOrUpdate(
        string $nodeCode,
        string $nodeName,
        ?string $region = null,
        ?string $ip = null,
    ): Node {
        return Node::updateOrCreate(
            ['node_code' => $nodeCode],
            [
                'name' => $nodeName,
                'region' => $region ?? 'resolver-default',
                'public_ipv4' => $ip,
                'install_status' => 'installed',
                'last_heartbeat_at' => Carbon::now(),
            ],
        );
    }

    public function recordHeartbeat(string $nodeCode, int $configVersion): Node
    {
        $node = Node::where('node_code', $nodeCode)->firstOrFail();
        $node->update([
            'current_config_version' => $configVersion,
            'last_heartbeat_at' => Carbon::now(),
        ]);
        return $node;
    }

    public function markOffline(string $nodeCode): Node
    {
        $node = Node::where('node_code', $nodeCode)->firstOrFail();
        $node->update(['last_heartbeat_at' => null]);
        return $node;
    }

    /**
     * @return array{total:int,online:int,offline:int,error:int,out_of_sync:int}
     */
    public function fleetStats(int $latestPublishedVersion): array
    {
        $total = Node::query()->where('region', 'like', 'resolver-%')->count();
        $online = Node::online()->where('region', 'like', 'resolver-%')->count();
        $offline = Node::query()->where('region', 'like', 'resolver-%')->where(function ($q) {
            $q->whereNull('last_heartbeat_at')
              ->orWhere('last_heartbeat_at', '<=', now()->subSeconds(180));
        })->count();
        $error = 0;
        $out_of_sync = Node::query()
            ->where('region', 'like', 'resolver-%')
            ->where('install_status', 'installed')
            ->where('last_heartbeat_at', '>', now()->subSeconds(90))
            ->where('current_config_version', '<', $latestPublishedVersion)
            ->count();
        return [
            'total' => $total,
            'online' => $online,
            'offline' => $offline,
            'error' => $error,
            'out_of_sync' => $out_of_sync,
        ];
    }
}
