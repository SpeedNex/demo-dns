<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Node;

use App\Domain\Ingest\QueryLogIngestService;
use App\Infrastructure\ClickHouse\ClickHouseClient;
use App\Models\Device;
use App\Models\Node;
use App\Models\Profile;
use App\Models\QueryLogIngestBatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class QueryLogController
{
    public function batch(Request $request): JsonResponse
    {
        $service = new QueryLogIngestService();
        $clickhouse = new ClickHouseClient();

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
            // 2026-06-22: dns-resolver 上报协议 doh/dot/udp/tcp，用于按协议分账
            'items.*.protocol' => 'nullable|string|max:16',
        ]);

        $result = $service->accept($validated);

        DB::transaction(function () use ($validated, $node, $clickhouse): void {
            $now = now();
            $batch = QueryLogIngestBatch::create([
                'batch_id' => $validated['batch_id'],
                'node_id' => $node->id,
                'item_count' => count($validated['items']),
                'event_count' => count($validated['items']),
                'status' => 'processing',
                // 2026-06-22: 原始 items 落 MySQL，CH 失败时给 retry-failed-batches 直接喂数据
                'raw_payload' => $validated['items'],
                'received_at' => $now,
            ]);

            $profiles = Profile::query()
                ->whereIn('profile_uid', collect($validated['items'])->pluck('profile_id')->filter()->unique()->values())
                ->get(['id', 'profile_uid', 'user_id'])
                ->keyBy('profile_uid');

            $dnsLogs = [];
            $usageEvents = [];

            foreach ($validated['items'] as $item) {
                $profileUid = $item['profile_id'] ?? null;
                $profile = $profileUid !== null ? $profiles->get($profileUid) : null;
                $profilePk = $profile?->id;
                $userPk = $profile?->user_id;
                $queriedAt = isset($item['queried_at']) ? now()->setTimestamp((int) $item['queried_at']) : $now;
                $queryName = strtolower((string) ($item['query_name'] ?? $item['domain'] ?? ''));
                $domain = strtolower((string) ($item['domain'] ?? $item['query_name'] ?? ''));
                $clientIp = trim((string) ($item['client_ip'] ?? ''));
                $devicePk = null;
                $deviceUid = trim((string) ($item['device_id'] ?? ''));

                // Fallback: profile not found via profile_uid → try resolving via device_id.
                if ($profilePk === null && $deviceUid !== '') {
                    $dev = Device::query()
                        ->where('device_uid', $deviceUid)
                        ->whereNotNull('profile_id')
                        ->orderByDesc('last_seen_at')
                        ->first(['profile_id', 'user_id']);
                    if ($dev) {
                        $profilePk = $dev->profile_id;
                        $userPk = $dev->user_id;
                        $fingerprint = hash('sha256', 'resolver-fallback|' . $deviceUid);
                        $dev->forceFill([
                            'last_seen_at' => $queriedAt,
                            'last_query_at' => $queriedAt,
                            'updated_at' => $now,
                        ])->save();
                        $dev->increment('query_count');
                        $devicePk = $dev->id;
                    }
                }

                if ($userPk !== null && $profilePk !== null) {
                    $device = Device::query()
                        ->where('profile_id', $profilePk)
                        ->where('device_uid', $deviceUid !== '' ? $deviceUid : 'dev_localhost')
                        ->first();

                    if (! $device) {
                        $device = Device::query()->create([
                            'user_id' => $userPk,
                            'profile_id' => $profilePk,
                            'device_uid' => $deviceUid !== '' ? $deviceUid : 'dev_' . substr(hash('sha256', $clientIp), 0, 16),
                            'fingerprint' => hash('sha256', implode('|', [
                                (string) $profilePk,
                                'doh',
                                $clientIp,
                                $deviceUid,
                            ])),
                            'name' => $deviceUid !== '' ? $deviceUid : ('Device ' . ($clientIp !== '' ? $clientIp : substr(hash('sha256', $clientIp), 0, 6))),
                            'source' => 'auto',
                            'protocol' => 'doh',
                            'ip_hash' => $clientIp !== '' ? hash('sha256', $clientIp) : null,
                            'first_seen_at' => $queriedAt,
                            'last_seen_at' => $queriedAt,
                            'last_query_at' => $queriedAt,
                            'query_count' => 1,
                            'status' => 'active',
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    } else {
                        $device->forceFill([
                            'last_seen_at' => $queriedAt,
                            'last_query_at' => $queriedAt,
                            'updated_at' => $now,
                        ])->save();
                        $device->increment('query_count');
                    }
                    $devicePk = $device->id;
                    $deviceUid = $device->device_uid;
                }

                $dnsLogs[] = [
                    'event_time' => $queriedAt->format('Y-m-d H:i:s'),
                    'timestamp' => $queriedAt->format('Y-m-d H:i:s'),
                    'node_id' => (string) $node->id,
                    'user_id' => $userPk !== null ? (string) $userPk : '',
                    'profile_id' => $profileUid ?? '',
                    'device_id' => $deviceUid,
                    'query_name' => $queryName,
                    'domain' => $domain,
                    'query_type' => strtoupper((string) ($item['query_type'] ?? 'A')),
                    'action' => strtoupper((string) $item['action']),
                    'reason' => (string) ($item['reason'] ?? ''),
                    'category' => (string) ($item['category'] ?? ''),
                    'client_ip' => $clientIp,
                    'rcode' => (int) ($item['rcode'] ?? 0),
                    'latency_ms' => (int) ($item['latency_ms'] ?? 0),
                    // 2026-06-22: 透传协议，CH 列 protocol（ALTER TABLE dns_logs ADD COLUMN protocol String）
                    'protocol' => strtolower((string) ($item['protocol'] ?? '')),
                ];

                if ($userPk !== null && $profilePk !== null) {
                    $usageEvents[] = [
                        'timestamp' => $queriedAt->format('Y-m-d H:i:s'),
                        'user_id' => (string) $userPk,
                        'profile_id' => (int) $profilePk,
                        'device_id' => $devicePk,
                        'billing_category' => strtolower((string) ($item['category'] ?? 'query')),
                    ];
                }
            }

            // 2026-06-22: 用户查询日志只存 ClickHouse（dns_logs / usage_events），
            // 不再写入 MySQL dns_query_log_entries，MySQL 仅保留 batch 审计与重试元数据。
            // 重试数据从 dns_query_log_ingest_batches.raw_payload 取。

            try {
                $clickhouse->insertJsonEachRow('dns_logs', $dnsLogs);
                $clickhouse->insertJsonEachRow('usage_events', $usageEvents);
                $batch->update([
                    'status' => 'succeeded',
                    'forwarded_to_clickhouse' => true,
                    'processed_at' => $now,
                    'updated_at' => $now,
                ]);
            } catch (\Throwable $e) {
                DB::table('query_log_ingest_errors')->insert([
                    'batch_id' => $batch->id,
                    'node_id' => $node->id,
                    'error_type' => 'clickhouse_insert_failed',
                    'error_message' => $e->getMessage(),
                    'raw_payload' => json_encode($validated['items'], JSON_UNESCAPED_UNICODE),
                    'occurred_at' => $now,
                    'created_at' => $now,
                ]);
                $batch->update([
                    'status' => 'partial',
                    'error_message' => $e->getMessage(),
                    'processed_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });

        return response()->json([
            'data' => $result,
        ]);
    }
}
