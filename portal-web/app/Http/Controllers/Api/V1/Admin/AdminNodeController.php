<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\AdminAuditLog;
use App\Models\Node;
use App\Models\NodeToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

final class AdminNodeController
{
    public function index(Request $request): JsonResponse
    {
        // 2026-06-22: 单一事实源 — nodes.status 列已 drop，"是否在线/降级/离线"全由 Node::runtimeStatus() 现算。
        // 前端读 row.runtime_status / row.install_status / row.last_seen_ago 渲染。
        $query = Node::query();

        // 2026-06-22: 支持按节点ID / 节点别名模糊检索
        $keyword = trim((string) $request->input('q', ''));
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword): void {
                $q->where('node_code', 'ilike', '%' . $keyword . '%')
                    ->orWhere('node_alias', 'ilike', '%' . $keyword . '%')
                    ->orWhere('name', 'ilike', '%' . $keyword . '%');
            });
        }

        $nodes = $query->orderBy('node_code')->get()->map(function (Node $node): array {
            $row = $node->toArray();
            $row['runtime_status'] = $node->runtimeStatus();
            $row['last_seen_ago'] = $node->lastSeenAgo();
            $row['is_online'] = $node->isOnline();
            return $row;
        })->all();

        // meta 用 runtimeStatus() 分类统计
        $totalNodes = count($nodes);
        $onlineNodes = count(array_filter($nodes, fn (array $n): bool => $n['runtime_status'] === 'online'));
        $degradedNodes = count(array_filter($nodes, fn (array $n): bool => $n['runtime_status'] === 'degraded'));
        $offlineNodes = count(array_filter($nodes, fn (array $n): bool => $n['runtime_status'] === 'offline'));
        $notInstalledNodes = count(array_filter($nodes, fn (array $n): bool => $n['runtime_status'] === 'not_installed'));

        return response()->json([
            'data' => $nodes,
            'meta' => [
                'total' => $totalNodes,
                'online' => $onlineNodes,
                'degraded' => $degradedNodes,
                'offline' => $offlineNodes,
                'not_installed' => $notInstalledNodes,
            ],
        ]);
    }

    public function show(string $nodeId): JsonResponse
    {
        $node = Node::query()->findOrFail($nodeId);
        $tokens = NodeToken::where('node_id', $nodeId)->orderByDesc('created_at')->get()->map(fn (NodeToken $token): array => [
            'id' => $token->id,
            'token_prefix' => $token->token_prefix,
            'status' => $token->status,
            'last_used_at' => optional($token->last_used_at)?->toIso8601String(),
            'expires_at' => optional($token->expires_at)?->toIso8601String(),
            'revoked_at' => optional($token->revoked_at)?->toIso8601String(),
            'created_at' => optional($token->created_at)?->toIso8601String(),
        ])->all();

        $row = $node->toArray();
        $row['tokens'] = $tokens;

        return response()->json(['data' => $row]);
    }

    public function store(Request $request): JsonResponse
    {
        $actorId = $request->user()?->admin_id;

        $validated = $request->validate([
            'node_code' => 'nullable|string|max:64|unique:nodes,node_code',
            'name' => 'nullable|string|max:120',
            'node_name' => 'nullable|string|max:120',
            'node_alias' => 'nullable|string|max:100',
            'region' => 'required|string|max:40',
            'city' => 'nullable|string|max:80',
            'domain' => 'nullable|string|max:255',
            'public_ipv4' => 'nullable|string|max:45',
            'public_ipv6' => 'nullable|string|max:64',
            'weight' => 'nullable|integer|min:0|max:10000',
            'capacity_qps' => 'nullable|integer|min:0',
            'supported_protocols' => 'array',
            'supported_protocols.*' => 'string',
        ]);

        // 2026-06-22: 节点代码缺省时自动生成 10 位随机
        if (blank($validated['node_code'] ?? null)) {
            $validated['node_code'] = Str::lower(Str::random(10));
        }

        // 2026-06-23: 节点别名为非必填，留空时按 "node-{region}-{index}" 自动生成
        $aliasInput = trim((string) ($validated['node_alias'] ?? ''));
        if ($aliasInput === '') {
            $regionCode = strtolower($validated['region'] ?? 'unknown');
            $validated['node_alias'] = Node::generateAlias($regionCode);
        } else {
            $validated['node_alias'] = $aliasInput;
        }

        // name 字段保留作兼容；如果未传，从 alias 同步
        $validated['name'] = $validated['name'] ?? $validated['node_alias'];

        // 2026-06-22: 单一事实源 — status 列已 drop，新建节点不写 status，仅设 install_status=pending。
        $node = Node::create(array_merge($validated, [
            'install_status' => 'pending',
            'current_config_version' => 0,
            'desired_config_version' => 1,
            'created_by_admin_id' => $actorId,
        ]));

        AdminAuditLog::record('node.create', 'node', (string) $node->id, $node->toArray(), $actorId !== null ? (string) $actorId : null, null, $request->ip(), $request->userAgent());

        return response()->json(['data' => $node->toArray()], 201);
    }

    public function update(Request $request, string $nodeId): JsonResponse
    {
        $actorId = $request->user()?->admin_id;
        $node = Node::query()->findOrFail($nodeId);

        $validated = $request->validate([
            'node_code' => 'string|max:64|unique:nodes,node_code,' . $nodeId,
            'node_name' => 'nullable|string|max:120',
            'node_alias' => 'nullable|string|max:100',
            'region' => 'nullable|string|max:40',
            'city' => 'nullable|string|max:80',
            'domain' => 'nullable|string|max:255',
            'public_ipv4' => 'nullable|string|max:45',
            'public_ipv6' => 'nullable|string|max:64',
            'weight' => 'nullable|integer|min:0|max:10000',
            'capacity_qps' => 'nullable|integer|min:0',
            'supported_protocols' => 'array',
            'supported_protocols.*' => 'string',
        ]);

        $node->update($validated);

        AdminAuditLog::record('node.update', 'node', $nodeId, $validated, $actorId, null, $request->ip(), $request->userAgent());

        return response()->json(['data' => $node->fresh()->toArray()]);
    }

    public function destroy(Request $request, string $nodeId): JsonResponse
    {
        $actorId = $request->user()?->admin_id;
        $node = Node::query()->findOrFail($nodeId);
        $node->delete();

        AdminAuditLog::record('node.delete', 'node', $nodeId, [], $actorId, null, $request->ip(), $request->userAgent());

        return response()->json(['data' => ['id' => $nodeId, 'deleted' => true]]);
    }

    public function batchDestroy(Request $request): JsonResponse
    {
        $actorId = $request->user()?->admin_id;
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'required',
        ]);

        $count = Node::whereIn('id', $validated['ids'])->delete();

        AdminAuditLog::record('node.batch_delete', 'node', null, ['ids' => $validated['ids'], 'count' => $count], $actorId, null, $request->ip(), $request->userAgent());

        return response()->json(['data' => ['deleted' => $count]]);
    }

    public function issueToken(Request $request, string $nodeId): JsonResponse
    {
        $actorId = $request->user()?->admin_id;
        $node = Node::query()->findOrFail($nodeId);

        $validated = $request->validate([
            'scopes' => 'array',
            'scopes.*' => 'string',
            'expires_in_days' => 'integer|min:1|max:3650',
        ]);

        // V2.3: 预签发 plaintext token，存 SHA256 hash（与 NodeToken::createForNode 兼容）
        $result = NodeToken::createForNode(
            $node,
            isset($validated['expires_in_days']) ? (int) $validated['expires_in_days'] : 365,
            $actorId
        );

        if (! empty($validated['scopes'])) {
            NodeToken::where('token_hash', hash('sha256', $result['token']))
                ->update(['scopes' => json_encode($validated['scopes'])]);
        }

        AdminAuditLog::record('node.token_issue', 'node_token', (string) $node->id, ['node_id' => $nodeId], $actorId !== null ? (string) $actorId : null, null, $request->ip(), $request->userAgent());

        return response()->json([
            'data' => [
                'id' => (string) $node->id,
                'token_prefix' => $result['prefix'],
                'api_key' => $result['token'],
                'hmac_secret' => $result['hmac_secret'] ?? '',
                'node_code' => $node->node_code,
                'dns_domain' => $node->domain ?? '',
                'expires_at' => optional($result['expires_at'])?->toIso8601String(),
            ],
        ], 201)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, private')
            ->header('Pragma', 'no-cache');
    }

    public function revokeToken(Request $request, string $nodeId, string $tokenId): JsonResponse
    {
        $actorId = $request->user()?->admin_id;
        $token = NodeToken::where('node_id', $nodeId)->where('id', $tokenId)->firstOrFail();
        $token->update(['revoked_at' => now()]);

        AdminAuditLog::record('node.token_revoke', 'node_token', $tokenId, ['node_id' => $nodeId], $actorId !== null ? (string) $actorId : null, null, $request->ip(), $request->userAgent());

        return response()->json(['data' => ['id' => $tokenId, 'revoked' => true]]);
    }
}
