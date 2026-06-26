<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\AdminAuditLog;
use App\Models\PublishTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Publish Center — 增强 AdminPublishController
 *  提供版本历史、回滚、一键全量发布等接口
 */
final class AdminPublishCenterController
{
    public function versions(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 20);
        $page = (int) $request->input('page', 1);

        $query = PublishTask::query()
            ->whereIn('status', ['succeeded', 'partial', 'failed'])
            ->orderByDesc('completed_at');

        $total = (clone $query)->count();
        $rows = $query->paginate($perPage, ['*'], 'page', $page)->toArray();

        return response()->json([
            'data' => $rows['data'],
            'meta' => [
                'total' => $total,
                'per_page' => $rows['per_page'],
                'current_page' => $rows['current_page'],
                'last_page' => $rows['last_page'],
            ],
        ]);
    }

    public function rollback(Request $request, string $versionId): JsonResponse
    {
        $actorId = $request->user()?->admin_id;
        $source = PublishTask::findOrFail($versionId);

        if ($source->status !== 'succeeded') {
            return \App\Helpers\ApiResponse::error('INVALID_STATE', 'Only succeeded tasks can be rolled back.', 422);
        }

        $task = PublishTask::create([
            'profile_version_id' => $source->profile_version_id,
            'profile_id' => $source->profile_id,
            'status' => 'queued',
            'target_scope' => $source->target_scope,
            'target_filter' => $source->target_filter,
            'target_node_count' => $source->target_node_count,
            'applied_node_count' => 0,
            'failed_node_count' => 0,
            'retry_count' => 0,
            'message' => 'Rollback to ' . $versionId,
            'latest_error' => null,
            'queued_at' => now(),
        ]);

        AdminAuditLog::record('publish.rollback', 'publish_task', $task->id, ['rollback_from' => $versionId], $actorId, null, $request->ip(), $request->userAgent());

        return response()->json(['data' => $task->toArray()], 201);
    }

    public function syncAll(Request $request): JsonResponse
    {
        $actorId = $request->user()?->admin_id;
        $profiles = \App\Models\Profile::all();

        $created = 0;
        foreach ($profiles as $profile) {
            PublishTask::create([
                'config_version_id' => null,
                'profile_id' => $profile->id,
                'status' => 'queued',
                'target_scope' => 'all_nodes',
                'target_filter' => [],
                'target_node_count' => \App\Models\Node::online()->count(),
                'applied_node_count' => 0,
                'failed_node_count' => 0,
                'retry_count' => 0,
                'message' => 'Sync-all batch publish',
                'latest_error' => null,
                'queued_at' => now(),
            ]);
            $created++;
        }

        AdminAuditLog::record('publish.sync_all', 'publish_task', null, ['created' => $created], $actorId, null, $request->ip(), $request->userAgent());

        return response()->json(['data' => ['created' => $created]]);
    }
}
