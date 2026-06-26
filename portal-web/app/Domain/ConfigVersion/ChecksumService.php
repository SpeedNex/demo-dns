<?php

namespace App\Domain\ProfileVersion;

final class ChecksumService
{
    /**
     * @param array<string, mixed> $payload
     */
    public function checksum(array $payload): string
    {
        return 'sha256:' . hash('sha256', CanonicalJson::encode($payload));
    }
}
