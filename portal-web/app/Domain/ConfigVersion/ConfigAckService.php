<?php

namespace App\Domain\ConfigVersion;

final class ConfigAckService
{
    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function acknowledge(array $payload): array
    {
        return [
            'ok' => true,
            'node_id' => $payload['node_id'] ?? null,
            'config_version' => (int) ($payload['config_version'] ?? 0),
            'status' => $payload['status'] ?? 'applied',
            'recorded_at' => gmdate(DATE_ATOM),
        ];
    }
}
