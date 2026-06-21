<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Node;

use App\Models\Node;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GeoDNS 节点安装注册端点（geodns 专属，与 dns-resolver 接口隔离）
 *
 * geodns 执行 `install` 子命令后，会向本端点发起 POST 报告：
 *   - node_id        节点 ID（由 console 预签发）
 *   - installed_at   安装时间（ISO8601）
 *   - listen_addr    HTTP 监听地址
 *
 * 用途：在 console GeoDNS 节点列表中标记「已注册 / 已安装」，
 * 便于用户区分「仅签发了 token 但尚未安装」与「已完成 install 并启动」两种状态。
 */
final class GeoDnsRegisterController
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'node_id' => 'required|string|max:80',
            'installed_at' => 'nullable|date',
            'listen_addr' => 'nullable|string|max:80',
        ]);

        // 通过当前 token 找到对应节点
        $nodeToken = $request->attributes->get('node_token');
        if (! $nodeToken) {
            return response()->json(['error' => ['code' => 'UNAUTHORIZED', 'message' => 'node token required']], 401);
        }

        // 2026-06-21: geodns 专属注册端点，仅接受 node_type='geodns' 的节点，
        // 防止与 dns-resolver 节点接口冲突。
        $node = Node::query()
            ->where('node_code', $validated['node_id'])
            ->where('node_type', 'geodns')
            ->first();
        if (! $node) {
            return response()->json(['error' => ['code' => 'NOT_FOUND', 'message' => 'geodns node not found']], 404);
        }

        $node->update([
            'last_installed_at' => $validated['installed_at'] ?? now(),
            'last_listen_addr' => $validated['listen_addr'] ?? null,
            'install_status' => 'installed',
        ]);

        return response()->json([
            'data' => [
                'node_id' => $node->node_code,
                'install_status' => $node->install_status,
                'last_installed_at' => $node->last_installed_at?->toIso8601String(),
            ],
        ]);
    }
}
