<?php

declare(strict_types=1);

namespace App\Infrastructure\ClickHouse;

/**
 * Read-side service that turns the raw dns_logs ClickHouse rows into the
 * shapes the portal-web frontend expects. All public methods are
 * pure: any transport failure returns an empty payload so the calling
 * controller can decide whether to fall back to the local
 * (fallback) sample. We never throw past the service boundary — that
 * would block a member's dashboard because the analytics store is
 * down.
 *
 * All methods accept an optional `?string $profileId` to scope analytics
 * to a single configuration profile. When null, queries are user-scoped
 * only (legacy behavior).
 */
final class UserAnalyticsService
{
    public function __construct(
        private readonly ClickHouseClient $client = new ClickHouseClient(),
    ) {
    }

    public function ping(): bool
    {
        return $this->client->ping();
    }

    /**
     * @return array{
     *   today_queries: int,
     *   today_blocked: int,
     *   period_queries: int,
     *   top_domains: array<int, array{domain: string, count: int}>,
     *   top_blocked: array<int, array{domain: string, count: int}>
     * }
     */
    public function summaryForUser(string $userId, ?string $profileId = null): array
    {
        if (! $this->client->ping()) {
            return $this->emptySummary();
        }

        $where = $this->buildWhere($userId, $profileId, interval: 'INTERVAL 24 HOUR');
        try {
            $rows = $this->client->jsonSelect(
                'SELECT count() AS total, countIf(action = \'BLOCK\') AS blocked '.
                'FROM dns_logs WHERE '.$where,
                $this->paramsFor($userId, $profileId)
            );
        } catch (\RuntimeException) {
            return $this->emptySummary();
        }

        $row = $rows[0] ?? [];
        $todayQueries = (int) ($row['total'] ?? 0);
        $todayBlocked = (int) ($row['blocked'] ?? 0);

        return [
            'today_queries'  => $todayQueries,
            'today_blocked'  => $todayBlocked,
            'period_queries' => $todayQueries,
            'top_domains'    => $this->topDomains($userId, 'all', $profileId),
            'top_blocked'    => $this->topDomains($userId, 'BLOCK', $profileId),
        ];
    }

