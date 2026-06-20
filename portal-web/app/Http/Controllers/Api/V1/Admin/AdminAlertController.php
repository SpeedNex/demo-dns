<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\Alert;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AdminAlertController
{
    public function index(Request $request): JsonResponse
    {
        $query = Alert::query();

        if ($request->filled('status')) {
            $query->where('status', (string) $request->input('status'));
        }

        if ($request->filled('level')) {
            $query->where('level', (string) $request->input('level'));
        }

        $perPage = min(max((int) $request->input('per_page', 20), 1), 100);
        $alerts = $query->orderByDesc('created_at')->paginate($perPage);

        return response()->json([
            'data' => $alerts->items(),
            'meta' => [
                'total' => $alerts->total(),
                'critical' => Alert::where('level', 'critical')->where('status', 'open')->count(),
                'warning' => Alert::where('level', 'warning')->where('status', 'open')->count(),
                'info' => Alert::where('level', 'info')->where('status', 'open')->count(),
                'page' => $alerts->currentPage(),
                'per_page' => $alerts->perPage(),
            ],
        ]);
    }

    public function acknowledge(Request $request, string $alertId): JsonResponse
    {
        $alert = Alert::find($alertId);
        if ($alert) {
            $alert->update([
                'status' => 'acknowledged',
                'acknowledged_by' => $request->user()?->admin_id,
                'acknowledged_at' => now(),
            ]);
        }

        return response()->json(['data' => ['ok' => true, 'id' => $alertId]]);
    }

    public function destroy(string $alertId): JsonResponse
    {
        $deleted = Alert::where('id', $alertId)->delete();

        return response()->json(['data' => ['deleted' => $deleted]]);
    }

    public function batchDestroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'string',
        ]);

        $deleted = Alert::whereIn('id', $validated['ids'])->delete();

        return response()->json(['data' => ['deleted' => $deleted]]);
    }
}
