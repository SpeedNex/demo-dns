<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Node;

/**
 * DNS Resolver 节点注册端点（2026-06-23 改造）
 *
 * 继承 BaseNodeRegisterController。
 * resolver 节点 region 以 'resolver-' 开头。
 * URL: POST /api/v1/node/dns-resolver/register
 */
final class NodeRegisterController extends BaseNodeRegisterController
{
    /**
     * 限定 resolver 节点：region 必须以 'resolver-' 开头。
     */
    protected function expectedRegionPrefix(): ?string
    {
        return 'resolver-';
    }
}
