<?php

namespace Tests\Feature;

use App\Models\Node;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * 节点 API Key 鉴权中间件 (App\Http\Middleware\AuthenticateNodeApiKey) 的端到端测试。
 *
 * 中间件契约（来自 AuthenticateNodeApiKey.php）:
 *   - Bearer token 必须以 ak_ 开头
 *   - 服务端查 resolver_nodes.api_key（存的是 hash(sha256)）
 *   - 拒绝响应: 401, { "error": { "code": "UNAUTHORIZED", "message": "..." } }
 *
 * 测试策略（2026-06-27 重构）：
 *   由于 node.hmac 中间件已不再被任何路由引用，当前节点业务接口（heartbeat 等）
 *   使用 node.api_key 鉴权。本测试将原 HMAC 测试改为 node.api_key 测试。
 *
 * 测试约定:
 *   - 合法场景：预先创建 Node + 写入正确的 api_key hash，用 ak_xxx 格式 Bearer token 请求
 *   - 失败场景分别测试 token 格式错误、api_key 不匹配、节点状态异常等
 */
final class AgentHmacSignatureTest extends TestCase
{
    use RefreshDatabase;

    /** heartbeat 端点路径 */
    private const HEARTBEAT_PATH = '/api/v1/node/heartbeat';

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    // ------------------------------------------------------------------
    // 辅助方法
    // ------------------------------------------------------------------

    /**
     * 创建一个节点并用 ak_xxx 格式生成正确的 api_key。
     *
     * @return array{node: Node, api_key: string}
     */
    private function provisionNodeWithApiKey(): array
    {
        $plain = 'ak_' . bin2hex(random_bytes(20));
        $node = new Node();
        $node->forceFill([
            'node_name' => 'test-node-' . bin2hex(random_bytes(3)),
            'install_status' => 'installed',
            'region' => 'ap-northeast-1',
            'api_key' => hash('sha256', $plain),
            'api_key_issued_at' => now(),
        ])->save();

        return [
            'node' => $node,
            'api_key' => $plain,
        ];
    }

    /**
     * 构造一组合规的 Bearer token 请求头。
     *
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function withBearer(string $apiKey, array $overrides = []): array
    {
        return array_merge([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ], $overrides);
    }

    // ------------------------------------------------------------------
    // 成功路径
    // ------------------------------------------------------------------

    public function test_accepts_request_with_valid_api_key(): void
    {
        $cred = $this->provisionNodeWithApiKey();
        $headers = $this->withBearer($cred['api_key']);

        $response = $this->postJson(self::HEARTBEAT_PATH, [
            'status' => 'online',
            'uptime_seconds' => 3600,
        ], $headers);

        if ($response->getStatusCode() !== 200) {
            fwrite(STDERR, "DEBUG RESPONSE: " . $response->getContent() . "\n");
        }
        $response->assertOk();
    }

    // ------------------------------------------------------------------
    // 失败路径
    // ------------------------------------------------------------------

    public function test_rejects_request_without_bearer_token(): void
    {
        $this->postJson(self::HEARTBEAT_PATH, [])
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHORIZED');
    }

    public function test_rejects_request_with_invalid_token_format(): void
    {
        // 不以 ak_ 开头的 Bearer token
        $cred = $this->provisionNodeWithApiKey();
        $headers = $this->withBearer('invalid_token_format');

        $this->postJson(self::HEARTBEAT_PATH, [], $headers)
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHORIZED');
    }

    public function test_rejects_request_with_wrong_api_key(): void
    {
        $this->provisionNodeWithApiKey();
        // 用错误的 ak_ 格式 token（正确的 key 已被 provision，但我们故意用别的）
        $headers = $this->withBearer('ak_' . bin2hex(random_bytes(20)));

        $this->postJson(self::HEARTBEAT_PATH, [], $headers)
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHORIZED');
    }

    public function test_rejects_request_with_revoked_node(): void
    {
        $cred = $this->provisionNodeWithApiKey();
        // 模拟节点 install_status = failed
        $cred['node']->update(['install_status' => 'failed']);

        $this->postJson(
            self::HEARTBEAT_PATH,
            [],
            $this->withBearer($cred['api_key']),
        )
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHORIZED');
    }

    public function test_rejects_request_with_non_existent_node(): void
    {
        // 不调用 provision，直接发送一个实际不存在的 ak_xxx
        $headers = $this->withBearer('ak_' . bin2hex(random_bytes(20)));

        $this->postJson(self::HEARTBEAT_PATH, [], $headers)
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHORIZED');
    }

    /**
     * nt 格式旧 token（来自 NodeTokenService）不应通过 node.api_key 鉴权。
     */
    public function test_rejects_old_format_token(): void
    {
        $this->provisionNodeWithApiKey();
        // ntk_ 格式是旧的 node.token 中间件用的，node.api_key 应该拒绝
        $headers = $this->withBearer('ntk_' . bin2hex(random_bytes(20)));

        $this->postJson(self::HEARTBEAT_PATH, [], $headers)
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'UNAUTHORIZED');
    }
}