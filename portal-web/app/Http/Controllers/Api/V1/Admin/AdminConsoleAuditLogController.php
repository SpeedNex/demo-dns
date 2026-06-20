<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\AdminAuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AdminConsoleAuditLogController
{
    public function index(Request $request): JsonResponse
    {
        $query = AdminAuditLog::query();

        if ($request->filled('action')) {
            $query->where('action', 'like', '%' . (string) $request->input('action') . '%');
        }

        if ($request->filled('actor_id')) {
            $query->where('actor_id', (string) $request->input('actor_id'));
        }

        if ($request->filled('target_type')) {
            $query->where('target_type', (string) $request->input('target_type'));
        }

        if ($request->filled('from')) {
            $query->where('created_at', '>=', (string) $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->where('created_at', '<=', (string) $request->input('to'));
        }

        $page = max(1, (int) $request->input('page', 1));
        $perPage = max(1, min(200, (int) $request->input('per_page', 50)));
        $total = (clone $query)->count();
        $items = $query->orderByDesc('created_at')->forPage($page, $perPage)->get()->toArray();

        return response()->json([
            'data' => $items,
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $actorId = $request->user()?->admin_id;
        $query = AdminAuditLog::query();

        if ($request->filled('action')) {
            $query->where('action', 'like', '%' . (string) $request->input('action') . '%');
        }

        $filename = 'admin_audit_logs_' . now()->format('Ymd_His') . '.ndjson';

        AdminAuditLog::record('audit_log.export', 'audit_log', null, ['filters' => $request->all()], $actorId, null, $request->ip(), $request->userAgent());

        return response()->streamDownload(function () use ($query): void {
            $out = fopen('php://output', 'wb');
            $query->orderBy('created_at')->chunk(500, function ($rows) use ($out): void {
                foreach ($rows as $row) {
                    fwrite($out, json_encode($row->toArray(), JSON_UNESCAPED_UNICODE) . "\n");
                }
            });
            fclose($out);
        }, $filename, [
            'Content-Type' => 'application/x-ndjson',
        ]);
    }

    public function batchDestroy(Request $request): JsonResponse
    {
        $actorId = $request->user()?->admin_id;
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'string',
        ]);

        // 检查是否有 24h 内的日志被选中删除
        $recentCount = AdminAuditLog::whereIn('id', $validated['ids'])
            ->where('created_at', '>=', now()->subHours(24))
            ->count();

        if ($recentCount > 0) {
            return response()->json([
                'message' => sprintf(
                    '%d of the selected audit logs were created within the last 24 hours and cannot be deleted.',
                    $recentCount
                ),
            ], 422);
        }

        $count = AdminAuditLog::whereIn('id', $validated['ids'])->delete();

        AdminAuditLog::record('audit_log.batch_delete', 'audit_log', null, ['ids' => $validated['ids'], 'count' => $count], $actorId, null, $request->ip(), $request->userAgent());

        return response()->json(['data' => ['deleted' => $count]]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $actorId = $request->user()?->admin_id;
        $log = AdminAuditLog::query()->find($id);

        if (! $log) {
            return response()->json(['message' => 'Audit log not found'], 404);
        }

        if ($log->created_at !== null && $log->created_at->gt(now()->subHours(24))) {
            return response()->json([
                'message' => 'Cannot delete audit logs created within the last 24 hours.',
            ], 422);
        }

        $deleted = $log->delete();

        AdminAuditLog::record('audit_log.delete', 'audit_log', $id, ['deleted' => $deleted > 0], $actorId, null, $request->ip(), $request->userAgent());

        return response()->json(['data' => ['deleted' => $deleted]]);
    }

    public function clear(Request $request): JsonResponse
    {
        $actorId = $request->user()?->admin_id;

        // 保护 24h 内的日志不被清空
        $count = AdminAuditLog::query()->where('created_at', '<', now()->subHours(24))->delete();

        AdminAuditLog::record('audit_log.clear', 'audit_log', null, ['deleted' => $count], $actorId, null, $request->ip(), $request->userAgent());

        return response()->json(['data' => ['deleted' => $count]]);
    }
}
