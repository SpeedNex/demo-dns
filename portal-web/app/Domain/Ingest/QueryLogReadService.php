<?php

namespace App\Domain\Ingest;

use App\Infrastructure\ClickHouse\ClickHouseClient;

final class QueryLogReadService
{
    public function __construct(private readonly ClickHouseClient $clickhouse = new ClickHouseClient())
    {
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{data: array<int, array<string, mixed>>, meta: array<string, int>}
     */
    public function logs(string $userId, array $filters): array
    {
        // 2026-06-22: 用户查询日志只存 ClickHouse，读取走 dns_logs
        $where = ['user_id = ' . $this->q((string) $userId)];
        if (! empty($filters['action'])) {
            $where[] = 'action = ' . $this->q(strtoupper((string) $filters['action']));
        }
        if (! empty($filters['domain'])) {
            $like = strtolower((string) $filters['domain']);
            $where[] = 'domain LIKE ' . $this->q('%' . $like . '%');
        }
        if (! empty($filters['profile_id'])) {
            $where[] = 'profile_id = ' . $this->q((string) $filters['profile_id']);
        }

        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = max(1, min(100, (int) ($filters['per_page'] ?? 20)));
        $offset = ($page - 1) * $perPage;

        $whereSql = 'WHERE ' . implode(' AND ', $where);

        $countSql = "SELECT count() AS c FROM dns_logs {$whereSql}";
        $countRows = $this->clickhouse->jsonSelect($countSql);
        $total = (int) ($countRows[0]['c'] ?? 0);

        $sql = sprintf(
            "SELECT event_time, user_id, profile_id, device_id, domain, action, reason, query_type, rcode, latency_ms, protocol
             FROM dns_logs %s ORDER BY event_time DESC LIMIT %d OFFSET %d",
            $whereSql,
            $perPage,
            $offset,
        );
        $rows = $this->clickhouse->jsonSelect($sql);

        $items = array_map(static function (array $r): array {
            return [
                'id' => $r['event_time'] . '-' . substr(hash('sha256', (string) ($r['domain'] ?? '') . '|' . (string) ($r['event_time'] ?? '')), 0, 8),
                'profile_id' => $r['profile_id'] ?? null,
                'device_id' => $r['device_id'] ?? null,
                'domain' => $r['domain'] ?? null,
                'action' => strtolower((string) ($r['action'] ?? '')),
                'reason' => $r['reason'] ?? null,
                'query_type' => $r['query_type'] ?? null,
                'rcode' => is_numeric($r['rcode'] ?? null) ? (int) $r['rcode'] : null,
                'latency_ms' => is_numeric($r['latency_ms'] ?? null) ? (int) $r['latency_ms'] : null,
                'protocol' => $r['protocol'] ?? null,
                'timestamp' => (string) ($r['event_time'] ?? ''),
            ];
        }, $rows);

        return [
            'data' => $items,
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function analytics(string $userId, ?string $profileId = null): array
    {
        $where = ['user_id = ' . $this->q((string) $userId)];
        if ($profileId !== null && $profileId !== '') {
            $where[] = 'profile_id = ' . $this->q((string) $profileId);
        }
        $whereSql = 'WHERE ' . implode(' AND ', $where);

        // 今日 / 当期 总数
        $totalRows = $this->clickhouse->jsonSelect("SELECT count() AS c FROM dns_logs {$whereSql}");
        $periodTotal = (int) ($totalRows[0]['c'] ?? 0);

        $todayRows = $this->clickhouse->jsonSelect("SELECT count() AS c FROM dns_logs {$whereSql} AND event_time >= toDate(now())");
        $todayQueries = (int) ($todayRows[0]['c'] ?? 0);

        $blockedRows = $this->clickhouse->jsonSelect("SELECT count() AS c FROM dns_logs {$whereSql} AND lower(action) = 'block'");
        $todayBlocked = (int) ($blockedRows[0]['c'] ?? 0);

        // 周期内 top domain
        $topRows = $this->clickhouse->jsonSelect("SELECT domain, count() AS c FROM dns_logs {$whereSql} GROUP BY domain ORDER BY c DESC LIMIT 10");
        $topDomains = array_map(static fn (array $r): array => ['domain' => (string) ($r['domain'] ?? ''), 'count' => (int) ($r['c'] ?? 0)], $topRows);

        $topBlockedRows = $this->clickhouse->jsonSelect("SELECT domain, count() AS c FROM dns_logs {$whereSql} AND lower(action) = 'block' GROUP BY domain ORDER BY c DESC LIMIT 10");
        $topBlocked = array_map(static fn (array $r): array => ['domain' => (string) ($r['domain'] ?? ''), 'count' => (int) ($r['c'] ?? 0)], $topBlockedRows);

        return [
            'today_queries' => $todayQueries,
            'today_blocked' => $todayBlocked,
            'period_queries' => $periodTotal,
            'top_domains' => $topDomains,
            'top_blocked' => $topBlocked,
        ];
    }

    /**
     * 最近 7 天每日查询量（UTC 日期）。返回固定 7 长度数组，无数据的日期 count=0。
     *
     * @return array{data: array<int, array{date: string, count: int, blocked: int}>, meta: array<string, mixed>}
     */
    public function trend7d(string $userId, ?string $profileId = null): array
    {
        $where = ['user_id = ' . $this->q((string) $userId)];
        if ($profileId !== null && $profileId !== '') {
            $where[] = 'profile_id = ' . $this->q((string) $profileId);
        }
        $whereSql = 'WHERE ' . implode(' AND ', $where);

        // 用 toDate(event_time) 聚合最近 7 天（含今天）。ClickHouse 不会自动补 0 长度日期 → PHP 端补齐。
        $sql = "SELECT toDate(event_time) AS d, count() AS c, countIf(lower(action) = 'block') AS b
                FROM dns_logs {$whereSql} AND event_time >= toDate(now()) - 6
                GROUP BY d ORDER BY d ASC";
        $rows = $this->clickhouse->jsonSelect($sql);

        $byDate = [];
        foreach ($rows as $r) {
            $key = (string) ($r['d'] ?? '');
            if ($key === '') {
                continue;
            }
            $byDate[$key] = [
                'date' => $key,
                'count' => (int) ($r['c'] ?? 0),
                'blocked' => (int) ($r['b'] ?? 0),
            ];
        }

        // 7 天补齐（按本地时区 today 为终点；这里用 UTC 简单处理，避免再依赖服务器时区）
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $series = [];
        for ($i = 6; $i >= 0; $i--) {
            $d = $now->modify("-{$i} day")->format('Y-m-d');
            $series[] = $byDate[$d] ?? ['date' => $d, 'count' => 0, 'blocked' => 0];
        }

        $total = array_sum(array_column($series, 'count'));
        $max = max(1, max(array_column($series, 'count')));

        return [
            'data' => $series,
            'meta' => [
                'total' => $total,
                'max' => $max,
                'range' => '7d',
            ],
        ];
    }

    private function q(string $value): string
    {
        // ClickHouse single-quote escape
        return "'" . str_replace(['\\', "'"], ['\\\\', "\\'"], $value) . "'";
    }
}
