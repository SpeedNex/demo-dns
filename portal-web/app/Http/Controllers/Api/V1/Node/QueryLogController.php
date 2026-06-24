<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Node;

use App\Domain\Ingest\QueryLogIngestService;
use App\Infrastructure\ClickHouse\ClickHouseClient;
use App\Models\Device;
use App\Models\Node;
use App\Models\Profile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 2026-06-22: 查询日志只写 ClickHouse，不再经过 MySQL 中间表。
 * dns-resolver 上报失败时由其本地 buffer 重试。
 */
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
            'items.*.protocol' => 'nullable|string|max:16',
        ]);

        $result = $service->accept($validated);

        $now = now();
        $dnsLogs = [];
        $usageEvents = [];

        $profiles = Profile::query()
            ->whereIn('profile_uid', collect($validated['items'])->pluck('profile_id')->filter()->unique()->values())
            ->get(['id', 'profile_uid', 'user_id'])
            ->keyBy('profile_uid');

        foreach ($validated['items'] as $item) {
            $profileUid = $item['profile_id'] ?? null;
            $profile = $profileUid !== null ? $profiles->get($profileUid) : null;
            $profilePk = $profile?->id;
            $userPk = $profile?->user_id;
            $queriedAt = isset($item['queried_at']) ? now()->setTimestamp((int) $item['queried_at']) : $now;
            $queryName = strtolower((string) ($item['query_name'] ?? $item['domain'] ?? ''));
            $domain = strtolower((string) ($item['domain'] ?? $item['query_name'] ?? ''));
            $clientIp = trim((string) ($item['client_ip'] ?? ''));
            // P1: 防御性剥离端口号（127.0.0.1:55309 → 127.0.0.1），
            // 防止 resolver 上报时意外包含端口导致设备识别失真
            if ($clientIp !== '' && substr_count($clientIp, ':') === 1) {
                $parts = explode(':', $clientIp);
                $clientIp = $parts[0];
            }
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
                // 2026-06-24: 优先按 (profile_id, fingerprint) 查找,
                // 避免 device_uid 为空时 INSERT 重复记录导致 uniq_devices_profile_fingerprint 冲突。
                // fingerprint 已经包含 profile_id + protocol + clientIp + deviceUid,
                // 足以唯一标识一台设备。
                $fingerprint = hash('sha256', implode('|', [
                    (string) $profilePk,
                    'doh',
                    $clientIp,
                    $deviceUid,
                ]));
                $device = Device::query()
                    ->where('profile_id', $profilePk)
                    ->where('fingerprint', $fingerprint)
                    ->first();

                if (! $device) {
                    // 2026-06-24: 先按 fingerprint 查不到，再按 device_uid 查
                    // (同客户端可能因间隙性 fingerprint 变化导致首次 lookup miss)
                    if ($deviceUid !== '') {
                        $device = Device::query()
                            ->where('profile_id', $profilePk)
                            ->where('device_uid', $deviceUid)
                            ->first();
                    }

                    if (! $device) {
                        $device = Device::query()->create([
                            'user_id' => $userPk,
                            'profile_id' => $profilePk,
                            'device_uid' => $deviceUid !== '' ? $deviceUid : 'dev_' . substr(hash('sha256', $clientIp), 0, 16),
                            'fingerprint' => $fingerprint,
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
                        // 通过 device_uid 找到已有设备，更新 fingerprint
                        $device->forceFill([
                            'fingerprint' => $fingerprint,
                            'last_seen_at' => $queriedAt,
                            'last_query_at' => $queriedAt,
                            'updated_at' => $now,
                        ])->save();
                        $device->increment('query_count');
                    }
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
                'event_id' => \Illuminate\Support\Str::uuid()->toString(),
                'event_time' => $queriedAt->format('Y-m-d H:i:s'),
                'timestamp' => $queriedAt->format('Y-m-d H:i:s'),
                'node_id' => (string) $node->id,
                'user_id' => $userPk !== null ? (string) $userPk : '',
                'profile_id' => $profileUid ?? '',
                'device_id' => $deviceUid,
                'domain' => $domain,
                'query_type' => strtoupper((string) ($item['query_type'] ?? 'A')),
                'action' => strtoupper((string) $item['action']),
                'reason' => (string) ($item['reason'] ?? ''),
                'category' => (string) ($item['category'] ?? ''),
                'client_ip' => $clientIp,
                'rcode' => (int) ($item['rcode'] ?? 0),
                'latency_ms' => (int) ($item['latency_ms'] ?? 0),
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

        // 直接写入 ClickHouse，不再经过 MySQL batch/error 表。
        // dns-resolver 的本地 buffer 会在写入失败时自动重试。
        try {
            $clickhouse->insertJsonEachRow('dns_logs', $dnsLogs);
            $clickhouse->insertJsonEachRow('usage_events', $usageEvents);
        } catch (\Throwable $e) {
            return response()->json([
                'data' => $result,
                'error' => 'clickhouse insert failed: ' . $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'data' => $result,
        ]);
    }
}
