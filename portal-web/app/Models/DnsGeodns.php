<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * GeoDNS 调度解析器模型（2026-06-23 新建）
 *
 * GeoDNS 是调度解析器（Scheduler / Resolver），不是节点。
 * 存放在独立的 dns_geodns 表中。
 * 与 Resolver 节点（dns_resolver_nodes）通过 region 字段精确匹配关联。
 *
 * 【强约束】GeoDNS ≠ 节点（Node）。GeoDNS 负责调度解析，不参与 DNS 节点注册/心跳流程。
 */
class DnsGeodns extends Model
{
    protected $table = 'geodns';

    protected $fillable = [
        'node_code', 'node_alias', 'region', 'country', 'city', 'domain',
        'public_ipv4', 'public_ipv6', 'supported_protocols', 'weight', 'capacity_qps',
        'install_status', 'desired_config_version', 'current_config_version',
        'last_heartbeat_at', 'last_log_flush_at', 'last_installed_at', 'last_listen_addr',
        'api_key', 'api_key_issued_at', 'meta', 'created_by_admin_id',
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

    /**
     * 生成 GeoDNS 别名，格式：geo-{region_code}-{index}
     * regionCode 传入时应为小写地区码，如 "kr", "cn"
     */
    public static function generateAlias(string $regionCode): string
    {
        $prefix = 'geo-' . strtolower($regionCode) . '-';
        // region 字段存储格式为 "geodns-kr"，需匹配
        $regionPattern = 'geodns-' . $regionCode;
        $maxIndex = self::query()
            ->where('region', 'like', $regionPattern . '%')
            ->whereNotNull('node_alias')
            ->get()
            ->map(fn ($n) => (int) preg_replace('/^' . preg_quote($prefix, '/') . '(\d+)$/', '$1', $n->node_alias ?? ''))
            ->filter(fn ($i) => $i > 0)
            ->max() ?? 0;

        return $prefix . ($maxIndex + 1);
    }
}
