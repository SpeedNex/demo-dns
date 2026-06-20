<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\Device;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

final class AdminDeviceController
{
    public function index(Request $request): JsonResponse
    {
        $query = Device::query()->with('user:id,username,email');

        if ($deviceName = $request->input('device_name')) {
            $query->where('name', 'like', "%{$deviceName}%");
        }

        $perPage = (int) $request->input('per_page', 20);
        $paginator = $query->orderByDesc('last_seen_at')->paginate(min($perPage, 100));

        $items = array_map(function ($d) {
            return [
                'id' => $d['id'],
                'device_name' => $d['name'],
                'device_type' => $d['protocol'],
                'source_ip' => $d['ip_hash'] ? 'hashed' : null,
                'is_online' => $d['last_seen_at'] !== null && Carbon::parse($d['last_seen_at'])->gt(now()->subMinutes(5)),
                'last_seen_at' => $d['last_seen_at'],
                'user_email' => $d['user']['email'] ?? null,
                'user_name' => $d['user']['username'] ?? null,
            ];
        }, $paginator->items());

        return response()->json([
            'data' => $items,
            'meta' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'page' => $paginator->currentPage(),
            ],
        ]);
    }

    public function show(string $deviceId): JsonResponse
    {
        $device = Device::with('user:id,username,email')->find($deviceId);
        if (! $device) {
            return response()->json(['message' => 'Device not found'], 404);
        }

        return response()->json([
            'data' => [
                'id' => $device['id'],
                'device_name' => $device['name'],
                'device_type' => $device['protocol'],
                'source_ip' => $device['ip_hash'] ? 'hashed' : null,
                'is_online' => $device['last_seen_at'] !== null && $device['last_seen_at']->gt(now()->subMinutes(5)),
                'last_seen_at' => $device['last_seen_at'],
                'user_email' => $device['user']['email'] ?? null,
                'user_name' => $device['user']['username'] ?? null,
            ],
        ]);
    }

    public function destroy(string $deviceId): JsonResponse
    {
        $device = Device::find($deviceId);
        if (! $device) {
            return response()->json(['message' => 'Device not found'], 404);
        }

        $device->delete();

        return response()->json(['message' => 'Device deleted']);
    }

    public function batchDestroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'string',
        ]);

        $deleted = Device::whereIn('id', $validated['ids'])->delete();

        return response()->json(['message' => "Deleted {$deleted} device(s).", 'deleted' => $deleted]);
    }
}
