<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\AdminAuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AdminAuditLogController
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'actor_id' => 'nullable|string',
            'action' => 'nullable|string|max:100',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
        ]);

        $query = AdminAuditLog::query();
        if ($request->filled('action')) {
            $query->where('action', 'like', '%' . (string) $request->input('action') . '%');
        }
        if ($request->filled('actor_id')) {
            $query->where('actor_admin_id', (string) $request->input('actor_id'));
        }
        if ($request->filled('from')) {
            $query->where('created_at', '>=', (string) $request->input('from'));
        }
        if ($request->filled('to')) {
            $query->where('created_at', '<=', (string) $request->input('to'));
        }
        $page = max(1, (int) $request->input('page', 1));
        $perPage = max(1, min(100, (int) $request->input('per_page', 50)));
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
}
