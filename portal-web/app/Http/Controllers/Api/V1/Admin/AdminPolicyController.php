<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\PolicySnapshot;
use App\Models\ResolverNode;
use App\Domain\Node\NodeRegistryService;
use App\Domain\Policy\PolicyPublisher;
use App\Domain\Policy\PolicySnapshotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * UI.md #61 / #62 / #63 — 策略闭环 admin 接口。
 */
final class AdminPolicyController
{
    public function indexNodes(Request $request): JsonResponse
    {
        $latestVersion = (int) (PolicySnapshot::where('status', PolicySnapshot::STATUS_PUBLISHED)->max('version') ?? 0);
        $fleet = app(NodeRegistryService::class)->fleetStats($latestVersion);
        $rows = ResolverNode::query()
            ->orderBy('node_id')
            ->get()
            ->map(fn (ResolverNode $n) => [
                'node_id' => $n->node_id,
                'node_name' => $n->node_name,
                'region' => $n->region,
                'status' => $n->status,
                'policy_version' => $n->policy_version,
                'last_sync_at' => optional($n->last_sync_at)->toIso8601String(),
                'out_of_sync' => $n->status === ResolverNode::STATUS_ONLINE
                    && $n->policy_version < $latestVersion,
            ]);
        return response()->json([
            'data' => $rows,
            'meta' => ['latest_published_version' => $latestVersion] + $fleet,
        ]);
    }

    public function snapshotUser(string $userId, Request $request): JsonResponse
    {
        $snap = app(PolicySnapshotService::class)->snapshotForUser($userId);
        return response()->json(['data' => $snap], 201);
    }

    public function publishSnapshot(int $id, Request $request): JsonResponse
    {
        $actorId = (string) ($request->user()?->id ?? 'admin');
        $snap = app(PolicySnapshotService::class)->publish($id, $actorId);
        $results = app(PolicyPublisher::class)->publishToAllOnlineNodes($snap->id);
        return response()->json(['data' => $snap, 'dispatch' => $results]);
    }
}
