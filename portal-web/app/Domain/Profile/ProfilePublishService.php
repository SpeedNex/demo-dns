<?php

declare(strict_types=1);

namespace App\Domain\Profile;

use App\Domain\Publish\PublishService;

final class ProfilePublishService
{
    public function __construct(
        private readonly ProfileConfigBuilder $configBuilder,
        private readonly PublishService $publishService,
    ) {
    }

    /**
     * Build the config bundle, persist the canonical JSON, and create the
     * (config_version, publish_task) tuple in-process.
     *
     * Pre-merge this method dispatched to dns-console-web over HTTP and
     * returned a hard-coded "queued" stub when the call failed. After
     * the 2026-06-15 merge both packages share the same Laravel process
     * and PostgreSQL database, so we call the in-process PublishService
     * directly. A failure to write the publish tuple propagates as a
     * 5xx to the member-facing endpoint.
     *
     * @param array<string, mixed> $profile
     * @param array<int, array<string, mixed>> $rules
     * @param array<string, mixed> $featureSettings
     * @param array<string, mixed> $quota
     * @return array<string, mixed>
     */
    public function publish(array $profile, array $rules, array $featureSettings, array $quota = []): array
    {
        $config = $this->configBuilder->build($profile, $rules, $featureSettings, $quota);
        $configJson = $this->canonicalJson($config);
        $checksum = hash('sha256', $configJson);

        $result = $this->publishService->recordPublish(
            (string) ($profile['profile_uid'] ?? $profile['id']),
            (int) $config['version'],
            $checksum,
            $config,
        );

        return [
            'profile_id' => (string) ($profile['profile_uid'] ?? $profile['id']),
            'profile_version' => (int) $config['version'],
            'publish_id' => $result['publish_id'],
            'publish_status' => $result['status'],
            'config_version' => $result['config_version'],
            'checksum' => $result['checksum'],
            'config_json' => $config,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function canonicalJson(array $payload): string
    {
        $sorted = $this->ksortRecursive($payload);

        $encoded = json_encode($sorted, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            throw new \RuntimeException('Failed to encode config to canonical JSON: ' . (json_last_error_msg()));
        }

        return $encoded;
    }

    /**
     * @param array<string, mixed> $value
     * @return array<string, mixed>
     */
    private function ksortRecursive(array $value): array
    {
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->isAssoc($item) ? $this->ksortRecursive($item) : array_map(
                    fn ($child) => is_array($child) && $this->isAssoc($child) ? $this->ksortRecursive($child) : $child,
                    $item,
                );
            }
        }

        ksort($value);

        return $value;
    }

    /**
     * @param array<mixed> $value
     */
    private function isAssoc(array $value): bool
    {
        return array_keys($value) !== range(0, count($value) - 1);
    }
}
