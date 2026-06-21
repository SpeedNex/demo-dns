<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\AdminAuditLog;
use App\Models\PublishTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
            'config_version_id' => 'required_without:profile_id|nullable',
            'profile_id' => 'required_without:config_version_id|nullable',
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

        // Count target nodes
        $targetNodeCount = match ($targetScope) {
            'all_nodes' => \App\Models\Node::where('status', 'online')->count(),
            'specific_nodes' => count($validated['target_node_ids'] ?? []),
            'all_profiles' => \App\Models\Node::where('status', 'online')->count(),
            default => 0,
        };

        $task = PublishTask::create([
            'config_version_id' => $validated['config_version_id'] ?? null,
            'profile_id' => $validated['profile_id'] ?? null,
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
            return response()->json(['error' => 'Task cannot be cancelled in current state.'], 422);
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
}
