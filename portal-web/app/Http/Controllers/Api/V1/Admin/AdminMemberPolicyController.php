<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Profile;
use App\Models\ProfileRule;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * 会员策略列表：按用户维度聚合 profile 数量与默认安全/隐私/家长设置。
 */
final class AdminMemberPolicyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $keyword = trim((string) $request->query('keyword', ''));

        $query = User::query()
            ->withCount('profiles')
            ->orderByDesc('created_at')
            ->limit(500);

        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword): void {
                $q->where('uid_code', 'like', "%{$keyword}%")
                    ->orWhere('email', 'like', "%{$keyword}%")
                    ->orWhere('username', 'like', "%{$keyword}%");
            });
        }

        $users = $query->get(['id', 'uid_code', 'email', 'username', 'created_at']);

        $rows = $users->map(function (User $u): array {
            $profile = Profile::query()->where('user_id', $u->id)->orderByDesc('id')->first();
            $settings = $profile?->member_center_settings ?? [];

            return [
                'user_id' => $u->id,
                'user_uid' => $u->uid_code,
                'email' => $u->email,
                'profile_count' => $u->profiles_count ?? 0,
                'security' => $settings['security_level'] ?? 'standard',
                'privacy' => $settings['privacy_level'] ?? 'standard',
                'parental' => ! empty($settings['parental_enabled']) ? 'enabled' : 'disabled',
                'updated_at' => $profile?->updated_at,
            ];
        });

        return response()->json(['data' => $rows->values()]);
    }
}
