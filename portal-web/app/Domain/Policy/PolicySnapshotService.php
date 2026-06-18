<?php

declare(strict_types=1);

namespace App\Domain\Policy;

use App\Models\PolicySnapshot;
use App\Models\Profile;
use Illuminate\Support\Facades\DB;

/**
 * UI.md #63 / #64 — Policy Snapshot Service。
 *
 * 修改 Profile → version+1 → 生成 snapshot → published。
 * Resolver 只读 snapshot，禁止实时查询多个业务表。
 */
final class PolicySnapshotService
{
    public function snapshotForUser(string $userId): PolicySnapshot
    {
        $nextVersion = (int) (DB::table('policy_snapshots')->where('user_id', $userId)->max('version') ?? 0) + 1;
        $payload = $this->buildPayload($userId);

        return PolicySnapshot::create([
            'user_id' => $userId,
            'version' => $nextVersion,
            'payload_json' => $payload,
            'status' => PolicySnapshot::STATUS_DRAFT,
        ]);
    }

    public function publish(int $snapshotId, string $actorId = 'system'): PolicySnapshot
    {
        $snap = PolicySnapshot::findOrFail($snapshotId);
        $snap->update([
            'status' => PolicySnapshot::STATUS_PUBLISHED,
            'published_at' => now(),
            'published_by' => $actorId,
        ]);
        return $snap;
    }

    /**
     * 拼装 resolver 需要的完整策略负载。
     * 取代 resolver 实时 join 多张业务表的做法。
     */
    private function buildPayload(string $userId): array
    {
        $profiles = Profile::where('user_id', $userId)->get();
        $payload = ['user_id' => $userId, 'generated_at' => now()->toIso8601String(), 'profiles' => []];
        foreach ($profiles as $profile) {
            $payload['profiles'][] = [
                'profile_id' => $profile->id,
                'name' => $profile->name,
                'default_action' => $profile->default_action ?? 'allow',
                'security' => $profile->security ?? [],
                'parental' => $profile->parental ?? [],
                'privacy' => $profile->privacy ?? [],
                'safe_search' => $profile->safe_search ?? [],
            ];
        }
        return $payload;
    }
}
