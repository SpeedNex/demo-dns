<?php

namespace App\Domain\Profile;

use App\Models\Device;
use App\Models\Profile;
use App\Models\ProfileRule;
use App\Models\ProfileVersion;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

final class ProfileService
{
    public function __construct(
        private readonly ProfilePublishService $publishService,
    ) {
    }

    private function resolveProfile(string $userId, string $profileId): Profile
    {
        return Profile::query()
            ->where('user_id', $userId)
            ->where(function ($query) use ($profileId): void {
                $query->where('profile_uid', $profileId);
                if (ctype_digit($profileId)) {
                    $query->orWhere('id', (int) $profileId);
                }
            })
            ->firstOrFail();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listForCurrentUser(string $userId): array
    {
        return Profile::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function create(string $userId, array $payload): array
    {
        $profileName = $payload['name'] ?? 'My Profile';

        // 检查同名 Profile 是否已存在
        $exists = Profile::where('user_id', $userId)
            ->where('name', $profileName)
            ->exists();
        if ($exists) {
            throw new \InvalidArgumentException("Profile with name '{$profileName}' already exists");
        }

        // V2.4: 用户首个 Profile 自动设为默认；之后由用户手动在 API 切换
        $hasAny = Profile::where('user_id', $userId)->exists();
        $isDefault = $hasAny ? (bool) ($payload['is_default'] ?? false) : true;

        $profile = Profile::create([
            'profile_uid' => Profile::generateProfileUid(),
            'user_id' => $userId,
            'name' => $profileName,
            'description' => $payload['description'] ?? null,
            'is_default' => $isDefault,
            'status' => $payload['status'] ?? 'active',
            'security_enabled' => (bool) ($payload['security_enabled'] ?? true),
            'privacy_enabled' => (bool) ($payload['privacy_enabled'] ?? true),
            'parental_enabled' => (bool) ($payload['parental_enabled'] ?? false),
            'safesearch_enabled' => (bool) ($payload['safesearch_enabled'] ?? false),
            'log_retention_days' => (int) ($payload['log_retention_days'] ?? 24),
            'version' => 1,
        ]);

        // 2026-06-24: 创建 Profile 后自动触发首次发布，确保立即可用
        $profileData = $profile->toArray();
        $profileData['profile_uid'] = $profile->profile_uid;
        $this->publishService->publish($profileData, [], ['security_enabled' => $profile->security_enabled]);

        return $profileData;
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $userId, string $profileId): array
    {
        $profile = $this->resolveProfile($userId, $profileId);

        return $profile->toArray();
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function update(string $userId, string $profileId, array $payload): array
    {
        $profile = $this->resolveProfile($userId, $profileId);

        $updatable = array_intersect_key($payload, array_flip([
            'name', 'description', 'default_action', 'block_response',
            'security_enabled', 'adblock_enabled', 'parental_enabled',
            'privacy_enabled', 'safe_search_enabled', 'log_mode',
        ]));

        $profile->update($updatable);

        return $profile->fresh()->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    public function delete(string $userId, string $profileId): array
    {
        $profile = $this->resolveProfile($userId, $profileId);
        $profileUid = $profile->profile_uid;

        // V2.4: 在事务内级联删除该 Profile 的所有关联数据，避免遗留白/黑名单、版本、设备
        DB::transaction(function () use ($profile, $profileUid): void {
            ProfileRule::where('profile_id', $profile->id)->delete();
            ProfileVersion::where('profile_id', $profile->id)->delete();
            Device::where('profile_id', $profile->id)->delete();

            $profile->delete();

            // 若删除的是默认 Profile，则把该用户第一个剩余 Profile 标记为默认
            if ($profile->is_default ?? false) {
                $next = Profile::where('user_id', $profile->user_id)
                    ->orderBy('created_at')
                    ->first();
                if ($next) {
                    $next->update(['is_default' => true]);
                }
            }
        });

        return [
            'id' => $profileId,
            'profile_uid' => $profileUid,
            'deleted' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function copy(string $userId, string $profileId): array
    {
        $profile = $this->resolveProfile($userId, $profileId);

        $clone = $profile->replicate();
        $clone->profile_uid = \App\Models\Profile::generateProfileUid();
        $clone->name = $profile->name . ' (Copy)';
        $clone->save();

        return $clone->toArray();
    }

    /**
     * @param array<int, string> $profileIds
     * @return array<string, mixed>
     */
    public function batchDelete(string $userId, array $profileIds): array
    {
        $existing = Profile::where('user_id', $userId)
            ->whereIn('profile_uid', $profileIds)
            ->get();

        $existingUids = $existing->pluck('profile_uid')->all();

        if ($existingUids === []) {
            return [
                'requested' => count($profileIds),
                'deleted' => 0,
                'not_found' => array_values($profileIds),
            ];
        }

        $notFound = array_values(array_diff($profileIds, $existingUids));
        $deletedCount = 0;

        // V2.4: 每个 profile 单独事务级联删除
        foreach ($existing as $profile) {
            DB::transaction(function () use ($profile, &$deletedCount): void {
                ProfileRule::where('profile_id', $profile->id)->delete();
                ProfileVersion::where('profile_id', $profile->id)->delete();
                Device::where('profile_id', $profile->id)->delete();
                $profile->delete();
                $deletedCount++;
            });
        }

        // 任意一个默认被删后，重选第一个剩余作为默认
        $stillHasDefault = Profile::where('user_id', $userId)
            ->where('is_default', true)
            ->exists();
        if (! $stillHasDefault) {
            $next = Profile::where('user_id', $userId)
                ->orderBy('created_at')
                ->first();
            if ($next) {
                $next->update(['is_default' => true]);
            }
        }

        return [
            'requested' => count($profileIds),
            'deleted' => $deletedCount,
            'not_found' => $notFound,
        ];
    }
}
