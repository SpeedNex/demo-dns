<?php

declare(strict_types=1);

namespace App\Domain\Node;

use App\Models\ResolverNode;
use Illuminate\Support\Carbon;

/**
 * UI.md #48 / #61 — Resolver 节点注册/心跳/版本同步。
 */
final class NodeRegistryService
{
    public function registerOrUpdate(
        string $nodeId,
        string $nodeName,
        ?string $region = null,
        ?string $ip = null,
    ): ResolverNode {
        return ResolverNode::updateOrCreate(
            ['node_id' => $nodeId],
            [
                'node_name' => $nodeName,
                'region' => $region,
                'ip_address' => $ip,
                'status' => ResolverNode::STATUS_ONLINE,
                'last_sync_at' => Carbon::now(),
            ],
        );
    }

    public function recordHeartbeat(string $nodeId, int $policyVersion): ResolverNode
    {
        $node = ResolverNode::where('node_id', $nodeId)->firstOrFail();
        $node->update([
            'policy_version' => $policyVersion,
            'last_sync_at' => Carbon::now(),
            'status' => ResolverNode::STATUS_ONLINE,
        ]);
        return $node;
    }

    public function markOffline(string $nodeId): ResolverNode
    {
        $node = ResolverNode::where('node_id', $nodeId)->firstOrFail();
        $node->update(['status' => ResolverNode::STATUS_OFFLINE]);
        return $node;
    }

    /**
     * @return array{total:int,online:int,offline:int,error:int,out_of_sync:int}
     */
    public function fleetStats(int $latestPublishedVersion): array
    {
        $total = ResolverNode::count();
        $online = ResolverNode::where('status', ResolverNode::STATUS_ONLINE)->count();
        $offline = ResolverNode::where('status', ResolverNode::STATUS_OFFLINE)->count();
        $error = ResolverNode::where('status', ResolverNode::STATUS_ERROR)->count();
        $outOfSync = ResolverNode::where('status', ResolverNode::STATUS_ONLINE)
            ->where('policy_version', '<', $latestPublishedVersion)
            ->count();
        return compact('total', 'online', 'offline', 'error', 'out_of_sync');
    }
}
