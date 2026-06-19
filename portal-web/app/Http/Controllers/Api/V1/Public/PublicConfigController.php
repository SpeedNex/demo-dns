<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public;

use App\Models\SystemConfig;
use Illuminate\Http\JsonResponse;

/**
 * PublicConfigController
 *
 * 提供无需认证的公开配置查询（如 DNS 域名），供会员端使用。
 */
final class PublicConfigController
{
    public function dnsConfig(): JsonResponse
    {
        $basic = SystemConfig::query()->find('basic');

        $dnsDomain = 'dns.ocerdns.local';

        if ($basic && $basic->value) {
            $decoded = is_string($basic->value) ? json_decode($basic->value, true) : $basic->value;
            if (is_array($decoded) && !empty($decoded['dns_domain'])) {
                $dnsDomain = $decoded['dns_domain'];
            }
        }

        return response()->json([
            'data' => [
                'dns_domain' => $dnsDomain,
            ],
        ]);
    }
}