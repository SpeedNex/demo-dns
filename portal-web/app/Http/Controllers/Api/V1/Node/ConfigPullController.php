<?php

namespace App\Http\Controllers\Api\V1\Node;

use App\Domain\ConfigVersion\ConfigBuildService;
use App\Domain\ConfigVersion\ChecksumService;
use App\Models\ConfigVersion;
use App\Models\Node;
use App\Models\PublishTask;
use App\Models\TaskExecution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ConfigPullController
{
    public function show(Request $request): JsonResponse|Response
    {
        /** @var Node $node */
        $node = $request->attributes->get('node');
        $service = new ConfigBuildService(new ChecksumService());
        $currentVersion = (int) $request->integer('current_version', $node->current_config_version);

        // Resolve the config version targeted for this node via publish tasks,
        // rather than picking the globally latest version. This ensures
        // multi-tenant isolation and supports gradual rollout.
        $configVersion = ConfigVersion::query()
            ->where(function ($query) use ($node): void {
                $query
                    ->whereHas('publishTasks.executions', function ($executionQuery) use ($node): void {
                        $executionQuery->where('node_id', $node->node_id);
                    })
                    ->orWhereHas('publishTasks', function ($taskQuery) use ($node): void {
                        $taskQuery
                            ->whereIn('status', ['queued', 'running', 'succeeded', 'partial'])
                            ->where(function ($targetQuery) use ($node): void {
                                $targetQuery
                                    ->where('target_scope', 'all_nodes')
                                    ->orWhere(function ($specificNodeQuery) use ($node): void {
                                        $specificNodeQuery
                                            ->where('target_scope', 'specific_nodes')
                                            ->whereJsonContains('target_filter->node_ids', $node->node_id);
                                    });
                            });
                    });
            })
            ->orderByDesc('version')
            ->first();

        // Fallback to latest version if no targeted task found.
        if ($configVersion === null) {
            $configVersion = ConfigVersion::query()->orderByDesc('version')->first();
        }

        if ($configVersion === null) {
            return response()->noContent();
        }

        if ($configVersion->version <= $currentVersion) {
            return response()->noContent();
        }

        // Read raw config_json bypassing Eloquent's `array` cast so the
        // nested `quota: {}` object is preserved as stdClass instead of
        // collapsing to []. The resolver expects quota as map[string]any.
        $rawConfigJson = $configVersion->getRawOriginal('config_json');
        $configJson = is_string($rawConfigJson)
            ? json_decode($rawConfigJson, true)
            : (array) $rawConfigJson;
        if (is_array($configJson) && array_key_exists('quota', $configJson)) {
            $configJson['quota'] = (object) $configJson['quota'];
        } else {
            $configJson['quota'] = (object) [];
        }
        // Backfill rule_id to string for legacy bundles (resolver expects string type).
        if (is_array($configJson) && isset($configJson['rules']) && is_array($configJson['rules'])) {
            foreach ($configJson['rules'] as $i => $r) {
                if (is_array($r) && array_key_exists('rule_id', $r)) {
                    $configJson['rules'][$i]['rule_id'] = (string) $r['rule_id'];
                }
            }
        }

        $bundle = $service->buildBundle(
            [
                'profile_version' => $configVersion->version,
                'config_json' => $configJson,
            ],
            [$this->defaultUpstream()],
        );

        $publishTask = PublishTask::where('config_version_id', $configVersion->id)
            ->latest('queued_at')
            ->first();

        if ($publishTask !== null) {
            TaskExecution::updateOrCreate(
                [
                    'publish_task_id' => $publishTask->id,
                    'node_id' => $node->node_id,
                ],
                [
                    'config_version' => $configVersion->version,
                    'status' => 'pulled',
                    'checksum' => $bundle['checksum'],
                    'pulled_at' => now(),
                    'last_seen_at' => now(),
                ],
            );
        }

        return response()->json([
            'data' => $bundle,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultUpstream(): array
    {
        return [
            'address' => config('dns.default_upstream', '1.1.1.1:53'),
            'protocol' => 'udp',
            'timeout' => '1500ms',
        ];
    }
}
