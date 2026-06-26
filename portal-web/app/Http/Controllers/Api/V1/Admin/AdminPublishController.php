<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\AdminAuditLog;
use App\Models\PublishTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

final class AdminPublishController
{
    public function index(Request $request): JsonResponse
    {
        $query = PublishTask::query();

        if ($request->filled('status')) {
            $query->where('status', (string) $request->input('status'));
        }

        // Pagination support
        $perPage = (int) $request->input('per_page', 20);
        $page = (int) $request->input('page', 1);

        $tasks = $query->orderByDesc('queued_at')->paginate($perPage, ['*'], 'page', $page);
        $tasksArray = $tasks->toArray();

        $pending = 0;
        $completed = 0;
        $failed = 0;
        $cancelled = 0;
        foreach ($tasksArray['data'] as $task) {
            switch ($task['status']) {
                case 'queued':
                case 'running':
                    $pending++;
                    break;
                case 'succeeded':
                case 'partial':
                    $completed++;
                    break;
                case 'failed':
                    $failed++;
                    break;
            }
        }

        return response()->json([
            'data' => $tasksArray['data'],
            'meta' => [
                'total' => $tasksArray['total'],
                'per_page' => $tasksArray['per_page'],
                'current_page' => $tasksArray['current_page'],
                'last_page' => $tasksArray['last_page'],
                'pending' => $pending,
                'completed' => $completed,
                'failed' => $failed,
                'cancelled' => $cancelled,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $actorId = $request->user()?->admin_id;
        $validated = $request->validate([
            'message' => 'required|string|max:500',
            'profile_version_id' => 'required_without:profile_id|nullable',
            'profile_id' => 'required_without:profile_version_id|nullable',
            'target_scope' => 'sometimes|string|in:all_nodes,specific_nodes,all_profiles',
            'target_node_ids' => 'sometimes|array',
            'target_node_ids.*' => 'required',
        ]);

        $targetScope = $this->mapTargetScope($validated['target_scope'] ?? 'all_nodes');
        $targetFilter = null;

        if ($targetScope === 'node' && !empty($validated['target_node_ids'])) {
            $targetFilter = ['node_ids' => $validated['target_node_ids']];
        } else {
            $targetFilter = [];
        }

        // Count target nodes — 2026-06-22: 用 Node::online() scope 取代 where('status','online')。
        $targetNodeCount = match ($targetScope) {
            'all_nodes' => \App\Models\Node::online()->count(),
            'specific_nodes' => count($validated['target_node_ids'] ?? []),
            'all_profiles' => \App\Models\Node::online()->count(),
            default => 0,
        };

        // 解析 profile_id：兼容传入 profile_id 字符串或整数 pk
        $profileId = $validated['profile_id'] ?? null;
        if ($profileId !== null && $profileId !== '' && !ctype_digit((string) $profileId)) {
            $profile = \App\Models\Profile::where('profile_id', $profileId);
            if (\Illuminate\Support\Facades\Schema::hasColumn('profiles', 'profile_uid')) {
                $profile = $profile->orWhere('profile_uid', $profileId);
            }
            $resolvedId = $profile->value('id');
            $profileId = $resolvedId !== null ? (int) $resolvedId : throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException('Profile not found');
        }

        $task = PublishTask::create([
            'profile_version_id' => $validated['profile_version_id'] ?? null,
            'profile_id' => $profileId !== null ? (int) $profileId : null,
            'status' => 'queued',
            'target_scope' => $targetScope,
            'target_filter' => $targetFilter,
            'target_node_count' => $targetNodeCount,
            'applied_node_count' => 0,
            'failed_node_count' => 0,
            'retry_count' => 0,
            'message' => $validated['message'],
            'latest_error' => null,
            'queued_at' => now(),
            'started_at' => null,
            'completed_at' => null,
        ]);

        AdminAuditLog::record('publish.create', 'publish_task', $task->id, [
            'message' => $task->message,
            'target_scope' => $targetScope,
            'target_node_count' => $targetNodeCount,
        ], $actorId, null, $request->ip(), $request->userAgent());

        return response()->json([
            'data' => $task->toArray(),
            'meta' => [
                'message' => 'Publish task created successfully',
            ],
        ], 201);
    }

    public function retry(Request $request, string $taskId): JsonResponse
    {
        $actorId = $request->user()?->admin_id;
        $task = PublishTask::query()->findOrFail($taskId);
        $task->update([
            'status' => 'queued',
            'retry_count' => $task->retry_count + 1,
            'latest_error' => null,
            'completed_at' => null,
            'queued_at' => now(),
        ]);

        AdminAuditLog::record('publish.retry', 'publish_task', $taskId, [], $actorId, null, $request->ip(), $request->userAgent());

        return response()->json([
            'data' => [
                'id' => $taskId,
                'status' => 'queued',
                'message' => 'Retry initiated',
                'retried_at' => now()->toIso8601String(),
            ],
        ]);
    }

    public function cancel(Request $request, string $taskId): JsonResponse
    {
        $actorId = $request->user()?->admin_id;
        $task = PublishTask::query()->findOrFail($taskId);

        if (in_array($task->status, ['succeeded', 'failed'], true)) {
            return \App\Helpers\ApiResponse::error('INVALID_STATE', 'Task cannot be cancelled in current state.', 422);
        }

        $task->update([
            'status' => 'failed',
            'completed_at' => now(),
            'latest_error' => 'Cancelled by admin',
        ]);

        AdminAuditLog::record('publish.cancel', 'publish_task', $taskId, [], $actorId, null, $request->ip(), $request->userAgent());

        return response()->json([
            'data' => [
                'id' => $taskId,
                'status' => 'cancelled',
                'cancelled_at' => now()->toIso8601String(),
            ],
        ]);
    }

    public function batchRetry(Request $request): JsonResponse
    {
        $actorId = $request->user()?->admin_id;
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'required',
        ]);

        $count = PublishTask::whereIn('id', $validated['ids'])
            ->whereIn('status', ['failed'])
            ->update([
                'status' => 'queued',
                'latest_error' => null,
                'completed_at' => null,
                'queued_at' => now(),
                'updated_at' => now(),
            ]);

        AdminAuditLog::record('publish.batch_retry', 'publish_task', null, ['ids' => $validated['ids'], 'count' => $count], $actorId, null, $request->ip(), $request->userAgent());

        return response()->json(['data' => ['retried' => $count]]);
    }

    public function batchCancel(Request $request): JsonResponse
    {
        $actorId = $request->user()?->admin_id;
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'required',
        ]);

