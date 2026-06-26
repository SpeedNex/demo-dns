<?php

namespace App\Domain\ProfileVersion;

final class CanonicalJson
{
    /**
     * @param array<string, mixed> $payload
     */
    public static function encode(array $payload): string
    {
        $sorted = self::sortRecursive($payload);

        return (string) json_encode($sorted, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private static function sortRecursive(array $payload): array
    {
        foreach ($payload as $key => $value) {
            if (! is_array($value)) {
                continue;
            }

            if (array_keys($value) === range(0, count($value) - 1)) {
                $payload[$key] = array_map(
                    static fn ($item) => is_array($item) ? self::sortRecursive($item) : $item,
                    $value,
                );
                continue;
            }

            $payload[$key] = self::sortRecursive($value);
        }

        ksort($payload);

        return $payload;
    }
}
