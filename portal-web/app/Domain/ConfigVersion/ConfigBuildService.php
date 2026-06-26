<?php

namespace App\Domain\ProfileVersion;

final class ConfigBuildService
{
    public function __construct(
        private readonly ChecksumService $checksumService,
    ) {
    }

    /**
     * @param array<string, mixed> $profilePublishPayload
     * @param array<int, array<string, mixed>> $upstreams
     * @return array<string, mixed>
     */
    public function buildBundle(array $profilePublishPayload, array $upstreams): array
    {
        $bundle = [
            'version' => (int) ($profilePublishPayload['profile_version'] ?? 0),
            'generated_at' => gmdate(DATE_ATOM),
            'expires_at' => gmdate(DATE_ATOM, time() + 600),
            'profiles' => $profilePublishPayload['all_profiles']
                ?? [$profilePublishPayload['config_json']],
            'rulesets' => [],
            'upstreams' => $upstreams,
            'runtime' => [
                'dnssec_validate' => false,
                'ecs_enabled' => false,
                'qname_minimization' => false,
            ],
            'signature' => null,
        ];

        $bundle['checksum'] = $this->checksumService->checksum($bundle);

        return $bundle;
    }
}
