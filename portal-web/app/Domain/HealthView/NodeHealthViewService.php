<?php

namespace App\Domain\HealthView;

final class NodeHealthViewService
{
    /**
     * @param array<int, array<string, mixed>> $nodes
     * @return array<string, mixed>
     */
    public function build(array $nodes): array
    {
        // 2026-06-22: 单一事实源 — nodes.status 列已 drop，"是否在线"由 Node::isOnline() 现算。
        // 这里传入的 $nodes 已经是 Node 模型数组，直接调 isOnline() 即可。
        $items = array_values(array_filter(array_map(
            function (array $node): ?array {
                $model = $node instanceof \App\Models\Node ? $node : \App\Models\Node::query()->find($node['id'] ?? null);
                if (! $model) {
                    return null;
                }
                if (! $model->isOnline()) {
                    return null;
                }
                return $this->mapNode($model);
            },
            $nodes,
        )));

        return [
            'generated_at' => gmdate(DATE_ATOM),
            'ttl_seconds' => 30,
            'nodes' => $items,
        ];
    }

    /**
     * @param \App\Models\Node $node
     * @return array<string, mixed>|null
     */
    private function mapNode(\App\Models\Node $node): ?array
    {
        return [
            'node_id' => (string) ($node->node_id ?? ''),
            'region' => $node->region,
            'country' => $node->country ?? null,
            'city' => $node->city ?? null,
            'status' => $node->isOnline() ? 'online' : 'offline',
            'public_ipv4' => $node->public_ipv4 ?? null,
            'public_ipv6' => $node->public_ipv6 ?? null,
            'supported_protocols' => $node->supported_protocols ?? [],
            'weight' => (int) ($node->weight ?? 100),
            'last_heartbeat_at' => $node->last_heartbeat_at?->toIso8601String(),
        ];
    }
}
