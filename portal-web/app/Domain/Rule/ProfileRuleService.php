<?php

namespace App\Domain\Rule;

use App\Models\Profile;
use App\Models\ProfileRule;
use App\Domain\Profile\DomainNormalizer;

final class ProfileRuleService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function list(string $userId, int|string $profileId): array
    {
        $profile = Profile::where('user_id', $userId)
            ->where('id', $profileId)
            ->firstOrFail();

        return ProfileRule::where('profile_id', $profile->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function create(string $userId, int|string $profileId, array $payload): array
    {
        $profile = Profile::where('user_id', $userId)
            ->where('id', $profileId)
            ->firstOrFail();

        $domain = (string) ($payload['domain'] ?? '');
        $normalizedDomain = DomainNormalizer::normalize($domain);
        $listType = $this->normalizeListType((string) ($payload['list_type'] ?? 'block'));

        $existing = ProfileRule::where('profile_id', $profile->id)
            ->where('list_type', $listType)
            ->where('match_type', $payload['match_type'])
            ->where('normalized_domain', $normalizedDomain)
            ->first();

        if ($existing) {
            throw new \InvalidArgumentException('Duplicate rule: same domain already exists for this profile and list type.');
        }

        $rule = ProfileRule::create([
            'profile_id' => $profile->id,
            'list_type' => $listType,
            'match_type' => $payload['match_type'],
            'domain' => $domain,
            'normalized_domain' => $normalizedDomain,
            'action' => $payload['action'] ?? ($listType === 'allowlist' ? 'allow' : 'block'),
            'category' => $payload['category'] ?? null,
            'enabled' => $payload['enabled'] ?? true,
            'note' => $payload['note'] ?? null,
            'created_by' => $userId,
        ]);

        return $rule->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    public function delete(string $userId, string $profileId, string $ruleId): array
    {
        $profile = Profile::where('user_id', $userId)
            ->where('id', $profileId)
            ->firstOrFail();

        $rule = ProfileRule::where('profile_id', $profile->id)
            ->where('id', $ruleId)
            ->firstOrFail();

        $rule->delete();

        return [
            'id' => $ruleId,
            'deleted' => true,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function update(string $userId, string $profileId, string $ruleId, array $payload): array
    {
        $profile = Profile::where('user_id', $userId)
            ->where('id', $profileId)
            ->firstOrFail();

        $rule = ProfileRule::where('profile_id', $profile->id)
            ->where('id', $ruleId)
            ->firstOrFail();

        if (isset($payload['domain'])) {
            $rule->domain = (string) $payload['domain'];
            $rule->normalized_domain = DomainNormalizer::normalize((string) $payload['domain']);
        }

        if (isset($payload['match_type'])) {
            $rule->match_type = (string) $payload['match_type'];
        }

        if (isset($payload['list_type'])) {
            $listType = $this->normalizeListType((string) $payload['list_type']);
            $rule->list_type = $listType;
            $rule->action = $listType === 'allowlist' ? 'allow' : 'block';
        }

        if (array_key_exists('enabled', $payload)) {
            $rule->enabled = (bool) $payload['enabled'];
        }

        if (array_key_exists('note', $payload)) {
            $rule->note = $payload['note'];
        }

        $rule->save();

        return $rule->fresh()->toArray();
    }

    /**
     * @param array<int, string> $ruleIds
     * @return array<string, mixed>
     */
    public function batchDelete(string $userId, int|string $profileId, array $ruleIds): array
    {
        $profile = Profile::where('user_id', $userId)
            ->where('id', $profileId)
            ->firstOrFail();

        $existingIds = ProfileRule::where('profile_id', $profile->id)
            ->whereIn('id', $ruleIds)
            ->pluck('id')
            ->all();

        if ($existingIds === []) {
            return [
                'requested' => count($ruleIds),
                'deleted' => 0,
                'not_found' => array_values($ruleIds),
            ];
        }

        $notFound = array_values(array_diff($ruleIds, $existingIds));
        $deletedCount = ProfileRule::where('profile_id', $profile->id)
            ->whereIn('id', $existingIds)
            ->delete();

        return [
            'requested' => count($ruleIds),
            'deleted' => $deletedCount,
            'not_found' => $notFound,
        ];
    }

    private function normalizeListType(string $listType): string
    {
        return match ($listType) {
            'allow', 'allowlist' => 'allowlist',
            'block', 'blocklist' => 'blocklist',
            default => $listType,
        };
    }
}
