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
 */
final class MemberAnalyticsService
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
    public function summaryForUser(string $userId): array
    {
        if (! $this->client->ping()) {
            return [
                'today_queries' => 0,
                'today_blocked' => 0,
                'period_queries' => 0,
                'top_domains' => [],
                'top_blocked' => [],
            ];
        }

        try {
            $rows = $this->client->jsonSelect(
                'SELECT count() AS total, countIf(action = \'BLOCK\') AS blocked '.
                'FROM dns_logs WHERE user_id = {uid:String} AND timestamp >= now() - INTERVAL 24 HOUR',
                ['uid' => $userId]
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
            'top_domains'    => $this->topDomains($userId, 'all'),
            'top_blocked'    => $this->topDomains($userId, 'BLOCK'),
        ];
    }

    /**
     * @return array<int, array{domain: string, count: int}>
     */
    public function topDomains(string $userId, string $actionFilter = 'all'): array
    {
        if (! $this->client->ping()) {
            return [];
        }

        $where = 'user_id = {uid:String} AND timestamp >= now() - INTERVAL 7 DAY';
        if (strtoupper($actionFilter) === 'BLOCK') {
            $where .= ' AND action = {act:String}';
            $params = ['uid' => $userId, 'act' => 'BLOCK'];
        } else {
            $params = ['uid' => $userId];
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
     * Domains that were allowed (action != 'BLOCK').
     * Includes manually allowlisted domains and domains not matched by any block rule.
     *
     * @return array<int, array{domain: string, count: int}>
     */
    public function allowedDomains(string $userId, int $limit = 20): array
    {
        if (! $this->client->ping()) {
            return [];
        }

        try {
            $rows = $this->client->jsonSelect(
                'SELECT domain, count() AS hits FROM dns_logs '.
                'WHERE user_id = {uid:String} AND action <> {blocked:String} '.
                'AND timestamp >= now() - INTERVAL 7 DAY '.
                'GROUP BY domain ORDER BY hits DESC LIMIT {lim:UInt32}',
                ['uid' => $userId, 'blocked' => 'BLOCK', 'lim' => $limit]
            );
        } catch (\RuntimeException) {
            return [];
        }

        return $this->mapDomainRows($rows);
    }

    /**
     * Domains that were blocked (action = 'BLOCK').
     * Includes manually blocklisted domains and domains blocked by security/privacy/parental rules.
     *
     * @return array<int, array{domain: string, count: int}>
     */
    public function blockedDomains(string $userId, int $limit = 20): array
    {
        if (! $this->client->ping()) {
            return [];
        }

        try {
            $rows = $this->client->jsonSelect(
                'SELECT domain, count() AS hits FROM dns_logs '.
                'WHERE user_id = {uid:String} AND action = {blocked:String} '.
                'AND timestamp >= now() - INTERVAL 7 DAY '.
                'GROUP BY domain ORDER BY hits DESC LIMIT {lim:UInt32}',
                ['uid' => $userId, 'blocked' => 'BLOCK', 'lim' => $limit]
            );
        } catch (\RuntimeException) {
            return [];
        }

        return $this->mapDomainRows($rows);
    }

    /**
     * Top block reasons — which security/privacy/parental rules blocked the most queries.
     *
     * @return array<int, array{reason: string, count: int}>
     */
    public function blockReasons(string $userId, int $limit = 10): array
    {
        if (! $this->client->ping()) {
            return [];
        }

        try {
            $rows = $this->client->jsonSelect(
                'SELECT reason, count() AS hits FROM dns_logs '.
                'WHERE user_id = {uid:String} AND action = {blocked:String} '.
                'AND timestamp >= now() - INTERVAL 7 DAY '.
                'AND reason <> \'\' AND reason IS NOT NULL '.
                'GROUP BY reason ORDER BY hits DESC LIMIT {lim:UInt32}',
                ['uid' => $userId, 'blocked' => 'BLOCK', 'lim' => $limit]
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
     * Top devices by query count.
     *
     * @return array<int, array{device_id: string, count: int}>
     */
    public function topDevices(string $userId, int $limit = 10): array
    {
        if (! $this->client->ping()) {
            return [];
        }

        try {
            $rows = $this->client->jsonSelect(
                'SELECT device_id, count() AS hits FROM dns_logs '.
                'WHERE user_id = {uid:String} AND timestamp >= now() - INTERVAL 7 DAY '.
                'AND device_id <> \'\' AND device_id IS NOT NULL '.
                'GROUP BY device_id ORDER BY hits DESC LIMIT {lim:UInt32}',
                ['uid' => $userId, 'lim' => $limit]
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
     * Top client IP addresses by query count.
     *
     * @return array<int, array{client_ip: string, count: int}>
     */
    public function topClientIps(string $userId, int $limit = 10): array
    {
        if (! $this->client->ping()) {
            return [];
        }

        try {
            $rows = $this->client->jsonSelect(
                'SELECT client_ip, count() AS hits FROM dns_logs '.
                'WHERE user_id = {uid:String} AND timestamp >= now() - INTERVAL 7 DAY '.
                'AND client_ip <> \'\' AND client_ip IS NOT NULL '.
                'GROUP BY client_ip ORDER BY hits DESC LIMIT {lim:UInt32}',
                ['uid' => $userId, 'lim' => $limit]
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
     * Top root domains (base domain, e.g. "google.com" from "www.google.com").
     *
     * @return array<int, array{domain: string, count: int}>
     */
    public function topRootDomains(string $userId, int $limit = 20): array
    {
        if (! $this->client->ping()) {
            return [];
        }

        try {
            $rows = $this->client->jsonSelect(
                'SELECT '.
                '  arrayElement(splitByString(\'.\', domain), -2) || \'.\' || '.
                '  arrayElement(splitByString(\'.\', domain), -1) AS root_domain, '.
                '  count() AS hits '.
                'FROM dns_logs '.
                'WHERE user_id = {uid:String} AND timestamp >= now() - INTERVAL 7 DAY '.
                'GROUP BY root_domain ORDER BY hits DESC LIMIT {lim:UInt32}',
                ['uid' => $userId, 'lim' => $limit]
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
     * Percentage of queries using encrypted DNS (DoH / DoT / RFC 8484).
     *
     * @return array{total: int, encrypted: int, ratio_percent: int}
     */
    public function encryptedDnsRatio(string $userId): array
    {
        if (! $this->client->ping()) {
            return ['total' => 0, 'encrypted' => 0, 'ratio_percent' => 0];
        }

        try {
            $rows = $this->client->jsonSelect(
                'SELECT '.
                '  count() AS total, '.
                '  countIf(protocol IN (\'doh\', \'dot\', \'https\', \'tls\')) AS encrypted '.
                'FROM dns_logs '.
                'WHERE user_id = {uid:String} AND timestamp >= now() - INTERVAL 7 DAY',
                ['uid' => $userId]
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
     * Percentage of queries that used DNSSEC validation.
     *
     * @return array{total: int, validated: int, ratio_percent: int}
     */
    public function dnssecRatio(string $userId): array
    {
        if (! $this->client->ping()) {
            return ['total' => 0, 'validated' => 0, 'ratio_percent' => 0];
        }

        try {
            $rows = $this->client->jsonSelect(
                'SELECT '.
                '  count() AS total, '.
                '  countIf(dnssec = \'validated\' OR dnssec = \'secure\') AS validated '.
                'FROM dns_logs '.
                'WHERE user_id = {uid:String} AND timestamp >= now() - INTERVAL 7 DAY',
                ['uid' => $userId]
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
