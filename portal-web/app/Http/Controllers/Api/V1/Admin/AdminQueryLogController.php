<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\AdminAuditLog;
use App\Infrastructure\ClickHouse\ClickHouseClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AdminQueryLogController
{
    // ClickHouseClient already sets X-ClickHouse-Database header, no need to hardcode table name
    public function __construct(private readonly ClickHouseClient $clickhouse = new ClickHouseClient())
    {
        // 2026-06-24: 兼容旧 dns_logs 表（缺少 event_id 列）。
        // 添加 event_id 列以支持行级 ALTER TABLE DELETE。
        // 已有 event_id 列时 CH 会忽略 IF NOT EXISTS,不会报错。
        try {
            $this->clickhouse->send('ALTER TABLE dns_logs ADD COLUMN IF NOT EXISTS event_id String');
        } catch (\Throwable) {
            // 忽略 — 已添加过或表不存在,不影响后续查询
        }
    }

    public function index(Request $request): JsonResponse|Response
    {
        $validated = $request->validate([
            'domain' => 'nullable|string|max:255',
            'action' => 'nullable|string|max:20',
            'profile_id' => 'nullable|string|max:40',
            'user_id' => 'nullable|integer|min:1',
            'username' => 'nullable|string|max:120',
            'start_time' => 'nullable|date',
            'end_time' => 'nullable|date',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'export' => 'nullable|boolean',
        ]);

        $where = [];
        if (! empty($validated['domain'])) {
            $where[] = 'domain LIKE ' . $this->q('%' . strtolower((string) $validated['domain']) . '%');
        }
        if (! empty($validated['action'])) {
            $where[] = $this->actionPredicate((string) $validated['action']);
        }
        if (! empty($validated['profile_id'])) {
            $where[] = 'profile_id = ' . $this->q((string) $validated['profile_id']);
        }

        // 用户筛选优先 user_id，其次 username（把 username 解析为多个 user_id 列表）
        if (! empty($validated['user_id'])) {
            $where[] = 'user_id = ' . $this->q((string) $validated['user_id']);
        } elseif (! empty($validated['username'])) {
            $uids = \App\Models\User::query()
                ->where('username', 'like', '%' . $validated['username'] . '%')
                ->orWhere('email', 'like', '%' . $validated['username'] . '%')
                ->pluck('uid')
                ->all();
            if (! empty($uids)) {
                $list = implode(',', array_map(fn ($v) => $this->q((string) $v), $uids));
                $where[] = "user_id IN ({$list})";
            } else {
                $where[] = '1=0';
            }
        }

        if (! empty($validated['start_time'])) {
            $where[] = 'event_time >= ' . $this->q((string) $validated['start_time']);
        }
        if (! empty($validated['end_time'])) {
            $where[] = 'event_time <= ' . $this->q((string) $validated['end_time']);
        }

        $whereSql = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);

        if (! empty($validated['export'])) {
            $sql = "SELECT event_id, event_time, user_id, profile_id, device_id, domain, query_type, action, reason, client_ip, rcode, latency_ms, protocol, node_id FROM dns_logs {$whereSql} ORDER BY event_time DESC LIMIT 10000";
            $rows = $this->clickhouse->jsonSelect($sql);
            $rows = $this->enrich($rows);

            return response($this->csv($rows), 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="query-logs-' . now()->format('Y-m-d') . '.csv"',
            ]);
        }

        $perPage = (int) ($validated['per_page'] ?? 20);
        $page = max(1, (int) ($validated['page'] ?? 1));
        $offset = ($page - 1) * $perPage;

        $countSql = "SELECT count() AS c FROM dns_logs {$whereSql}";
        $total = (int) ($this->clickhouse->jsonSelect($countSql)[0]['c'] ?? 0);

        $sql = "SELECT event_id, event_time, user_id, profile_id, device_id, domain, query_type, action, reason, client_ip, rcode, latency_ms, protocol, node_id FROM dns_logs {$whereSql} ORDER BY event_time DESC LIMIT {$perPage} OFFSET {$offset}";
        $rows = $this->enrich($this->clickhouse->jsonSelect($sql));

        return response()->json([
            'data' => $rows,
            'meta' => [
                'total' => $total,
                'per_page' => $perPage,
                'page' => $page,
            ],
        ]);
    }

    /**
     * 批量为日志记录附加 user_name / profile_name 字段，避免前端 N+1。
     * 输入数组元素必须是关联数组（来自 ClickHouse 的 jsonSelect）。
     *
     * @param array<int, array<string, mixed>> $entries
     * @return array<int, array<string, mixed>>
     */
    private function enrich(array $entries): array
    {
        $userIds = [];
        $profileIds = [];
        foreach ($entries as $entry) {
            $uid = $entry['user_id'] ?? null;
            if ($uid !== null && $uid !== '') {
                $userIds[(string) $uid] = true;
            }
            $pid = $entry['profile_id'] ?? null;
            if ($pid !== null && $pid !== '') {
                $profileIds[(string) $pid] = true;
            }
        }
        $userMap = \App\Models\User::query()
            ->whereIn('uid', array_keys($userIds))
            ->get(['uid', 'username', 'email'])
            ->keyBy('uid');
        // profile_id 在 CH 中是字符串，user 端是字符串 profile_id；尝试两种匹配
        $profileMap = \App\Models\Profile::query()
            ->whereIn('profile_id', array_keys($profileIds))
            ->get(['id', 'profile_id', 'name'])
            ->keyBy('profile_id');

        $out = [];
        foreach ($entries as $entry) {
            $uid = (string) ($entry['user_id'] ?? '');
            $pid = (string) ($entry['profile_id'] ?? '');
            $user = $uid !== '' ? $userMap->get($uid) : null;
            $profile = $pid !== '' ? $profileMap->get($pid) : null;

            $entry['user_name'] = $user?->username;
            $entry['user_email'] = $user?->email;
            $entry['profile_id'] = $profile?->profile_id ?? ($pid !== '' ? $pid : null);
            $entry['profile_name'] = $profile?->name;
            $entry['timestamp'] = $entry['event_time'] ?? null;
            $entry['queried_at'] = $entry['event_time'] ?? null;
            $out[] = $entry;
        }

        return $out;
    }

    public function batchDestroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'string',
        ]);

        // 过滤空 event_id（旧数据没有 event_id）
        $ids = array_values(array_filter($validated['ids'], fn ($v) => $v !== '' && $v !== null));
        if ($ids === []) {
            return response()->json(['message' => 'No valid event IDs provided.'], 400);
        }

        $actorId = $request->user()?->admin_id;
        $list = implode(',', array_map(fn ($v) => $this->q((string) $v), $ids));
        $sql = "ALTER TABLE dns_logs DELETE WHERE event_id IN ({$list})";

        try {
            $this->clickhouse->send($sql);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'ClickHouse delete failed: ' . $e->getMessage()], 500);
        }

        AdminAuditLog::record('query_logs.batch_delete', 'query_log_entry', null, ['count' => count($ids)], $actorId, null, $request->ip(), $request->userAgent());

        return response()->json(['data' => ['deleted' => count($ids)]]);
    }

    public function clearAll(Request $request): JsonResponse
    {
        $actorId = $request->user()?->admin_id;

        try {
            $this->clickhouse->send('TRUNCATE TABLE IF EXISTS dns_logs');
        } catch (\Throwable $e) {
            return response()->json(['message' => 'ClickHouse truncate failed: ' . $e->getMessage()], 500);
        }

        AdminAuditLog::record('query_logs.clear_all', 'query_log_entry', null, ['note' => 'truncated'], $actorId, null, $request->ip(), $request->userAgent());

        return response()->json(['data' => ['deleted' => true]]);
    }

    /**
     * 后台筛选下拉使用：返回所有 profile 简要信息
     */
    public function profiles(): JsonResponse
    {
        $rows = \App\Models\Profile::query()
            ->orderBy('id')
            ->get(['id', 'profile_id', 'name', 'user_id'])
            ->map(fn ($p): array => [
                'id' => $p->id,
                'profile_id' => $p->profile_id,
                'name' => $p->name,
                'user_id' => $p->user_id,
            ])
            ->all();

        return response()->json(['data' => $rows]);
    }

    private function q(string $value): string
    {
        return "'" . str_replace(['\\', "'"], ['\\\\', "\\'"], $value) . "'";
    }

    private function actionPredicate(string $action): string
    {
        return match (strtolower($action)) {
            'allow', 'allowed' => "lower(action) IN ('allow', 'allowed')",
            'block', 'blocked' => "lower(action) IN ('block', 'blocked')",
            'rewrite', 'rewritten' => "lower(action) IN ('rewrite', 'rewritten')",
            'error' => "lower(action) = 'error'",
            default => 'action = ' . $this->q($action),
        };
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function csv(array $rows): string
    {
        $headers = [
            'event_id', 'event_time', 'user_id', 'user_email', 'profile_id', 'profile_name',
            'device_id', 'domain', 'query_type', 'action', 'reason', 'client_ip',
            'rcode', 'latency_ms', 'protocol', 'node_id',
        ];

        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            return '';
        }

        fputcsv($handle, $headers);
        foreach ($rows as $row) {
            fputcsv($handle, array_map(
                fn (string $key): string => (string) ($row[$key] ?? ''),
                $headers,
            ));
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return (string) $csv;
    }
}
