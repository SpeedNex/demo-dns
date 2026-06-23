<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Node;

/**
 * DNS Resolver 节点注册端点（2026-06-23 改造）
 *
 * 继承 BaseNodeRegisterController，不做 region 前缀限制。
 * URL: POST /api/v1/node/dns-resolver/register
 */
final class NodeRegisterController extends BaseNodeRegisterController
{
}
