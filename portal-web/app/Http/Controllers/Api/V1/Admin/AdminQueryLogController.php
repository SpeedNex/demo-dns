<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\AdminAuditLog;
use App\Infrastructure\ClickHouse\ClickHouseClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AdminQueryLogController
{
    public function __construct(private readonly ClickHouseClient $clickhouse = new ClickHouseClient())
    {
    }

    public function index(Request $request): JsonResponse
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
            $where[] = 'action = ' . $this->q(strtoupper((string) $validated['action']));
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
            $sql = "SELECT event_time, user_id, profile_id, device_id, domain, query_type, action, reason, client_ip, rcode, latency_ms, protocol, node_id FROM dns_logs {$whereSql} ORDER BY event_time DESC LIMIT 10000";
            $rows = $this->clickhouse->jsonSelect($sql);
            $rows = $this->enrich($rows);

            return response()->json(['data' => $rows]);
        }

        $perPage = (int) ($validated['per_page'] ?? 20);
        $page = max(1, (int) ($validated['page'] ?? 1));
        $offset = ($page - 1) * $perPage;

        $countSql = "SELECT count() AS c FROM dns_logs {$whereSql}";
        $total = (int) ($this->clickhouse->jsonSelect($countSql)[0]['c'] ?? 0);

        $sql = "SELECT event_time, user_id, profile_id, device_id, domain, query_type, action, reason, client_ip, rcode, latency_ms, protocol, node_id FROM dns_logs {$whereSql} ORDER BY event_time DESC LIMIT {$perPage} OFFSET {$offset}";
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
        // profile_id 在 CH 中是字符串，user 端是字符串 profile_uid；尝试两种匹配
        $profileMap = \App\Models\Profile::query()
            ->whereIn('profile_uid', array_keys($profileIds))
            ->get(['id', 'profile_uid', 'name'])
            ->keyBy('profile_uid');

        $out = [];
        foreach ($entries as $entry) {
            $uid = (string) ($entry['user_id'] ?? '');
            $pid = (string) ($entry['profile_id'] ?? '');
            $user = $uid !== '' ? $userMap->get($uid) : null;
            $profile = $pid !== '' ? $profileMap->get($pid) : null;

            $entry['user_name'] = $user?->username;
            $entry['user_email'] = $user?->email;
            $entry['profile_uid'] = $profile?->profile_uid;
            $entry['profile_name'] = $profile?->name;
            $entry['timestamp'] = $entry['event_time'] ?? null;
            $entry['query_name'] = $entry['domain'] ?? null;
            $entry['queried_at'] = $entry['event_time'] ?? null;
            $out[] = $entry;
        }

        return $out;
    }

    public function batchDestroy(Request $request): JsonResponse
    {
        // 2026-06-22: 查询日志唯一源是 ClickHouse，MySQL 不再保留明细 → 不再提供删除
        return response()->json(['data' => ['deleted' => 0, 'note' => 'logs are stored in ClickHouse, use TTL or external tooling']]);
    }

    public function clearAll(Request $request): JsonResponse
    {
        $actorId = $request->user()?->admin_id;
        AdminAuditLog::record('query_logs.clear_all', 'query_log_entry', null, ['note' => 'clickhouse-only, use external tooling'], $actorId, null, $request->ip(), $request->userAgent());

        return response()->json(['data' => ['deleted' => 0, 'note' => 'logs are stored in ClickHouse, use TTL or external tooling']]);
    }

    /**
     * 后台筛选下拉使用：返回所有 profile 简要信息
     */
    public function profiles(): JsonResponse
    {
        $rows = \App\Models\Profile::query()
            ->orderBy('id')
            ->get(['id', 'profile_uid', 'name', 'user_id'])
            ->map(fn ($p): array => [
                'id' => $p->id,
                'profile_uid' => $p->profile_uid,
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
}
