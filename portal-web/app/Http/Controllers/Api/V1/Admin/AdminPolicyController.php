<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Billing\PlanCatalogService;
use App\Models\Plan;
use App\Models\PolicySnapshot;
use App\Models\ResolverNode;
use App\Models\User;
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
            ->orderBy('node_code')
            ->get()
            ->map(fn (ResolverNode $n) => [
                'node_id' => $n->node_code,
                'node_name' => $n->name,
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
     * 方案列表 — 前台展示的 Plan（含订阅该方案的用户清单）。
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

        // 收集所有 plan.code，一次性查出对应用户
        $codes = $plans->pluck('code')->unique()->values()->all();
        $usersByCode = User::query()
            ->whereIn('plan_code', $codes)
            ->get(['uid', 'username', 'email', 'plan_code', 'status', 'created_at'])
            ->groupBy('plan_code');

        $rows = $plans->map(function (Plan $plan) use ($usersByCode) {
            $subscribers = $usersByCode->get($plan->code, collect());
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
                'user_count' => $subscribers->count(),
                'users' => $subscribers->map(fn (User $u) => [
                    'uid' => $u->uid,
                    'username' => $u->username,
                    'email' => $u->email,
                    'status' => $u->status,
                    'subscribed_at' => optional($u->created_at)?->toIso8601String(),
                ])->values()->all(),
            ];
        })->all();

        return response()->json([
            'data' => $rows,
            'meta' => [
                'total' => count($rows),
                'user_total' => $usersByCode->flatten(1)->count(),
            ],
        ]);
    }
}
