<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProfileRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 黑白名单汇总：跨 Profile 拉取所有 allow/deny 规则。
 */
final class AdminBlacklistWhitelistController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $type = $request->query('type', 'all'); // all|allow|deny
        $keyword = trim((string) $request->query('keyword', ''));

        $query = ProfileRule::query()
            ->with(['profile:id,profile_uid,name,user_id', 'profile.user:id,uid_code,email,username']);

        if ($type === 'allow' || $type === 'deny') {
            $query->where('action', $type);
        }

        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword): void {
                $q->where('domain', 'like', "%{$keyword}%")
                    ->orWhereHas('profile.user', function ($u) use ($keyword): void {
                        $u->where('uid_code', 'like', "%{$keyword}%")
                            ->orWhere('email', 'like', "%{$keyword}%");
                    });
            });
        }

        $rows = $query->orderByDesc('id')->limit(1000)->get();

        $data = $rows->map(function (ProfileRule $r): array {
            $user = $r->profile?->user;

            return [
                'id' => $r->id,
                'action' => $r->action,
                'domain' => $r->domain,
                'match_type' => $r->match_type ?? 'exact',
                'enabled' => (bool) ($r->enabled ?? true),
                'profile_uid' => $r->profile?->profile_uid,
                'user_email' => $user?->email,
                'username' => $user?->username,
                'created_at' => $r->created_at,
            ];
        });

        return response()->json(['data' => $data->values()]);
    }
}
