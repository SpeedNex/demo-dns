<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Node;

use App\Domain\Ingest\QueryLogIngestService;
use App\Infrastructure\ClickHouse\ClickHouseClient;
use App\Models\Device;
use App\Models\Node;
use App\Models\Profile;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * 2026-06-22: 查询日志只写 ClickHouse，不再经过 MySQL 中间表。
 * dns-resolver 上报失败时由其本地 buffer 重试。
 */

final class QueryLogController
{
    private const BATCH_DEDUP_TTL = 3600; // 1小时内相同 batch_id 只处理一次

    public function batch(Request $request): JsonResponse
    {
        $clickhouse = new ClickHouseClient();

        /** @var Node $node */
        $node = $request->attributes->get('node');

        $validated = $request->validate([
            'batch_id' => 'required|string|max:100',
            'items' => 'required|array|min:1|max:1000',
            'items.*.profile_id' => 'nullable|string|max:40',
            'items.*.device_id' => 'nullable|string|max:80',
            'items.*.device_type' => 'nullable|string|max:30',
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

        // 幂等检查：相同 batch_id 在 1 小时内只处理一次
        $dedupKey = 'querylog:batch:' . $validated['batch_id'];
        try {
            $alreadyProcessed = \Illuminate\Support\Facades\Redis::exists($dedupKey);
            if ($alreadyProcessed) {
                return response()->json([
                    'data' => ['deduped' => true, 'batch_id' => $validated['batch_id']],
                ]);
            }
            // SETNX + TTL 实现幂等
            \Illuminate\Support\Facades\Redis::setEx($dedupKey, self::BATCH_DEDUP_TTL, '1');
        } catch (\Throwable $e) {
            // Redis 不可用时跳过幂等检查，记录警告
            \Illuminate\Support\Facades\Log::warning('QueryLog dedup check failed, proceeding anyway', [
                'batch_id' => $validated['batch_id'],
                'error' => $e->getMessage(),
            ]);
        }

        $service = new QueryLogIngestService();
        $result = $service->accept($validated);

        $now = now();
        $dnsLogs = [];
        $usageEvents = [];
        // 记录每个 item 的上下文，供 Phase 2 设备更新使用
        $deviceOps = [];

        $profiles = Profile::query()
            ->whereIn(
                'profile_id',
                collect($validated['items'])
                    ->pluck('profile_id')
                    ->filter()
                    ->unique()
                    ->values()
            )
            ->get(['id', 'profile_id', 'user_id'])
            ->keyBy('profile_id');

        // Phase 1：构建日志数据，不做 MySQL 写入
        foreach ($validated['items'] as $i => $item) {
            $profileUid = $item['profile_id'] ?? null;
            $profile = $profileUid !== null ? $profiles->get($profileUid) : null;

            $profilePk = $profile?->id;
            $userPk = $profile?->user_id;

            $queriedAt = isset($item['queried_at'])
                ? now()->setTimestamp((int) $item['queried_at'])
                : $now;

            $domain = strtolower((string) ($item['domain'] ?? ''));

            $clientIp = trim((string) ($item['client_ip'] ?? ''));

            // 防御性剥离 IPv4 端口号，例如 127.0.0.1:55309 -> 127.0.0.1
            if ($clientIp !== '' && substr_count($clientIp, ':') === 1) {
                $parts = explode(':', $clientIp);
                $clientIp = $parts[0];
            }

            $protocol = strtolower(trim((string) ($item['protocol'] ?? 'doh')));
            if ($protocol === '') {
                $protocol = 'doh';
            }

            $deviceUid = trim((string) ($item['device_id'] ?? ''));
            $deviceType = strtolower(trim((string) ($item['device_type'] ?? '')));

            // 通过 device 回查 profile（仅读，不做写入）
            if ($profilePk === null && $deviceUid !== '') {
                $dev = Device::query()
                    ->where('device_uid', $deviceUid)
                    ->whereNotNull('profile_id')
                    ->orderByDesc('last_seen_at')
                    ->first(['id', 'profile_id', 'user_id']);

                if ($dev) {
                    $profilePk = $dev->profile_id;
                    $userPk = $dev->user_id;
                    $resolvedProfileUid = Profile::query()
                        ->whereKey($profilePk)
                        ->value('profile_id');
                    if (is_string($resolvedProfileUid) && $resolvedProfileUid !== '') {
                        $profileUid = $resolvedProfileUid;
                    }
                }
            }

            $newDeviceUid = $deviceUid;
            if ($userPk !== null && $profilePk !== null) {
                $fingerprint = hash('sha256', implode('|', [
                    (string) $profilePk,
                    $protocol,
                    $clientIp,
                    $deviceUid,
                ]));

                // 先查出已有设备 ID，避免写入
                $existing = Device::query()
                    ->where('device_uid', $deviceUid)
                    ->first(['id', 'device_uid']);
                if ($existing) {
                    $devicePk = $existing->id;
                    $newDeviceUid = $existing->device_uid;
                } else {
                    $devicePk = null;
                }

                // 记录设备操作上下文，Phase 3 中执行
                $deviceOps[] = [
                    'index' => $i,
                    'userPk' => (int) $userPk,
                    'profilePk' => (int) $profilePk,
                    'deviceUid' => $deviceUid,
                    'deviceType' => $deviceType,
                    'fingerprint' => $fingerprint,
                    'clientIp' => $clientIp,
                    'protocol' => $protocol,
                    'queriedAt' => $queriedAt,
                    'newDeviceUid' => $newDeviceUid,
                    'devicePk' => $devicePk,
                    'device_sig' => $item['device_sig'] ?? null,
                    'profileUid' => $profileUid,
                ];

                $usageEvents[] = [
                    'timestamp' => $queriedAt->copy()->setTimezone('Asia/Shanghai')->format('Y-m-d H:i:s'),
                    'user_id' => (string) $userPk,
                    'profile_id' => (int) $profilePk,
                    'device_id' => $devicePk,
                    'billing_category' => strtolower((string) ($item['category'] ?? 'query')),
                ];
            }

            $dnsLogs[] = [
                'event_id' => Str::uuid()->toString(),
                'event_time' => $queriedAt->copy()->setTimezone('Asia/Shanghai')->format('Y-m-d H:i:s'),
                'timestamp' => $queriedAt->copy()->setTimezone('Asia/Shanghai')->format('Y-m-d H:i:s'),
                'node_id' => (string) $node->id,
                'user_id' => $userPk !== null ? (string) $userPk : '',
                'profile_id' => $profileUid ?? '',
                'device_id' => $newDeviceUid,
                'device_type' => $deviceType,
                'domain' => $domain,
                'query_type' => strtoupper((string) ($item['query_type'] ?? 'A')),
                'action' => strtoupper((string) $item['action']),
                'reason' => (string) ($item['reason'] ?? ''),
                'category' => (string) ($item['category'] ?? ''),
                // 2026-06-30: 默认哈希存储 client_ip，保留统计能力的同时脱敏原始 IP
                'client_ip' => $clientIp !== '' ? hash('sha256', $clientIp) : '',
                'rcode' => (int) ($item['rcode'] ?? 0),
                'latency_ms' => (int) ($item['latency_ms'] ?? 0),
                'protocol' => $protocol,
            ];
        }

        // Phase 2：先写入 ClickHouse，失败则提前返回（无 MySQL 副作用）
        try {
            if ($dnsLogs !== []) {
                $clickhouse->insertJsonEachRow('dns_logs', $dnsLogs);
            }

            if ($usageEvents !== []) {
                $clickhouse->insertJsonEachRow('usage_events', $usageEvents);
            }
            // DEBUG: 记录写入状态
            Log::info('QueryLog batch processed', [
                'dns_logs_count' => count($dnsLogs),
                'usage_events_count' => count($usageEvents),
                'sample_profile_id' => $validated['items'][0]['profile_id'] ?? 'null',
                'sample_user_pk' => isset($userPk) ? $userPk : 'unset',
                'sample_profile_pk' => isset($profilePk) ? $profilePk : 'unset',
            ]);
        } catch (\Throwable $e) {
            Log::error('ClickHouse insert failed', [
                'message' => $e->getMessage(),
                'batch_id' => $validated['batch_id'] ?? null,
                'dns_logs_count' => count($dnsLogs),
                'usage_events_count' => count($usageEvents),
            ]);

            // 保存失败批次到 MySQL，由 clickhouse:retry-failed-batches 命令重试
            // 返回 200 让 resolver 可以安全清除本地缓冲（数据已持久化到 MySQL）
            try {
                $batchId = $validated['batch_id'] ?? ('missed_' . Str::uuid()->toString());
                DB::table('failed_ch_batches')->insert([
                    'batch_id' => $batchId,
                    'node_id' => $node->id,
                    'dns_logs' => json_encode($dnsLogs),
                    'usage_events' => json_encode($usageEvents),
                    'error_message' => substr($e->getMessage(), 0, 512),
                    'retry_count' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                Log::info('Saved failed CH batch for retry', [
                    'batch_id' => $batchId,
                    'node_id' => $node->id,
                ]);
            } catch (\Throwable $dbError) {
                // 如果连 MySQL 也失败，返回 500 让 resolver 保留缓冲稍后重试
                Log::critical('Failed to save failed CH batch to MySQL', [
                    'ch_error' => $e->getMessage(),
                    'db_error' => $dbError->getMessage(),
                ]);
                return response()->json([
                    'data' => $result,
                    'error' => 'Log storage temporarily unavailable.',
                ], 500);
            }

            return response()->json([
                'data' => $result,
                'warning' => 'Logs queued for retry.',
            ]);
        }

        // PHP 8.1: ClickHouse/MySQL transaction split — commit CH first, then MySQL
        // Phase 3: 按 fingerprint 去重设备操作，避免同一设备批量查询触发 N+1 更新
        // 每个 unique device 仅执行 1 次 SELECT + 1 次 INSERT/UPDATE
        // 2026-07-09: 增加 HMAC 签名验证，防止设备指纹伪造
        $deduped = [];
        $nodeSigningSecret = $node->signing_secret ?? null;
        foreach ($deviceOps as $op) {
            // 验证设备数据签名
            $signature = $op['device_sig'] ?? null;
            if (! $this->verifyDeviceSignature(
                deviceUid: $op['deviceUid'],
                deviceType: $op['deviceType'],
                clientIp: $op['clientIp'],
                profileId: $op['profileUid'] ?? '',
                signature: $signature,
                signingSecret: $nodeSigningSecret
            )) {
                Log::warning('Device signature verification failed, skipping device op', [
                    'device_uid' => $op['deviceUid'],
                    'profile_uid' => $op['profileUid'] ?? null,
                    'node_id' => $node->id,
                ]);
                continue;
            }

            $key = $op['fingerprint'];
            if (! isset($deduped[$key])) {
                $deduped[$key] = $op;
                $deduped[$key]['query_count'] = 1;
            } else {
                // 取最新的时间戳
                if ($op['queriedAt']->gt($deduped[$key]['queriedAt'])) {
                    $deduped[$key]['queriedAt'] = $op['queriedAt'];
                }
                $deduped[$key]['query_count']++;
            }
        }

        foreach ($deduped as $op) {
            $this->resolveDevice(
                userPk: $op['userPk'],
                profilePk: $op['profilePk'],
                deviceUid: $op['deviceUid'],
                deviceType: $op['deviceType'],
                fingerprint: $op['fingerprint'],
                clientIp: $op['clientIp'],
                protocol: $op['protocol'],
                queriedAt: $op['queriedAt'],
                now: $now,
                batchCount: $op['query_count'],
            );
        }

        return response()->json([
            'data' => $result,
        ]);
    }

    private function resolveDevice(
        int $userPk,
        int $profilePk,
        string $deviceUid,
        string $deviceType,
        string $fingerprint,
        string $clientIp,
        string $protocol,
        Carbon $queriedAt,
        Carbon $now,
        int $batchCount = 1
    ): Device {
        $resolvedDeviceUid = $deviceUid !== ''
            ? $deviceUid
            : 'dev_' . substr(hash('sha256', $clientIp), 0, 16);

        $device = $this->findDevice($resolvedDeviceUid, $profilePk, $fingerprint);

        if (! $device) {
            try {
                $device = Device::query()->create([
                    'user_id' => $userPk,
                    'profile_id' => $profilePk,
                    'device_uid' => $resolvedDeviceUid,
                    'fingerprint' => $fingerprint,
                    'name' => $deviceUid !== ''
                        ? $deviceUid
                        : ('Device ' . ($clientIp !== '' ? $clientIp : 'Unknown')),
                    'source' => 'auto',
                    'protocol' => $protocol,
                    'device_type' => $deviceType !== '' ? $deviceType : null,
                    'source_ip' => $clientIp !== '' ? $clientIp : null,
                    'ip_hash' => $clientIp !== ''
                        ? hash('sha256', $clientIp)
                        : null,
                    'first_seen_at' => $queriedAt,
                    'last_seen_at' => $queriedAt,
                    'last_query_at' => $queriedAt,
                    'query_count' => $batchCount,
                    'status' => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                return $device;
            } catch (QueryException $e) {
                if (! $this->isDuplicateKeyException($e)) {
                    throw $e;
                }

                $device = $this->findDevice($resolvedDeviceUid, $profilePk, $fingerprint);

                if (! $device) {
                    throw $e;
                }
            }
        }

        $this->touchDevice(
            device: $device,
            userPk: $userPk,
            profilePk: $profilePk,
            deviceType: $deviceType,
            fingerprint: $fingerprint,
            clientIp: $clientIp,
            protocol: $protocol,
            queriedAt: $queriedAt,
            now: $now,
            batchCount: $batchCount
        );

        return $device;
    }

    private function findDevice(
        string $resolvedDeviceUid,
        int $profilePk,
        string $fingerprint
    ): ?Device {
        $device = Device::query()
            ->where('device_uid', $resolvedDeviceUid)
            ->first();

        if ($device) {
            return $device;
        }

        return Device::query()
            ->where('profile_id', $profilePk)
            ->where('fingerprint', $fingerprint)
            ->first();
    }

    private function touchDevice(
        Device $device,
        int $userPk,
        int $profilePk,
        string $deviceType,
        string $fingerprint,
        string $clientIp,
        string $protocol,
        Carbon $queriedAt,
        Carbon $now,
        int $batchCount = 1
    ): void {
        $update = [
            'user_id' => $userPk,
            'profile_id' => $profilePk,
            'fingerprint' => $fingerprint,
            'protocol' => $protocol,
            'ip_hash' => $clientIp !== ''
                ? hash('sha256', $clientIp)
                : null,
            'last_seen_at' => $queriedAt,
            'last_query_at' => $queriedAt,
            'updated_at' => $now,
        ];
        // 仅当 resolver 上报了具体设备类型时才覆盖，避免后续空值把已有类型清空
        if ($deviceType !== '') {
            $update['device_type'] = $deviceType;
        }
        try {
            $device->forceFill($update)->save();
        } catch (QueryException $e) {
            if (! $this->isDuplicateKeyException($e)) {
                throw $e;
            }

            // 如果更新 fingerprint 时撞上 uniq_devices_profile_fingerprint，
            // 说明另一条设备记录已经拥有该 fingerprint。
            // 这里不再强行覆盖，避免继续抛 1062。
            $device->forceFill([
                'last_seen_at' => $queriedAt,
                'last_query_at' => $queriedAt,
                'updated_at' => $now,
            ])->save();
        }

        Device::query()
            ->whereKey($device->id)
            ->increment('query_count', $batchCount);
    }

    private function isDuplicateKeyException(QueryException $e): bool
    {
        return (int) ($e->errorInfo[1] ?? 0) === 1062;
    }

    /**
     * 验证设备数据 HMAC 签名（防伪造）。
     *
     * 签名内容：device_uid|device_type|client_ip|profile_id
     * 密钥：Node 的 signing_secret（存储在 nodes.signing_secret）
     *
     * 向后兼容：旧版 resolver 不上报签名，跳过验证（trust-on-first-use）。
     * 新版 resolver 上报签名，必须验证通过。
     */
    private function verifyDeviceSignature(
        string $deviceUid,
        string $deviceType,
        string $clientIp,
        string $profileId,
        ?string $signature,
        ?string $signingSecret
    ): bool {
        // 无签名 → 旧版 resolver，暂时信任
        if (empty($signature)) {
            return true;
        }

        // 有签名但无 secret → 无法验证，拒绝（防止伪造）
        if (empty($signingSecret)) {
            Log::warning('Device signature present but node has no signing_secret', [
                'profile_id' => $profileId,
                'device_uid' => $deviceUid,
            ]);
            return false;
        }

        // 计算期望签名
        $data = $deviceUid . '|' . $deviceType . '|' . $clientIp . '|' . $profileId;
        $expected = hash_hmac('sha256', $data, $signingSecret);

        // 恒定时间比较，防止时序攻击
        return hash_equals($expected, $signature);
    }
}
