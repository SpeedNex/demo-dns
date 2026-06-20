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
    public function index(): JsonResponse
    {
        $nodes = Node::query()->orderBy('node_code')->get()->toArray();
        $totalNodes = count($nodes);
        // 节点只关心 online / offline：pending/disabled 视作离线
        $onlineNodes = count(array_filter($nodes, fn (array $node): bool => $node['status'] === 'online'));

        return response()->json([
            'data' => $nodes,
            'meta' => [
                'total' => $totalNodes,
                'online' => $onlineNodes,
                'offline' => max($totalNodes - $onlineNodes, 0),
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
        $request->merge([
            'node_code' => $request->input('node_code', 'nd_' . Str::lower(Str::random(10))),
            'name' => $request->input('name', $request->input('node_name')),
        ]);
        $validated = $request->validate([
            'node_code' => 'required|string|max:64|unique:nodes,node_code',
            'name' => 'required|string|max:120',
            'region' => 'nullable|string|max:40',
            'country' => 'nullable|string|size:2',
            'city' => 'nullable|string|max:80',
            'public_ipv4' => 'nullable|string|max:45',
            'public_ipv6' => 'nullable|string|max:64',
            'supported_protocols' => 'array',
            'supported_protocols.*' => 'string',
        ]);

        $node = Node::create(array_merge($validated, [
            'status' => 'pending',
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
        $request->merge([
            'name' => $request->input('name', $request->input('node_name')),
        ]);

        $validated = $request->validate([
            'node_code' => 'string|max:64|unique:nodes,node_code,' . $nodeId,
            'name' => 'string|max:120',
            'region' => 'nullable|string|max:40',
            'country' => 'nullable|string|size:2',
            'city' => 'nullable|string|max:80',
            'public_ipv4' => 'nullable|string|max:45',
            'public_ipv6' => 'nullable|string|max:64',
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

    public function enable(Request $request, string $nodeId): JsonResponse
    {
        $actorId = $request->user()?->admin_id;
        $node = Node::query()->findOrFail($nodeId);
        // 只允许从 disabled → pending，等心跳确认真实在线
        $node->update(['status' => 'pending']);

        AdminAuditLog::record('node.enable', 'node', $nodeId, [], $actorId, null, $request->ip(), $request->userAgent());

        return response()->json(['data' => $node->fresh()->toArray()]);
    }

    public function disable(Request $request, string $nodeId): JsonResponse
    {
        $actorId = $request->user()?->admin_id;
        $node = Node::query()->findOrFail($nodeId);
        $node->update(['status' => 'disabled']);

        AdminAuditLog::record('node.disable', 'node', $nodeId, [], $actorId, null, $request->ip(), $request->userAgent());

        return response()->json(['data' => $node->fresh()->toArray()]);
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
                'node_id' => $node->node_code,
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