        $count = PublishTask::whereIn('id', $validated['ids'])
            ->whereIn('status', ['queued', 'running'])
            ->update([
                'status' => 'failed',
                'completed_at' => now(),
                'latest_error' => 'Batch cancelled by admin',
                'updated_at' => now(),
            ]);

        AdminAuditLog::record('publish.batch_cancel', 'publish_task', null, ['ids' => $validated['ids'], 'count' => $count], $actorId, null, $request->ip(), $request->userAgent());

        return response()->json(['data' => ['cancelled' => $count]]);
    }

    public function cleanupCompleted(Request $request): JsonResponse
    {
        $actorId = $request->user()?->admin_id;
        $validated = $request->validate([
            'older_than_days' => 'integer|min:1|max:3650',
        ]);

        $cutoff = now()->subDays($validated['older_than_days'] ?? 30);
        $count = PublishTask::where('status', 'succeeded')
            ->where('completed_at', '<', $cutoff)
            ->delete();

        AdminAuditLog::record('publish.cleanup', 'publish_task', null, ['older_than_days' => $validated['older_than_days'] ?? 30, 'count' => $count], $actorId, null, $request->ip(), $request->userAgent());

        return response()->json(['data' => ['deleted' => $count]]);
    }

    private function mapTargetScope(string $scope): string
    {
        return match ($scope) {
            'specific_nodes' => 'node',
            'all_profiles' => 'profile',
            default => $scope,
        };
    }

    /**
     * 列出所有 Profile 及其发布状态
     */
    public function profilePublishList(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 50);
        $page = (int) $request->input('page', 1);
        $search = $request->input('search', '');

        $query = \App\Models\Profile::with(['user:uid,username,email'])
            ->leftJoin('profile_versions', 'profile_versions.target_profile_id', '=', 'profiles.id')
            ->select('profiles.*', \Illuminate\Support\Facades\DB::raw('COUNT(dns_profile_versions.id) as profile_versions_count'))
            ->groupBy('profiles.id')
            ->orderByDesc('profiles.created_at');

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('profiles.name', 'like', "%{$search}%")
                    ->orWhere('profiles.profile_id', 'like', "%{$search}%");
                if (\Illuminate\Support\Facades\Schema::hasColumn('profiles', 'profile_uid')) {
                    $q->orWhere('profiles.profile_uid', 'like', "%{$search}%");
                }
            });
        }

        $profiles = $query->paginate($perPage, ['*'], 'page', $page);
        $profilesArray = $profiles->toArray();

        $data = collect($profilesArray['data'])->map(fn ($profile): array => [
            'id' => $profile['id'],
            'profile_id' => $profile['profile_id'] ?? $profile['profile_uid'] ?? '',
            'name' => $profile['name'],
            'user_id' => $profile['user_id'],
            'username' => $profile['user']['username'] ?? null,
            'email' => $profile['user']['email'] ?? null,
            'version' => $profile['version'],
            'status' => $profile['status'],
            'published_at' => $profile['published_at'],
            'created_at' => $profile['created_at'],
            'has_published_config' => ($profile['config_versions_count'] ?? 0) > 0,
            'config_versions_count' => $profile['config_versions_count'] ?? 0,
        ])->all();

        return response()->json([
            'data' => $data,
            'meta' => [
                'total' => $profilesArray['total'],
                'per_page' => $profilesArray['per_page'],
                'current_page' => $profilesArray['current_page'],
                'last_page' => $profilesArray['last_page'],
            ],
        ]);
    }

    /**
     * 手动发布指定 Profile
     */
    public function publishProfile(Request $request, string $profileId): JsonResponse
    {
        $actorId = $request->user()?->admin_id;

        $profile = \App\Models\Profile::where(function ($q) use ($profileId): void {
            $q->where('profile_id', $profileId);
            if (\Illuminate\Support\Facades\Schema::hasColumn('profiles', 'profile_uid')) {
                $q->orWhere('profile_uid', $profileId);
            }
            if (ctype_digit($profileId)) {
                $q->orWhere('id', (int) $profileId);
            }
        })->firstOrFail();

        $profilePublishService = app(\App\Domain\Profile\ProfilePublishService::class);
        $publishService = app(\App\Domain\Publish\PublishService::class);

        $configBuilder = app(\App\Domain\Profile\ProfileConfigBuilder::class);
        $profilePublishService = new \App\Domain\Profile\ProfilePublishService($configBuilder, $publishService);

        $result = $profilePublishService->publish(
            array_merge($profile->toArray(), [
                'profile_id' => $profile->profile_id ?? $profile->profile_uid,
                'devices' => $profile->devices()->get()->toArray(),
            ]),
            $profile->rules()->get()->toArray(),
            ['security_enabled' => $profile->security_enabled],
        );

        // 更新 profiles.published_at
        $profile->updateQuietly(['published_at' => now()]);

        AdminAuditLog::record('publish.profile', 'profile', $profile->id, [
            'profile_id' => $profile->profile_id ?? $profile->profile_uid,
            'profile_name' => $profile->name,
            'config_version' => $result['config_version'],
        ], $actorId, null, $request->ip(), $request->userAgent());

        return response()->json([
            'data' => [
                'profile_id' => $profile->profile_id ?? $profile->profile_uid,
                'profile_name' => $profile->name,
                'config_version' => $result['config_version'],
                'publish_id' => $result['publish_id'],
                'status' => $result['publish_status'],
                'message' => 'Profile published successfully',
            ],
        ]);
    }

    /**
     * 一键全量发布所有 Profile
     */
    public function syncAll(Request $request): JsonResponse
    {
        $actorId = $request->user()?->admin_id;
        $profiles = \App\Models\Profile::all();

        $configBuilder = app(\App\Domain\Profile\ProfileConfigBuilder::class);
        $publishService = app(\App\Domain\Publish\PublishService::class);
        $profilePublishService = new \App\Domain\Profile\ProfilePublishService($configBuilder, $publishService);

        $results = [];
        $errors = [];
        foreach ($profiles as $profile) {
            $profileId = $profile->profile_id ?? $profile->profile_uid;
            try {
                $result = $profilePublishService->publish(
                    array_merge($profile->toArray(), [
                        'profile_id' => $profileId,
                        'devices' => $profile->devices()->get()->toArray(),
                    ]),
                    $profile->rules()->get()->toArray(),
                    ['security_enabled' => $profile->security_enabled],
                );

                // 更新 profiles.published_at，确保前端显示发布时间
                $profile->updateQuietly(['published_at' => now()]);

                AdminAuditLog::record('publish.profile', 'profile', $profile->id, [
                    'profile_id' => $profileId,
                    'profile_name' => $profile->name,
                    'config_version' => $result['config_version'],
                ], $actorId, null, $request->ip(), $request->userAgent());

                $results[] = [
                    'profile_id' => $profileId,
                    'profile_name' => $profile->name,
                    'config_version' => $result['config_version'],
                    'status' => 'ok',
                ];
            } catch (\Throwable $e) {
                $errors[] = [
                    'profile_id' => $profileId,
                    'profile_name' => $profile->name,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'data' => [
                'total' => count($profiles),
                'succeeded' => count($results),
                'failed' => count($errors),
                'results' => $results,
                'errors' => $errors,
            ],
            'message' => count($errors) > 0
                ? 'Published with ' . count($errors) . ' error(s)'
                : 'All profiles published successfully',
        ]);
    }

    /**
     * 清空 PHP 缓存（optimize:clear），用于后台运维
     */
    public function clearCache(): JsonResponse
    {
        try {
            Artisan::call('optimize:clear');
            $output = Artisan::output();

            AdminAuditLog::record('system.cache_clear', 'system', '0', [
                'output' => $output,
            ], null, null, request()->ip(), request()->userAgent());

            return response()->json([
                'message' => 'Cache cleared successfully',
                'output' => $output,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Failed to clear cache: ' . $e->getMessage(),
            ], 500);
        }
    }
}