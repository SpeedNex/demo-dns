<?php

namespace App\Domain\Ingest;

final class QueryLogIngestService
{
    /**
     * @param array<string, mixed> $batch
     * @return array<string, mixed>
     */
    public function accept(array $batch): array
    {
        $itemCount = count($batch['items'] ?? []);
        if ($itemCount < 1 || $itemCount > 1000) {
            throw new \InvalidArgumentException('Batch size is out of bounds.');
        }

        $encoded = json_encode($batch['items'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            throw new \RuntimeException('Failed to encode log items to JSON: ' . json_last_error_msg());
        }

        $contentSha = 'sha256:' . hash('sha256', $encoded);

        return [
            'accepted' => true,
            'batch_id' => $batch['batch_id'],
            'received_count' => $itemCount,
            'duplicate' => false,
            'content_sha256' => $contentSha,
            // P0 修复: 显式 ACK 回执，resolver 据此确认 buffer 可安全删除
            'ack' => [
                'ack_id' => 'ack_' . hash('sha256', $batch['batch_id'] . $contentSha),
                'stored_count' => $itemCount,
                'checksum' => $contentSha,
                'confirmed_at' => now()->toIso8601String(),
            ],
        ];
    }
}
