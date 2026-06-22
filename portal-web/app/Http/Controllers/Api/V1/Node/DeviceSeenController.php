<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Node;

use App\Models\Device;
use App\Models\Node;
use App\Models\Profile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class DeviceSeenController
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'profile_uid' => 'required|string|size:6',
            'device_name' => 'nullable|string|max:120',
            'protocol' => 'required|string|in:doh,dot,doq,udp,tcp',
            'client_ip' => 'nullable|string|max:45',
            'user_agent' => 'nullable|string|max:1000',
            'sni' => 'nullable|string|max:255',
        ]);

        /** @var Node $node */
        $node = $request->attributes->get('node');
        $profile = Profile::query()->where('profile_uid', $validated['profile_uid'])->firstOrFail();
        $now = now();
        $clientIp = (string) ($validated['client_ip'] ?? $request->ip() ?? '');
        $userAgent = trim((string) ($validated['user_agent'] ?? ''));
        $sni = trim((string) ($validated['sni'] ?? ''));
        $fingerprint = hash('sha256', implode('|', [
            (string) $profile->id,
            $validated['protocol'],
            $clientIp,
            $userAgent,
            $sni,
        ]));

        $device = Device::query()->updateOrCreate(
            [
                'profile_id' => $profile->id,
                'fingerprint' => $fingerprint,
            ],
            [
                'user_id' => $profile->user_id,
                'device_uid' => 'dev_' . substr($fingerprint, 0, 16),
                'name' => $validated['device_name'] ?? ('Node ' . $node->node_id),
                'source' => 'auto',
                'protocol' => $validated['protocol'],
                'user_agent' => $userAgent !== '' ? $userAgent : null,
                'sni' => $sni !== '' ? $sni : null,
                'ip_hash' => $clientIp !== '' ? hash('sha256', $clientIp) : null,
                'first_seen_at' => DB::raw('COALESCE(first_seen_at, NOW())'),
                'last_seen_at' => $now,
                'last_query_at' => $now,
                'query_count' => DB::raw('COALESCE(query_count, 0) + 1'),
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        return response()->json([
            'data' => [
                'device_id' => $device->device_uid,
                'created' => $device->wasRecentlyCreated,
            ],
        ]);
    }
}
