<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\QueryLogEntry;
use App\Models\AdminAuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AdminQueryLogController
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'domain' => 'nullable|string|max:255',
            'action' => 'nullable|string|max:20',
            'profile_id' => 'nullable|string|max:40',
            'start_time' => 'nullable|date',
            'end_time' => 'nullable|date',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'export' => 'nullable|boolean',
        ]);

        $query = QueryLogEntry::query();

        if (! empty($validated['domain'])) {
            $query->where('query_name', 'like', '%' . $validated['domain'] . '%');
        }

        if (! empty($validated['action'])) {
            $query->where('action', $validated['action']);
        }

        if (! empty($validated['profile_id'])) {
            $query->where('profile_id', $validated['profile_id']);
        }

        if (! empty($validated['start_time'])) {
            $query->where('queried_at', '>=', $validated['start_time']);
        }

        if (! empty($validated['end_time'])) {
            $query->where('queried_at', '<=', $validated['end_time']);
        }

        // 导出模式返回全部匹配数据
        if (! empty($validated['export'])) {
            $all = $query->orderByDesc('queried_at')->limit(10000)->get();

            return response()->json(['data' => $all]);
        }

        $perPage = (int) ($validated['per_page'] ?? 20);
        $paginator = $query->orderByDesc('queried_at')->paginate(min($perPage, 100));

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'page' => $paginator->currentPage(),
            ],
        ]);
    }

    public function batchDestroy(Request $request): JsonResponse
    {
        $actorId = $request->user()?->id;
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'string',
        ]);

        $deleted = QueryLogEntry::whereIn('id', $validated['ids'])->delete();

        AdminAuditLog::record('query_logs.batch_destroy', 'query_log_entry', null, ['count' => $deleted], $actorId, null, $request->ip(), $request->userAgent());

        return response()->json(['data' => ['deleted' => $deleted]]);
    }

    public function clearAll(Request $request): JsonResponse
    {
        $actorId = $request->user()?->id;
        $deleted = QueryLogEntry::query()->delete();

        AdminAuditLog::record('query_logs.clear_all', 'query_log_entry', null, ['count' => $deleted], $actorId, null, $request->ip(), $request->userAgent());

        return response()->json(['data' => ['deleted' => $deleted]]);
    }
}
