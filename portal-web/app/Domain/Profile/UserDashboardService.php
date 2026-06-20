<?php

namespace App\Domain\Profile;

use App\Models\Device;
use App\Models\Profile;
use App\Models\User;

final class UserDashboardService
{
    public function __construct(
        private readonly UserWorkspaceService $workspace = new UserWorkspaceService(),
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getOverview(string $userId): array
    {
        $this->workspace->primaryProfile($userId);
        $profiles = Profile::where('user_id', $userId)->get();
        $deviceCount = Device::where('user_id', $userId)->count();

        try {
            $analytics = $this->workspace->analytics($userId);
        } catch (\Throwable) {
            $analytics = [
                'today_queries' => 0,
                'today_blocked' => 0,
                'period_queries' => 0,
            ];
        }

        $profileCount = $profiles->count();

        return [
            'user' => [
                'id' => $userId,
                'plan_code' => User::findOrFail($userId)->plan_code ?: 'free',
            ],
            'stats' => [
                'profile_count' => $profileCount,
                'device_count' => $deviceCount,
                'today_queries' => $analytics['today_queries'] ?? 0,
                'today_blocked' => $analytics['today_blocked'] ?? 0,
            ],
            'profiles' => $profiles->toArray(),
        ];
    }
}
