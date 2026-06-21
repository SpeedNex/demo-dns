<?php

declare(strict_types=1);

namespace App\Domain\ClickHouse;

use App\Infrastructure\ClickHouse\ClickHouseClient;

/**
 * Domain-level wrapper around ClickHouseClient that produces admin / member
 * facing analytics from the resolver's cold-path log table.
 *
 * The dns-resolver writes `dns_logs` to ClickHouse and portal-web also
 * mirrors node-side uploads into the same table via HTTP JSONEachRow.
 * protocol; this reader uses the HTTP `/FORMAT JSON` endpoint so we don't need
 * to install a binary client. All queries are read-only and use safe literal
 * substitutions (no string interpolation) so they can run as-is in production.
 */
final class ClickHouseStatsService
{
    public function __construct(
        private readonly ClickHouseClient $client = new ClickHouseClient(),
    ) {
    }

    public function isAvailable(): bool
    {
        try {
            return $this->client->ping();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array{
     *     source: 'clickhouse',
     *     total_queries: int,
     *     blocked_queries: int,
     *     allowed_queries: int,
     *     unique_clients: int,
     *     period_start: string,
     *     period_end: string
     * }
     */
    public function global24h(): array
    {
        try {
            $rows = $this->client->jsonSelect(
                'SELECT '
                . ' count() AS total_queries, '
                . ' countIf(action = \'BLOCK\') AS blocked_queries, '
                . ' countIf(action IN (\'ALLOW\', \'REWRITE\')) AS allowed_queries, '
                . ' uniqExact(node_id) AS unique_clients, '
                . ' min(event_time) AS period_start, '
                . ' max(event_time) AS period_end '
                . 'FROM dns_logs '
                . 'WHERE event_time >= now() - INTERVAL 24 HOUR',
            );
        } catch (\Throwable) {
            $rows = [];
        }

        $row = $rows[0] ?? [];
        return [
            'source' => 'clickhouse',
            'total_queries' => (int) ($row['total_queries'] ?? 0),
            'blocked_queries' => (int) ($row['blocked_queries'] ?? 0),
            'allowed_queries' => (int) ($row['allowed_queries'] ?? 0),
            'unique_clients' => (int) ($row['unique_clients'] ?? 0),
            'period_start' => (string) ($row['period_start'] ?? ''),
            'period_end' => (string) ($row['period_end'] ?? ''),
        ];
    }

    /**
     * @return array{
     *     source: 'clickhouse',
     *     top_blocked: array<int,array{domain:string,count:int}>
     * }
     */
    public function topBlocked(int $limit = 10): array
    {
        $limit = max(1, min(100, $limit));
        try {
            $rows = $this->client->jsonSelect(
                "SELECT domain, count() AS count "
                . "FROM dns_logs "
                . "WHERE event_time >= now() - INTERVAL 24 HOUR AND action = 'BLOCK' "
                . "GROUP BY domain ORDER BY count DESC LIMIT {$limit}",
            );
        } catch (\Throwable) {
            $rows = [];
        }

        return [
            'source' => 'clickhouse',
            'top_blocked' => array_map(
                static fn (array $row): array => [
                    'domain' => (string) ($row['domain'] ?? ''),
                    'count' => (int) ($row['count'] ?? 0),
                ],
                $rows,
            ),
        ];
    }
}
