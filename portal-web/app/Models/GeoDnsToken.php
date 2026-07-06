<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * GeoDNS 节点 Token（2026-07-06 新建）
 *
 * 业务语义：dns_geodns（独立 geodns 实体，非 resolver 节点）的部署凭证。
 *   - 物理节点 156.234.133.8：装 geodns 程序的独立服务器
 *   - 实体表   dns_geodns：geodns 调度器实体（出现在 /admin/geo-dns 页面）
 *   - Token    dns_geodns_tokens：geodns install token，独立于 resolver token
 *
 * Token 格式：gnk_ 前缀 + 32 字符（小写字母数字），共 36 字符
 * 与 NodeToken 的 40 字符无前缀格式区别，避免两套系统 token 互认。
 */
class GeoDnsToken extends Model
{
    protected $table = 'geodns_tokens';

    protected $hidden = ['token_hash'];

    protected $fillable = [
        'geodns_node_id',
        'token_prefix',
        'token_hash',
        'scopes',
        'status',
        'last_used_at',
        'expires_at',
        'revoked_at',
        'revoke_reason',
        'created_by_admin_id',
    ];

    protected $casts = [
        'scopes' => 'array',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    /**
     * Generate a new active token for a geodns entity. Returns plaintext
     * only once — only sha256 hash is persisted. Caller must surface the
     * plaintext to the admin immediately and never store it on disk.
     *
     * @return array{api_key: string, prefix: string, expires_at: \Carbon\Carbon|null}
     */
    public static function createForGeodns(DnsGeodns $geodns, int $ttlDays = 365, ?int $createdByAdminId = null): array
    {
        $plain = 'gnk_' . Str::lower(Str::random(32));
        $prefix = substr($plain, 0, 16) . '****';

        $token = self::create([
            'geodns_node_id' => $geodns->id,
            'token_prefix' => $prefix,
            'token_hash' => hash('sha256', $plain),
            'status' => 'active',
            'expires_at' => $ttlDays > 0 ? now()->addDays($ttlDays) : null,
            'created_by_admin_id' => $createdByAdminId,
        ]);

        return [
            'api_key' => $plain,
            'prefix' => $prefix,
            'expires_at' => $token->expires_at,
        ];
    }

    /**
     * Verify a bearer token by sha256 lookup. Returns the GeoDnsToken
     * record (with status / expires_at already filtered) or null.
     */
    public static function verifyPlainToken(string $plainToken): ?self
    {
        $hash = hash('sha256', $plainToken);
        return self::where('token_hash', $hash)
            ->where('status', 'active')
            ->where(function ($q): void {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->first();
    }

    public function geodnsNode(): BelongsTo
    {
        // 显式声明外键 — 修复 2026-07-06 migration 把 FK 从 dns_resolver_nodes.id 改为 dns_geodns.id
        return $this->belongsTo(DnsGeodns::class, 'geodns_node_id', 'id');
    }
}
