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
                $query->where('profile_id', $profileId);
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
            'profile_id' => Profile::generateProfileUid(),
            'user_id' => $userId,
            'name' => $profileName,
            'description' => $payload['description'] ?? null,
            'default_action' => $payload['default_action'] ?? 'allow',
            'block_response' => $payload['block_response'] ?? 'nxdomain',
            'is_default' => $isDefault,
            'status' => $payload['status'] ?? 'active',
            'security_enabled' => (bool) ($payload['security_enabled'] ?? true),
            'security_settings' => $this->defaultSecurity(),
            'privacy_enabled' => (bool) ($payload['privacy_enabled'] ?? true),
            'privacy_settings' => $this->defaultPrivacy(),
            'parental_enabled' => (bool) ($payload['parental_enabled'] ?? false),
            'parental_settings' => $this->defaultParental(),
            'safesearch_enabled' => (bool) ($payload['safesearch_enabled'] ?? false),
            'log_retention_days' => (int) ($payload['log_retention_days'] ?? 24),
            'version' => 1,
        ]);

        // 2026-06-24: 创建 Profile 后自动触发首次发布，确保立即可用
        $profileData = $profile->toArray();
        $profileData['profile_id'] = $profile->profile_id;
        $this->publishService->publish($profileData, [], $this->featureSettings($profile));

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

        // 2026-06-24: 配置变更后自动发布，确保 resolver 立即获取最新配置
        $profile->refresh();
        $profileData = $profile->toArray();
        $profileData['profile_id'] = $profile->profile_id;
        $profileData['devices'] = $profile->devices()->get()->toArray();
        $profileData['rules'] = $profile->rules()->get()->toArray();
        $this->publishService->publish(
            $profileData,
            $profileData['rules'],
            $this->featureSettings($profile),
        );

        return $profile->fresh()->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    public function delete(string $userId, string $profileId): array
    {
        $profile = $this->resolveProfile($userId, $profileId);
        $profileUid = $profile->profile_id;

        // V2.4: 在事务内级联删除该 Profile 的所有关联数据，避免遗留白/黑名单、版本、设备
        DB::transaction(function () use ($profile, $profileUid): void {
            ProfileRule::where('profile_id', $profile->id)->delete();
            ProfileVersion::where('target_profile_id', $profile->id)
                ->where('target_scope', 'profile')
                ->delete();
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
            'profile_id' => $profileUid,
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
        $clone->profile_id = \App\Models\Profile::generateProfileUid();
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
            ->whereIn('profile_id', $profileIds)
            ->get();

        $existingUids = $existing->pluck('profile_id')->all();

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
                ProfileVersion::where('target_profile_id', $profile->id)
                    ->where('target_scope', 'profile')
                    ->delete();
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

    /**
     * @return array<string, mixed>
     */
    private function featureSettings(Profile $profile): array
    {
        $security = array_merge($this->defaultSecurity(), is_array($profile->security_settings) ? $profile->security_settings : []);
        $privacy = array_merge($this->defaultPrivacy(), is_array($profile->privacy_settings) ? $profile->privacy_settings : []);
        $parental = array_merge($this->defaultParental(), is_array($profile->parental_settings) ? $profile->parental_settings : []);

        return [
            'security' => array_merge($security, [
                'enabled' => (bool) ($security['enabled'] ?? $profile->security_enabled),
            ]),
            'privacy' => array_merge($privacy, [
                'enabled' => (bool) ($privacy['enabled'] ?? $profile->privacy_enabled),
            ]),
            'parental' => array_merge($parental, [
                'enabled' => (bool) ($parental['enabled'] ?? $profile->parental_enabled),
                'safe_search' => (bool) ($parental['safe_search'] ?? $profile->safe_search_enabled),
            ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultSecurity(): array
    {
        return [
            'enabled' => true,
            'block_malware' => true,
            'block_phishing' => true,
            'block_command_and_control' => true,
            'block_cryptojacking' => true,
            'threat_intel' => true,
            'ai_threat_detection' => false,
            'google_safe_browsing' => true,
            'dns_rebind' => true,
            'idn_homograph' => true,
            'typo_squatting' => true,
            'dga_protection' => true,
            'block_new_domains' => true,
            'block_dynamic_dns' => false,
            'block_parked_domains' => true,
            'block_tld' => false,
            'child_abuse' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultPrivacy(): array
    {
        return [
            'enabled' => true,
            'block_trackers' => true,
            'block_analytics' => true,
            'block_telemetry' => true,
            'anonymize_client_ip' => true,
            'allow_marketing_links' => false,
            'block_disguised_trackers' => true,
            'log_mode' => 'full',
            'blocklists' => [],
            'deep_tracking_devices' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultParental(): array
    {
        return [
            'enabled' => false,
            'block_adult_content' => false,
            'block_gambling' => false,
            'block_gambling_basic' => false,
            'safe_search' => false,
            'force_safe_search' => false,
            'youtube_restricted_mode' => false,
            'force_youtube_restricted' => false,
            'block_bypass' => false,
            'time_limits' => [],
            'blocked_items' => [],
            'blocked_categories' => [],
        ];
    }
}
