<?php

declare(strict_types=1);

namespace App\Domain\Billing;

use Illuminate\Support\Facades\DB;

/**
 * 2026-06-30: 关闭已结束的 billing_period。
 *
 * 背景：UsageBillingService::ensureOpenPeriod() 只创建 status='open' 周期，
 * 但全代码库从未将 'open' 改为 'closed'，导致 billing:generate 每天扫 status='closed' 时永远为空。
 * 本服务补齐该环节：每日 00:00 关闭上个月及更早的 open period。
 *
 * 触发顺序：
 *   1. 00:00  billing:close-periods  → open → closed
 *   2. 00:30  billing:generate      → closed → billed
 */
final class BillingPeriodCloseService
{
    /**
     * 关闭所有"已过 period_end"的 open 周期。
     * 幂等：已 closed / billed 跳过（status='open' WHERE 条件保证）。
     */
    public function closeExpiredPeriods(): array
    {
        $now = now();
        $rows = DB::table('billing_periods')
            ->where('status', 'open')
            ->where('period_end', '<', $now)
            ->orderBy('id')
            ->get(['id', 'user_id', 'period_start', 'period_end']);

        $closedIds = [];
        foreach ($rows as $row) {
            $affected = DB::table('billing_periods')
                ->where('id', $row->id)
                ->where('status', 'open')
                ->update([
                    'status' => 'closed',
                    'closed_at' => $now,
                    'updated_at' => $now,
                ]);
            if ($affected > 0) {
                $closedIds[] = (int) $row->id;
            }
        }

        return [
            'scanned' => $rows->count(),
            'closed' => count($closedIds),
            'closed_ids' => $closedIds,
        ];
    }
}
