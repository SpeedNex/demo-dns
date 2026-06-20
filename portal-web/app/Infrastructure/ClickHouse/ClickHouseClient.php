<?php

declare(strict_types=1);

namespace App\Infrastructure\ClickHouse;

use App\Support\SystemConfigValue;

/**
 * Lightweight, dependency-free HTTP client for the ClickHouse
 * `SELECT ... FORMAT JSON` endpoint.
 *
 * Lives in portal-web so the member-facing analytics pages (Dashboard,
 * Analytics, Logs) and the admin / member analytics services can pull
 * top domains / query volume straight from the dns_logs table without
 * a join through the control plane.
 *
 * 2026-06-15: dns-console-web was merged into portal-web, so this is
 * the only ClickHouse client in the project. The query surface is
 * intentionally narrow (ping + jsonSelect) so the implementation can
 * be audited in 60 seconds.
 */
final class ClickHouseClient
{
    /**
     * @var string
     */
    private $host = '';
    /**
     * @var int
     */
    private $port = 8123;
    /**
     * @var string
     */
    private $database = 'ocer_dns';
    /**
     * @var string
     */
    private $username = 'ocer';
    /**
     * @var string
     */
    private $password = '';
    /**
     * @var float
     */
    private $timeout = 1.5;
    /**
     * @var float
     */
    private $connectTimeout = 1.0;
    /**
     * @var bool
     */
    private $enabled = true;

    public function __construct() {
        $cfg = SystemConfigValue::clickhouse();
        $this->enabled = (bool) ($cfg['enabled'] ?? true);
        $this->host = (string) ($cfg['host'] ?? '');
        $this->port = (int) ($cfg['port'] ?? 8123);
        $this->database = (string) ($cfg['database'] ?? 'ocer_dns');
        $this->username = (string) ($cfg['username'] ?? 'ocer');
        $this->password = $this->enabled
            ? $this->resolvePassword((string) ($cfg['password'] ?? ''), (string) ($cfg['password_file'] ?? ''))
            : '';
        $this->timeout = (float) ($cfg['timeout_seconds'] ?? 1.5);
        $this->connectTimeout = (float) ($cfg['connect_timeout_seconds'] ?? 1.0);
    }

    public function ping(): bool
    {
        if (! $this->enabled || $this->host === '') {
            return false;
        }
        try {
            $body = $this->send('SELECT 1 FORMAT TabSeparated', []);
        } catch (\RuntimeException) {
            return false;
        }
        return $body === '1' || $body === "1\n";
    }

    /**
     * @param  array<string,scalar>  $params
     * @return array<int,array<string,mixed>>
     */
    public function jsonSelect(string $query, array $params = []): array
    {
        $body = $this->send($query . ' FORMAT JSON', $params);
        if ($body === '') {
            return [];
        }
        $decoded = json_decode($body, true);
        if (! is_array($decoded) || ! isset($decoded['data']) || ! is_array($decoded['data'])) {
            return [];
        }
        return $decoded['data'];
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     */
    public function insertJsonEachRow(string $table, array $rows): void
    {
        if ($rows === []) {
            return;
        }

        $payload = '';
        foreach ($rows as $row) {
            $encoded = json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($encoded === false) {
                throw new \RuntimeException('clickhouse json encode failed: ' . json_last_error_msg());
            }
            $payload .= $encoded . "\n";
        }

        $this->sendRaw("INSERT INTO {$table} FORMAT JSONEachRow", $payload);
    }

    /**
     * @param  array<string,scalar>  $params
     */
    public function send(string $query, array $params = []): string
    {
        if ($this->host === '') {
            throw new \RuntimeException('clickhouse host is not configured');
        }

        $url = sprintf('http://%s:%d/', $this->host, $this->port);
        $body = $query;
        if (! empty($params)) {
            $body .= ' -- ' . http_build_query($params, '', ';');
        }

        return $this->sendRaw($query, $body);
    }

    public function sendRaw(string $query, string $body): string
    {
        if ($this->host === '') {
            throw new \RuntimeException('clickhouse host is not configured');
        }

        $url = sprintf('http://%s:%d/', $this->host, $this->port);
        $ch = curl_init($url);
        if ($ch === false) {
            throw new \RuntimeException('curl init failed');
        }

        $headers = [
            'Content-Type: text/plain; charset=utf-8',
            'X-ClickHouse-Database: ' . $this->database,
        ];
        if ($this->username !== '' || $this->password !== '') {
            $headers[] = 'Authorization: Basic ' . base64_encode($this->username . ':' . $this->password);
        }

        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT_MS => (int) ($this->connectTimeout * 1000),
            CURLOPT_TIMEOUT_MS        => (int) ($this->timeout * 1000),
            CURLOPT_NOSIGNAL          => 1,
        ]);

        $response = curl_exec($ch);
        $status   = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $errno    = curl_errno($ch);
        curl_close($ch);

        if ($errno !== 0) {
            throw new \RuntimeException('clickhouse curl error: ' . curl_strerror($errno));
        }
        if ($status < 200 || $status >= 300) {
            throw new \RuntimeException('clickhouse returned HTTP ' . $status . ': ' . substr((string) $response, 0, 256));
        }

        return (string) $response;
    }

    /**
     * Resolve the password from env / file. We never want a plain-text
     * secret in the process env, so CLICKHOUSE_PASSWORD_FILE takes
     * precedence when present.
     */
    private function resolvePassword(string $envValue, string $filePath): string
    {
        if ($filePath !== '' && is_file($filePath) && is_readable($filePath)) {
            $value = trim((string) file_get_contents($filePath));
            if ($value !== '') {
                return $value;
            }
        }
        return $envValue;
    }
}
