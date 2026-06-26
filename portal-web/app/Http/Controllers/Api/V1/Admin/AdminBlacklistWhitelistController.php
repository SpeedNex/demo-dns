<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProfileRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 黑白名单汇总：跨 Profile 拉取所有 allow/block 规则。
 */
final class AdminBlacklistWhitelistController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $type = $request->query('type', 'all'); // all|allow|block
        $keyword = trim((string) $request->query('keyword', ''));

        $query = ProfileRule::query()
            ->with(['profile:id,profile_id,name,user_id', 'profile.user:uid,email,username']);

        if ($type === 'allow' || $type === 'block') {
            $mapped = $type === 'allow' ? 'allowlist' : 'blocklist';
            $query->where('list_type', $mapped);
        }

        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword): void {
                $q->where('domain', 'like', "%{$keyword}%")
                    ->orWhereHas('profile.user', function ($u) use ($keyword): void {
                        $u->where('email', 'like', "%{$keyword}%");
                    });
            });
        }

        $perPage = (int) ($request->query('per_page', 20));
        $perPage = max(1, min(100, $perPage));
        $paginator = $query->orderByDesc('id')->paginate($perPage);
        $items = collect($paginator->items());

        // 聚合用户信息
        $userIds = $items
            ->map(fn (ProfileRule $r) => $r->profile?->user_id)
            ->filter()
            ->unique()
            ->values()
            ->all();
        $userMap = \App\Models\User::query()
            ->whereIn('uid', $userIds)
            ->get(['uid', 'username', 'email'])
            ->keyBy('uid');

        $data = $items->map(function (ProfileRule $r) use ($userMap): array {
            $user = $r->profile?->user ?: ($r->profile && $r->profile->user_id ? $userMap->get($r->profile->user_id) : null);

            return [
                'id' => $r->id,
                'list_type' => $r->list_type,
                'action' => $r->action,
                'domain' => $r->domain,
                'match_type' => $r->match_type ?? 'exact',
                'enabled' => (bool) ($r->enabled ?? true),
                'profile_id' => $r->profile?->profile_id,
                'user_email' => $user?->email,
                'username' => $user?->username,
                'created_at' => $r->created_at,
            ];
        });

        return response()->json([
            'data' => $data->values(),
            'meta' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'page' => $paginator->currentPage(),
            ],
        ]);
    }
}
