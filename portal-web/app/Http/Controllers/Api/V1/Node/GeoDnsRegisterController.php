<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Node;

use App\Models\DnsGeodns;
use App\Models\Node;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * GeoDNS 安装注册端点（2026-07-06 重构）
 *
 * 业务语义：geodns 进程装在某台 resolver 节点上。
 *   - 鉴权：geodns.token（dns_geodns_tokens，独立于 resolver）
 *   - 状态归属：dns_resolver_nodes（出现在 /admin/nodes 页面）
 *
 * 流程：
 *   1. token 解析 → Node（装 geodns 的 resolver 节点）
 *   2. 更新 Node.install_status='installed' + last_heartbeat_at
 *   3. 同步关联的 dns_geodns 调度器配置（按 region 匹配）
 *
 * URL: POST /api/v1/node/geodns/register
 */
final class GeoDnsRegisterController
{
    public function register(Request $request): JsonResponse
    {
        $start = microtime(true);

        $validated = $request->validate([
            'installed_at' => 'nullable|date',
            'listen_addr' => 'nullable|string|max:80',
        ]);

        // 从 geodns token 中间件获取已解析的 resolver 节点
        /** @var \App\Models\Node|null $node */
        $node = $request->attributes->get('geodns_node');
        if ($node === null) {
            return response()->json(['error' => ['code' => 'UNAUTHORIZED', 'message' => 'geodns token required']], 401);
        }

        $now = now();
        $installedAt = isset($validated['installed_at']) ? \Carbon\Carbon::parse($validated['installed_at']) : $now;

        // 1) 更新 resolver 节点 install 状态（admin 页面会看到）
        $node->forceFill([
            'install_status' => 'installed',
            'last_heartbeat_at' => $now,
            'last_listen_addr' => $validated['listen_addr'] ?? $node->last_listen_addr,
            'last_installed_at' => $installedAt,
        ])->save();

        // 2) 同步关联 dns_geodns 调度器（按 region）— 首次 register 时自动建配置
        $geodns = DnsGeodns::query()->where('region', $node->region)->first();
        if ($geodns === null && $node->region) {
            $regionCode = strtolower(str_replace('geodns-', '', (string) $node->region));
            $geodns = DnsGeodns::create([
                'node_code' => $node->node_code,
                'node_alias' => DnsGeodns::generateAlias($regionCode),
                'region' => $node->region,
                'public_ipv4' => $node->public_ipv4,
                'install_status' => 'installed',
                'current_config_version' => 1,
                'desired_config_version' => 1,
                'last_heartbeat_at' => $now,
            ]);
        } elseif ($geodns !== null) {
            $geodns->forceFill(['last_heartbeat_at' => $now])->save();
        }

        $response = [
            'data' => [
                'node_id' => $node->node_code,
                'node_db_id' => $node->id,
                'region' => $node->region,
                'install_status' => $node->install_status,
                'last_installed_at' => $node->last_installed_at?->toIso8601String(),
                'geodns_db_id' => $geodns?->id,
            ],
        ];

        // 第一次 register 时把明文 token 回传给客户端，便于写 configs/api_key 文件
        $apiKeyPlain = (string) $request->attributes->get('geodns_token_plain');
        if ($apiKeyPlain !== '' && empty($node->getOriginal('api_key'))) {
            $node->forceFill([
                'api_key' => hash('sha256', $apiKeyPlain),
                'api_key_issued_at' => $now,
            ])->save();
            $response['data']['api_key'] = $apiKeyPlain;
            $response['data']['api_key_path'] = 'configs/api_key';
        }

        $latencyMs = (int) ((microtime(true) - $start) * 1000);
        Log::channel('node_api')->info('geodns_register', [
            'method' => $request->method(),
            'path' => $request->path(),
            'node_code' => $node->node_code,
            'region' => $node->region,
            'status' => 200,
            'latency_ms' => $latencyMs,
            'api_key_issued' => isset($response['data']['api_key']),
            'remote_addr' => $request->ip(),
        ]);

        return response()->json($response);
    }
}
