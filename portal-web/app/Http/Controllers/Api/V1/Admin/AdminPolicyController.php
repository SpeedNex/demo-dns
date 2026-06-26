<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Billing\PlanCatalogService;
use App\Models\Node;
use App\Models\Plan;
use App\Models\PolicySnapshot;
use App\Models\User;
use App\Domain\Node\NodeRegistryService;
use App\Domain\Policy\PolicyPublisher;
use App\Domain\Policy\PolicySnapshotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * UI.md #61 / #62 / #63 — 策略闭环 admin 接口。
 *
 * 2026-06-23: 使用 Node 模型，通过 region 字段区分 resolver 节点。
 */
final class AdminPolicyController
{
    public function indexNodes(Request $request): JsonResponse
    {
        $latestVersion = (int) (PolicySnapshot::where('status', PolicySnapshot::STATUS_PUBLISHED)->max('version') ?? 0);
        $fleet = app(NodeRegistryService::class)->fleetStats($latestVersion);
        $rows = Node::query()
            ->where('region', 'like', 'resolver-%')
            ->orderBy('node_code')
            ->get()
            ->map(fn (Node $n) => [
                'node_id' => $n->node_code,
                'node_name' => $n->node_alias,
                'region' => $n->region,
                'status' => $n->runtimeStatus(),
                'policy_version' => $n->current_config_version,
                'last_sync_at' => optional($n->last_heartbeat_at)->toIso8601String(),
                'out_of_sync' => $n->install_status === 'installed' && $n->isOnline() && $n->current_config_version < $latestVersion,
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
        $actorId = (string) ($request->user()?->admin_id ?? 'admin');
        $snap = app(PolicySnapshotService::class)->publish($id, $actorId);
        $results = app(PolicyPublisher::class)->publishToAllOnlineNodes($snap->id);
        return response()->json(['data' => $snap, 'dispatch' => $results]);
    }

    /**
     * 方案列表 — Plan 及其订阅用户数（不含嵌套用户详情，避免大 JSON）。
     */
    public function indexPlans(Request $request): JsonResponse
    {
        app(PlanCatalogService::class)->ensureDefaults();

        $keyword = trim((string) $request->query('keyword', ''));
        $status = trim((string) $request->query('status', ''));

        $query = Plan::query()->with('prices');
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->where('code', 'like', '%' . $keyword . '%')
                    ->orWhere('name', 'like', '%' . $keyword . '%');
            });
        }
        if ($status !== '') {
            $query->where('status', $status);
        }

        $plans = $query
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        // 仅用 COUNT 统计每个 plan 的订阅用户数，不加载用户详情
        $codes = $plans->pluck('code')->unique()->values()->all();
        $userCounts = User::query()
            ->whereIn('plan_code', $codes)
            ->select('plan_code', \Illuminate\Support\Facades\DB::raw('COUNT(*) as cnt'))
            ->groupBy('plan_code')
            ->pluck('cnt', 'plan_code');

        $rows = $plans->map(function (Plan $plan) use ($userCounts) {
            $prices = $plan->prices->map(fn ($p) => [
                'id' => $p->id,
                'billing_cycle' => $p->billing_cycle,
                'currency' => $p->currency,
                'amount_minor' => (int) $p->amount_minor,
                'original_amount_minor' => $p->original_amount_minor !== null ? (int) $p->original_amount_minor : null,
                'status' => $p->status,
            ])->values()->all();

            return [
                'id' => $plan->id,
                'code' => $plan->code,
                'name' => $plan->name,
                'description' => $plan->description,
                'status' => $plan->status,
                'sort_order' => (int) $plan->sort_order,
                'is_featured' => (bool) $plan->is_featured,
                'badge' => $plan->badge,
                'features' => $plan->features ?? [],
                'limits' => $plan->limits ?? [],
                'prices' => $prices,
                'user_count' => (int) ($userCounts[$plan->code] ?? 0),
            ];
        })->all();

        return response()->json([
            'data' => $rows,
            'meta' => [
                'total' => count($rows),
                'user_total' => (int) $userCounts->sum(),
            ],
        ]);
    }
}