    /**
     * @return array<int, array{domain: string, count: int}>
     */
    public function topDomains(string $userId, string $actionFilter = 'all', ?string $profileId = null): array
    {
        if (! $this->client->ping()) {
            return [];
        }

        $where = $this->buildWhere($userId, $profileId, interval: 'INTERVAL 7 DAY');
        $params = $this->paramsFor($userId, $profileId);
        if (strtoupper($actionFilter) === 'BLOCK') {
            $where .= ' AND action = {act:String}';
            $params['act'] = 'BLOCK';
        }

        try {
            $rows = $this->client->jsonSelect(
                'SELECT domain, count() AS hits FROM dns_logs '.
                'WHERE '.$where.' '.
                'GROUP BY domain ORDER BY hits DESC LIMIT 10',
                $params
            );
        } catch (\RuntimeException) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            if (! isset($row['domain'])) {
                continue;
            }
            $out[] = [
                'domain' => (string) $row['domain'],
                'count'  => (int) ($row['hits'] ?? 0),
            ];
        }
        return $out;
    }

    /**
     * @return array<int, array{domain: string, count: int}>
     */
    public function allowedDomains(string $userId, int $limit = 20, ?string $profileId = null): array
    {
        if (! $this->client->ping()) {
            return [];
        }

        $where = $this->buildWhere($userId, $profileId, interval: 'INTERVAL 7 DAY')
            .' AND action <> {blocked:String}';
        $params = array_merge($this->paramsFor($userId, $profileId), [
            'blocked' => 'BLOCK',
            'lim'     => $limit,
        ]);

        try {
            $rows = $this->client->jsonSelect(
                'SELECT domain, count() AS hits FROM dns_logs '.
                'WHERE '.$where.' '.
                'GROUP BY domain ORDER BY hits DESC LIMIT {lim:UInt32}',
                $params
            );
        } catch (\RuntimeException) {
            return [];
        }

        return $this->mapDomainRows($rows);
    }

    /**
     * @return array<int, array{domain: string, count: int}>
     */
    public function blockedDomains(string $userId, int $limit = 20, ?string $profileId = null): array
    {
        if (! $this->client->ping()) {
            return [];
        }

        $where = $this->buildWhere($userId, $profileId, interval: 'INTERVAL 7 DAY')
            .' AND action = {blocked:String}';
        $params = array_merge($this->paramsFor($userId, $profileId), [
            'blocked' => 'BLOCK',
            'lim'     => $limit,
        ]);

        try {
            $rows = $this->client->jsonSelect(
                'SELECT domain, count() AS hits FROM dns_logs '.
                'WHERE '.$where.' '.
                'GROUP BY domain ORDER BY hits DESC LIMIT {lim:UInt32}',
                $params
            );
        } catch (\RuntimeException) {
            return [];
        }

        return $this->mapDomainRows($rows);
    }

    /**
     * @return array<int, array{reason: string, count: int}>
     */
    public function blockReasons(string $userId, int $limit = 10, ?string $profileId = null): array
    {
        if (! $this->client->ping()) {
            return [];
        }

        $where = $this->buildWhere($userId, $profileId, interval: 'INTERVAL 7 DAY')
            .' AND action = {blocked:String} AND reason <> \'\' AND reason IS NOT NULL';
        $params = array_merge($this->paramsFor($userId, $profileId), [
            'blocked' => 'BLOCK',
            'lim'     => $limit,
        ]);

        try {
            $rows = $this->client->jsonSelect(
                'SELECT reason, count() AS hits FROM dns_logs '.
                'WHERE '.$where.' '.
                'GROUP BY reason ORDER BY hits DESC LIMIT {lim:UInt32}',
                $params
            );
        } catch (\RuntimeException) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            if (! isset($row['reason']) || $row['reason'] === '') {
                continue;
            }
            $out[] = [
                'reason' => (string) $row['reason'],
                'count'  => (int) ($row['hits'] ?? 0),
            ];
        }
        return $out;
    }

    /**
     * @return array<int, array{device_id: string, count: int}>
     */
    public function topDevices(string $userId, int $limit = 10, ?string $profileId = null): array
    {
        if (! $this->client->ping()) {
            return [];
        }

        $where = $this->buildWhere($userId, $profileId, interval: 'INTERVAL 7 DAY')
            .' AND device_id <> \'\' AND device_id IS NOT NULL';
        $params = array_merge($this->paramsFor($userId, $profileId), ['lim' => $limit]);

        try {
            $rows = $this->client->jsonSelect(
                'SELECT device_id, count() AS hits FROM dns_logs '.
                'WHERE '.$where.' '.
                'GROUP BY device_id ORDER BY hits DESC LIMIT {lim:UInt32}',
                $params
            );
        } catch (\RuntimeException) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            if (! isset($row['device_id']) || $row['device_id'] === '') {
                continue;
            }
            $out[] = [
                'device_id' => (string) $row['device_id'],
                'count'     => (int) ($row['hits'] ?? 0),
            ];
        }
        return $out;
    }

    /**
     * @return array<int, array{client_ip: string, count: int}>
     */
    public function topClientIps(string $userId, int $limit = 10, ?string $profileId = null): array
    {
        if (! $this->client->ping()) {
            return [];
        }

        $where = $this->buildWhere($userId, $profileId, interval: 'INTERVAL 7 DAY')
            .' AND client_ip_hash <> \'\' AND client_ip_hash IS NOT NULL';
        $params = array_merge($this->paramsFor($userId, $profileId), ['lim' => $limit]);

        try {
            $rows = $this->client->jsonSelect(
                'SELECT client_ip_hash AS client_ip, count() AS hits FROM dns_logs '.
                'WHERE '.$where.' '.
                'GROUP BY client_ip_hash ORDER BY hits DESC LIMIT {lim:UInt32}',
                $params
            );
        } catch (\RuntimeException) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            if (! isset($row['client_ip']) || $row['client_ip'] === '') {
                continue;
            }
            $out[] = [
                'client_ip' => (string) $row['client_ip'],
                'count'     => (int) ($row['hits'] ?? 0),
            ];
        }
        return $out;
    }

    /**
     * @return array<int, array{domain: string, count: int}>
     */
    public function topRootDomains(string $userId, int $limit = 20, ?string $profileId = null): array
    {
        if (! $this->client->ping()) {
            return [];
        }

        $where = $this->buildWhere($userId, $profileId, interval: 'INTERVAL 7 DAY');
        $params = array_merge($this->paramsFor($userId, $profileId), ['lim' => $limit]);

        try {
            $rows = $this->client->jsonSelect(
                'SELECT '.
                '  arrayElement(splitByString(\'.\', domain), -2) || \'.\' || '.
                '  arrayElement(splitByString(\'.\', domain), -1) AS root_domain, '.
                '  count() AS hits '.
                'FROM dns_logs '.
                'WHERE '.$where.' '.
                'GROUP BY root_domain ORDER BY hits DESC LIMIT {lim:UInt32}',
                $params
            );
        } catch (\RuntimeException) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            if (! isset($row['root_domain']) || $row['root_domain'] === '') {
                continue;
            }
            $out[] = [
                'domain' => (string) $row['root_domain'],
                'count'  => (int) ($row['hits'] ?? 0),
            ];
        }
        return $out;
    }

    /**
     * @return array{total: int, encrypted: int, ratio_percent: int}
     */
    public function encryptedDnsRatio(string $userId, ?string $profileId = null): array
    {
        if (! $this->client->ping()) {
            return ['total' => 0, 'encrypted' => 0, 'ratio_percent' => 0];
        }

        $where = $this->buildWhere($userId, $profileId, interval: 'INTERVAL 7 DAY');
        try {
            $rows = $this->client->jsonSelect(
                'SELECT '.
                '  count() AS total, '.
                '  countIf(protocol IN (\'doh\', \'dot\', \'https\', \'tls\')) AS encrypted '.
                'FROM dns_logs '.
                'WHERE '.$where,
                $this->paramsFor($userId, $profileId)
            );
        } catch (\RuntimeException) {
            return ['total' => 0, 'encrypted' => 0, 'ratio_percent' => 0];
        }

        $row = $rows[0] ?? [];
        $total = (int) ($row['total'] ?? 0);
        $encrypted = (int) ($row['encrypted'] ?? 0);
        $ratio = $total > 0 ? (int) round($encrypted / $total * 100) : 0;

        return [
            'total'          => $total,
            'encrypted'      => $encrypted,
            'ratio_percent'  => $ratio,
        ];
    }

    /**
     * @return array{total: int, validated: int, ratio_percent: int}
     */
    public function dnssecRatio(string $userId, ?string $profileId = null): array
    {
        if (! $this->client->ping()) {
            return ['total' => 0, 'validated' => 0, 'ratio_percent' => 0];
        }

        $where = $this->buildWhere($userId, $profileId, interval: 'INTERVAL 7 DAY');
        try {
            $rows = $this->client->jsonSelect(
                'SELECT '.
                '  count() AS total, '.
                '  countIf(dnssec = \'validated\' OR dnssec = \'secure\') AS validated '.
                'FROM dns_logs '.
                'WHERE '.$where,
                $this->paramsFor($userId, $profileId)
            );
        } catch (\RuntimeException) {
            return ['total' => 0, 'validated' => 0, 'ratio_percent' => 0];
        }

        $row = $rows[0] ?? [];
        $total = (int) ($row['total'] ?? 0);
        $validated = (int) ($row['validated'] ?? 0);
        $ratio = $total > 0 ? (int) round($validated / $total * 100) : 0;

        return [
            'total'          => $total,
            'validated'      => $validated,
            'ratio_percent'  => $ratio,
        ];
    }

    /**
     * 最近 7 天每日查询量。返回固定 7 长度数组（按 UTC 倒序，date 字段为 YYYY-MM-DD）。
     *
     * @return array{data: array<int, array{date: string, count: int, blocked: int}>, meta: array{total: int, max: int, range: string}}
     */
    public function dailyTrend7d(string $userId, ?string $profileId = null): array
    {
        $empty = $this->emptyTrend7d();
        if (! $this->client->ping()) {
            return $empty;
        }

        $where = $this->buildWhere($userId, $profileId, interval: 'INTERVAL 7 DAY');
        $params = $this->paramsFor($userId, $profileId);

        try {
            $rows = $this->client->jsonSelect(
                'SELECT toDate(event_time) AS d, count() AS c, countIf(action = \'BLOCK\') AS b '.
                'FROM dns_logs WHERE '.$where.' '.
                'GROUP BY d ORDER BY d ASC',
                $params
            );
        } catch (\RuntimeException) {
            return $empty;
        }

        $byDate = [];
        foreach ($rows as $r) {
            $key = (string) ($r['d'] ?? '');
            if ($key === '') {
                continue;
            }
            $byDate[$key] = [
                'date'    => $key,
                'count'   => (int) ($r['c'] ?? 0),
                'blocked' => (int) ($r['b'] ?? 0),
            ];
        }

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
                'max'   => $max,
                'range' => '7d',
            ],
        ];
    }

    /**
     * @return array{data: array<int, array{date: string, count: int, blocked: int}>, meta: array{total: int, max: int, range: string}}
     */
    private function emptyTrend7d(): array
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $series = [];
        for ($i = 6; $i >= 0; $i--) {
            $d = $now->modify("-{$i} day")->format('Y-m-d');
            $series[] = ['date' => $d, 'count' => 0, 'blocked' => 0];
        }
        return [
            'data' => $series,
            'meta' => ['total' => 0, 'max' => 1, 'range' => '7d'],
        ];
    }

    /**
     * Build a WHERE clause for user (always) and profile (optional) scoping.
     */
    private function buildWhere(string $userId, ?string $profileId, string $interval): string
    {
        $where = 'user_id = {uid:String} AND timestamp >= now() - '.$interval;
        if ($profileId !== null && $profileId !== '') {
            $where .= ' AND profile_id = {pid:String}';
        }
        return $where;
    }

    /**
     * @return array<string, string>
     */
    private function paramsFor(string $userId, ?string $profileId): array
    {
        $params = ['uid' => $userId];
        if ($profileId !== null && $profileId !== '') {
            $params['pid'] = $profileId;
        }
        return $params;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array{domain: string, count: int}>
     */
    private function mapDomainRows(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (! isset($row['domain']) || $row['domain'] === '') {
                continue;
            }
            $out[] = [
                'domain' => (string) $row['domain'],
                'count'  => (int) ($row['hits'] ?? 0),
            ];
        }
        return $out;
    }

    /**
     * @return array{
     *   today_queries: int,
     *   today_blocked: int,
     *   period_queries: int,
     *   top_domains: array<int, array{domain: string, count: int}>,
     *   top_blocked: array<int, array{domain: string, count: int}>
     * }
     */
    private function emptySummary(): array
    {
        return [
            'today_queries'  => 0,
            'today_blocked'  => 0,
            'period_queries' => 0,
            'top_domains'    => [],
            'top_blocked'    => [],
        ];
    }
}
