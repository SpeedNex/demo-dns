<?php

namespace App\Http\Controllers\Api\V1\Agent;

use App\Domain\Ingest\QueryLogIngestService;
use App\Models\ConfigVersion;
use App\Models\Device;
use App\Models\Node;
use App\Models\QueryLogEntry;
use App\Models\QueryLogIngestBatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class QueryLogController
{
    public function batch(Request $request): JsonResponse
    {
        $service = new QueryLogIngestService();
        /** @var Node $node */
        $node = $request->attributes->get('node');
        $validated = $request->validate([
            'batch_id' => 'required|string|max:100',
            'items' => 'required|array|min:1|max:1000',
            'items.*.profile_id' => 'nullable|string|max:40',
            'items.*.device_id' => 'nullable|string|max:80',
            'items.*.query_name' => 'nullable|string|max:255',
            'items.*.domain' => 'nullable|string|max:255',
            'items.*.query_type' => 'nullable|string|max:20',
            'items.*.action' => 'required|string|max:20',
            'items.*.reason' => 'nullable|string|max:80',
            'items.*.category' => 'nullable|string|max:80',
            'items.*.client_ip' => 'nullable|string|max:64',
            'items.*.rcode' => 'nullable|integer|min:0|max:65535',
            'items.*.latency_ms' => 'nullable|integer|min:0|max:60000',
            'items.*.queried_at' => 'nullable|integer|min:0',
        ]);

        $result = $service->accept($validated);

        DB::transaction(function () use ($validated, $result, $node): void {
            $batch = QueryLogIngestBatch::create([
                'batch_id' => $validated['batch_id'],
                'node_id' => $node->id,
                'item_count' => count($validated['items']),
                'content_sha256' => $result['content_sha256'],
                'status' => 'accepted',
                'received_at' => now(),
                'written_at' => now(),
            ]);

            $latestUsersByProfile = ConfigVersion::query()
                ->select('profile_id', 'user_id')
                ->whereIn('profile_id', collect($validated['items'])->pluck('profile_id')->filter()->unique()->values())
                ->orderByDesc('version')
                ->get()
                ->unique('profile_id')
                ->pluck('user_id', 'profile_id');

            $now = now();
            $entries = [];
            $devices = [];
            foreach ($validated['items'] as $index => $item) {
                $profileId = $item['profile_id'] ?? null;
                $userId = $profileId !== null ? $latestUsersByProfile->get($profileId) : null;
                $queriedAt = isset($item['queried_at']) ? now()->setTimestamp((int) $item['queried_at']) : $now;
                $entries[] = [
                    'id' => 'qle_' . substr(hash('sha256', $validated['batch_id'] . '|' . $index . '|' . microtime(true)), 0, 12),
                    'ingest_batch_id' => $batch->id,
                    'node_id' => $node->id,
                    'user_id' => $userId,
                    'profile_id' => $profileId,
                    'device_id' => $item['device_id'] ?? null,
                    'query_name' => strtolower((string) ($item['query_name'] ?? $item['domain'] ?? '')),
                    'query_type' => $item['query_type'] ?? null,
                    'action' => strtolower((string) $item['action']),
                    'reason' => $item['reason'] ?? null,
                    'category' => $item['category'] ?? null,
                    'client_ip' => $item['client_ip'] ?? null,
                    'rcode' => (int) ($item['rcode'] ?? 0),
                    'latency_ms' => (int) ($item['latency_ms'] ?? 0),
                    'queried_at' => $queriedAt,
                    'created_at' => $now,
                ];

                if ($userId !== null && $profileId !== null) {
                    $rawDeviceId = trim((string) ($item['device_id'] ?? ''));
                    $clientIp = trim((string) ($item['client_ip'] ?? ''));
                    $identity = $rawDeviceId !== '' ? $rawDeviceId : ($clientIp !== '' ? 'ip:' . $clientIp : '');

                    if ($identity !== '') {
                        $devices[$userId . '|' . $profileId . '|' . $identity] = [
                            'user_id' => $userId,
                            'profile_id' => $profileId,
                            'device_id' => $identity,
                            'name' => $rawDeviceId !== '' ? $rawDeviceId : ('Device ' . $clientIp),
                            'device_type' => 'dns-client',
                            'public_ip' => $clientIp !== '' ? $clientIp : null,
                            'last_seen_at' => $queriedAt,
                            'updated_at' => $now,
                        ];
                    }
                }
            }

            QueryLogEntry::insert($entries);

            foreach ($devices as $device) {
                Device::query()->updateOrCreate(
                    [
                        'user_id' => $device['user_id'],
                        'profile_id' => $device['profile_id'],
                        'device_id' => $device['device_id'],
                    ],
                    $device + ['created_at' => $now]
                );
            }
        });

        return response()->json([
            'data' => $result,
        ]);
    }
}
