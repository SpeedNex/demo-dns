<?php

declare(strict_types=1);

namespace App\Application\Member;

use App\Domain\Profile\ProfileConfigBuilder;
use App\Domain\Profile\ProfilePublishService;
use App\Domain\Publish\PublishService;
use App\Models\Profile;
use App\Models\ProfileVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class ProfilePublishApplicationService
{
    public function __construct(
        private readonly ProfileConfigBuilder $configBuilder,
        private readonly PublishService $publishService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function publishForUser(string $userId, string $profileUid): array
    {
        $profile = Profile::where('user_id', $userId)
            ->where(function ($query) use ($profileUid): void {
                $query->where('profile_uid', $profileUid);
                if (ctype_digit($profileUid)) {
                    $query->orWhere('id', (int) $profileUid);
                }
            })
            ->firstOrFail();

        $rules = $profile->rules()->get()->toArray();
        $devices = $profile->devices()->get()->toArray();

        $featureSettings = [
            'security' => [
                'enabled' => (bool) $profile->security_enabled,
                'categories' => [
                    'malware' => (bool) ($profile->security_settings['malware'] ?? true),
                    'phishing' => (bool) ($profile->security_settings['phishing'] ?? true),
                ],
            ],
            'privacy' => [
                'enabled' => (bool) $profile->privacy_enabled,
                'log_mode' => 'full',
                'adblock_enabled' => false,
            ],
            'parental' => [
                'enabled' => (bool) $profile->parental_enabled,
                'safe_search' => (bool) $profile->safesearch_enabled,
                'adult' => true,
            ],
        ];

        $profilePublishService = new ProfilePublishService($this->configBuilder, $this->publishService);

        return DB::transaction(function () use ($profile, $profilePublishService, $featureSettings, $rules, $devices, $userId): array {
            $publishResult = $profilePublishService->publish(
                array_merge($profile->toArray(), [
                    'devices' => $devices,
                    'security_settings' => $featureSettings['security'],
                    'privacy_settings' => $featureSettings['privacy'],
                    'parental_settings' => $featureSettings['parental'],
                ]),
                $rules,
                $featureSettings,
                $this->loadQuotaData((int) $userId),
            );

            $newVersion = (int) ($profile->version ?? 1) + 1;

            ProfileVersion::create([
                'profile_id' => $profile->id,
                'version' => $newVersion,
                'status' => 'published',
                'rule_count' => count($rules),
                'config_json' => $publishResult['config_json'],
                'checksum' => $publishResult['checksum'],
                'message' => 'Published by member workspace',
                'published_at' => now(),
                'created_at' => now(),
            ]);

            $profile->update([
                'version' => $newVersion,
                'published_at' => now(),
            ]);

            return $publishResult;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function loadQuotaData(int $userId): array
    {
        $quota = [];

        try {
            $subscription = DB::table('subscriptions')
                ->where('user_id', $userId)
                ->where('status', 'active')
                ->orderByDesc('id')
                ->first(['quota_status', 'plan_id']);

            if ($subscription !== null && ($subscription->quota_status ?? 'normal') !== 'normal') {
                $quota['quota_status'] = $subscription->quota_status;
            }
        } catch (\Throwable $e) {
            Log::warning('loadQuotaData failed, using default quota', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }

        return $quota;
    }
}
