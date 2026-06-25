<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class Node extends Model
{
    protected static function booted(): void
    {
        static::creating(function (self $node): void {
            if (blank($node->node_code)) {
                $node->node_code = strtolower(\Illuminate\Support\Str::random(10));
            }
        });
    }

    protected $table = 'resolver_nodes';
    protected $fillable = [
        'node_code', 'domain', 'region', 'city', 'weight', 'capacity_qps',
        'public_ipv4', 'public_ipv6', 'supported_protocols',
        'desired_config_version', 'current_config_version',
        'last_heartbeat_at', 'last_log_flush_at', 'meta', 'created_by_admin_id',
        'node_name', 'node_alias',
        // 2026-06-22: install 状态记录
        'install_status', 'last_installed_at', 'last_listen_addr',
        // 2026-06-22 fix: register 端点签发 api_key 必须可写。
        'api_key', 'api_key_issued_at',
    ];
    protected $casts = [
        'supported_protocols' => 'array',
        'meta' => 'array',
        'last_heartbeat_at' => 'datetime',
        'last_log_flush_at' => 'datetime',
        'last_installed_at' => 'datetime',
        'desired_config_version' => 'integer',
        'current_config_version' => 'integer',
    ];

    public function getNodeNameAttribute(): ?string
    {
        return $this->attributes['node_alias'] ?? null;
    }

    public function setNodeNameAttribute(?string $value): void
    {
        $this->attributes['node_alias'] = $value;
    }

    public function tokens(): HasMany
    {
        return $this->hasMany(NodeToken::class, 'node_id');
    }

    public function heartbeats(): HasMany
    {
        return $this->hasMany(NodeHeartbeat::class, 'node_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }

    // =========================================================================
    // 2026-06-22: 「单一事实源 last_heartbeat_at」运行时状态派生
    // -------------------------------------------------------------------------
    // 旧设计：nodes.status 列由 HeartbeatController 写 online，由 cron 写 offline，
    //         任何读它的脚本都有 race / 漂移风险。
    // 新设计：status 列已 drop，所有"在线/离线/降级"都从这里实时计算：
    //   - getHeartbeatStaleSeconds()  阈值（统一 90 秒）
    //   - isOnline() / isDegraded()   布尔谓词（用于 Query Builder、告警）
    //   - runtimeStatus()             4 档字符串（用于 JSON 响应、UI）
    // 任何时候任何人读，结论都一致；无需 cron、无需 MarkOfflineCommand。
    // =========================================================================

    /** 心跳超时阈值（秒），统一使用 90 秒。 */
    public function getHeartbeatStaleSeconds(): int
    {
        return (int) env('NODE_HEARTBEAT_STALE_SECONDS', 90);
    }

    /** 心跳新鲜 = 真正在岗。优先查 Redis，兜底 MySQL。 */
    public function isOnline(): bool
    {
        // 优先从 Redis 最新心跳判断（TTL 90 秒）
        try {
            $exists = Redis::exists("node:{$this->id}:heartbeat");
            if ($exists) {
                return true;
            }
            // Redis key 不存在 → 心跳超时，但可能 Redis 数据丢失，需 fallback 到 MySQL
        } catch (\Throwable $e) {
            // Redis 不可用时静默 fallback 到 MySQL
            Log::debug('Redis unavailable for isOnline check, fallback to MySQL', [
                'node_id' => $this->id,
                'error' => $e->getMessage(),
            ]);
        }

        // MySQL fallback：last_heartbeat_at 在 90 秒内 → 在线
        if (! $this->last_heartbeat_at) {
            return false;
        }
        return $this->last_heartbeat_at->gt(now()->subSeconds($this->getHeartbeatStaleSeconds()));
    }

    /** 漏 1 拍（>1 倍阈值但 ≤2 倍）：黄。 */
    public function isDegraded(): bool
    {
        if (! $this->last_heartbeat_at) {
            return false;
        }
        $threshold = $this->getHeartbeatStaleSeconds();
        $age = $this->last_heartbeat_at->diffInSeconds(now());
        return $age > $threshold && $age <= $threshold * 2;
    }

    /** 4 档语义：未装 / 离线 / 降级 / 在线。 */
    public function runtimeStatus(): string
    {
        if ($this->install_status !== 'installed') {
            return 'not_installed';
        }
        if ($this->isOnline()) {
            return 'online';
        }
        if ($this->isDegraded()) {
            return 'degraded';
        }
        return 'offline';
    }

    /** 给人看的"最近一次心跳距今"。 */
    public function lastSeenAgo(): ?string
    {
        return $this->last_heartbeat_at?->diffForHumans(now(), ['short' => true]);
    }

    // ============== Query Scopes ==============
    // 给 AdminStatsController / PublishService / PolicyPublisher 等
    // 想要"取所有在线节点"的地方用，where 子句由 SQL 算（不是 PHP 遍历后过滤）。

    public function scopeOnline(Builder $query): Builder
    {
        $threshold = $this->getHeartbeatStaleSeconds();
        return $query->where('install_status', 'installed')
            ->whereNotNull('last_heartbeat_at')
            ->where('last_heartbeat_at', '>', now()->subSeconds($threshold));
    }

    public function scopeDegraded(Builder $query): Builder
    {
        $threshold = $this->getHeartbeatStaleSeconds();
        return $query->where('install_status', 'installed')
            ->whereNotNull('last_heartbeat_at')
            ->where('last_heartbeat_at', '<=', now()->subSeconds($threshold))
            ->where('last_heartbeat_at', '>', now()->subSeconds($threshold * 2));
    }

    public function scopeOffline(Builder $query): Builder
    {
        $threshold = $this->getHeartbeatStaleSeconds();
        return $query->where('install_status', 'installed')
            ->where(function (Builder $q) use ($threshold): void {
                $q->whereNull('last_heartbeat_at')
                    ->orWhere('last_heartbeat_at', '<=', now()->subSeconds($threshold * 2));
            });
    }

    /**
     * 生成 Resolver 节点别名，格式：node-{region_code}-{index}
     * regionCode 传入时应为小写地区码，如 "kr", "cn"
     */
    public static function generateAlias(string $regionCode): string
    {
        $prefix = 'node-' . strtolower($regionCode) . '-';
        $maxIndex = self::query()
            ->where('region', $regionCode)
            ->whereNotNull('node_alias')
            ->get()
            ->map(fn ($n) => (int) preg_replace('/^' . preg_quote($prefix, '/') . '(\d+)$/', '$1', $n->node_alias ?? ''))
            ->filter(fn ($i) => $i > 0)
            ->max() ?? 0;

        return $prefix . ($maxIndex + 1);
    }
}
