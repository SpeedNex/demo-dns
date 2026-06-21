<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | ClickHouse HTTP reader
    |--------------------------------------------------------------------------
    |
    | portal-web (which now absorbs the dns-console-web control plane) uses
    | ClickHouse for two read paths:
    |   1. Member analytics (top visited / blocked, query volume per day)
    |   2. Admin stats + geodns health view (cross-node rollups, online/offline)
    |
    | All inserts stay on the dns-resolver side (Go binary → native TCP);
    | portal-web is read-only. An unreachable ClickHouse is therefore a
    | degraded-but-online state — the read paths will return empty data
    | and the call sites surface a 502 to the user instead of caching
    | stale numbers.
    |
    | The host / port / database / credentials can be overridden per
    | environment. CLICKHOUSE_PASSWORD_FILE points at a Docker secret so
    | we never read a password from a process-visible env var. When
    | CLICKHOUSE_ENABLED is false the client is hard-disabled and the
    | in-process analytics path returns 0 rows (with a log line).
    */
    'host'        => '127.0.0.1',
    'port'        => 8123,
    'database'    => 'ocer_dns',
    'username'    => 'ocer',
    'password'    => '',
    'password_file' => '',
    'timeout_seconds'        => 1.5,
    'connect_timeout_seconds' => 1.0,
    'enabled'     => true,
];
