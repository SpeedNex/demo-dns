<?php

declare(strict_types=1);

namespace App\Domain\Billing;

use App\Models\JobExecution;
use App\Domain\Jobs\JobRunner;
use Illuminate\Support\Facades\DB;

/**
 * UI.md #67/#70 — Usage 聚合 + 账单生成。
 *
 * 1) Usage Aggregation: ClickHouse usage_events → PostgreSQL usage_records
 * 2) Billing Generation: usage_records (按 period) → invoices(billing_type=usage)
 *
 * 当前实现：聚合逻辑 + 账单生成，使用 JobRunner 包裹 + 失败告警。
 * ClickHouse 客户端通过 ocer-dns/portal-web/app/Services/ClickHouseClient 注入。
 */
final class UsageBillingService
{
    public function __construct(
        private readonly ?object $clickhouseClient = null,
    ) {
    }

    /**
     * 每 5 分钟调用：拉取 ClickHouse usage_events，按
     * (user_id, profile_id, device_id, billing_category, period) 聚合写入 usage_records。
     */
    public function aggregateOnce(?string $since = null): array
    {
        return JobRunner::run('usage_aggregation', function () use ($since) {
            $offset = DB::table('aggregation_offsets')->where('job_type', 'usage_aggregation')->first();
            $sinceIso = $since ?? $offset?->last_processed_at;
            $events = $this->fetchUsageEvents($sinceIso);

            $buckets = [];
            foreach ($events as $e) {
                $key = sprintf(
                    '%s|%s|%s|%s',
                    $e['user_id'],
                    $e['profile_id'],
                    $e['device_id'],
                    $e['billing_category'] ?? 'normal_query',
                );
                $buckets[$key] = ($buckets[$key] ?? 0) + 1;
            }
            $now = now();
            foreach ($buckets as $key => $count) {
                [$userId, $profileId, $deviceId, $category] = explode('|', $key);
                $period = $this->ensureOpenPeriod($userId);
                DB::table('usage_records')->updateOrInsert(
                    [
                        'user_id' => $userId,
                        'profile_id' => $profileId,
                        'device_id' => $deviceId,
                        'billing_category' => $category,
                        'billing_period_id' => $period->id,
                    ],
                    [
                        'query_count' => DB::raw('COALESCE(usage_records.query_count, 0) + ' . (int) $count),
                        'amount_minor' => DB::raw('COALESCE(usage_records.amount_minor, 0)'),
                        'period_start' => $period->period_start,
                        'period_end' => $period->period_end,
                        'updated_at' => $now,
                        'created_at' => DB::raw('COALESCE(usage_records.created_at, NOW())'),
                    ],
                );
            }

            DB::table('aggregation_offsets')->updateOrInsert(
                ['job_type' => 'usage_aggregation'],
                ['last_processed_at' => $now, 'status' => 'idle', 'updated_at' => $now],
            );
            return ['buckets' => count($buckets), 'events' => count($events)];
        });
    }

    /**
     * 把已关闭的 billing_period 内 usage_records 生成 usage 类型账单。
     */
    public function generateBillingsForClosedPeriods(): array
    {
        return JobRunner::run('billing_generation', function () {
            $periods = DB::table('billing_periods')
                ->where('status', 'closed')
                ->whereNull('billing_id')
                ->get();
            $generated = 0;
            foreach ($periods as $period) {
                DB::transaction(function () use ($period, &$generated) {
                    $records = DB::table('usage_records')
                        ->where('billing_period_id', $period->id)
                        ->get();
                    $totalMinor = 0;
                    foreach ($records as $r) {
                        $totalMinor += $this->priceFor($r->billing_category, (int) $r->query_count);
                    }
                    $billingId = DB::table('invoices')->insertGetId([
                        'user_id' => $period->user_id,
                        'type' => 'usage',
                        'billing_type' => 'usage',
                        'billing_period_id' => $period->id,
                        'order_id' => null,
                        'amount_minor' => $totalMinor,
                        'currency' => 'USD',
                        'status' => 'issued',
                        'issued_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    foreach ($records as $r) {
                        $amount = $this->priceFor($r->billing_category, (int) $r->query_count);
                        DB::table('billing_items')->insert([
                            'billing_id' => $billingId,
                            'item_type' => 'usage',
                            'item_name' => sprintf('DNS usage (%s) %d queries', $r->billing_category, $r->query_count),
                            'quantity' => $r->query_count,
                            'unit_price_minor' => $this->unitPrice($r->billing_category),
                            'amount_minor' => $amount,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                    DB::table('billing_periods')->where('id', $period->id)->update([
                        'status' => 'billed',
                        'billing_id' => $billingId,
                        'updated_at' => now(),
                    ]);
                    $generated++;
                });
            }
            return ['generated' => $generated];
        });
    }

    private function ensureOpenPeriod(string $userId): object
    {
        $now = now();
        $monthStart = $now->copy()->startOfMonth();
        $monthEnd = $now->copy()->endOfMonth();
        $row = DB::table('billing_periods')
            ->where('user_id', $userId)
            ->where('period_start', $monthStart)
            ->where('status', 'open')
            ->first();
        if ($row) {
            return $row;
        }
        $id = DB::table('billing_periods')->insertGetId([
            'user_id' => $userId,
            'period_start' => $monthStart,
            'period_end' => $monthEnd,
            'status' => 'open',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        return DB::table('billing_periods')->where('id', $id)->first();
    }

    /**
     * 简化定价：normal_query=0, encrypted_dns=1 分/千次, dnssec=2 分/千次。
     */
    private function priceFor(string $category, int $count): int
    {
        $unit = $this->unitPrice($category);
        return (int) round($count * $unit / 1000);
    }

    private function unitPrice(string $category): int
    {
        return match ($category) {
            'encrypted_dns' => 1,
            'dnssec' => 2,
            default => 0,
        };
    }

    /**
     * 抽象 ClickHouse 拉取：依赖未注入时直接失败（避免账单为 0）。
     */
    private function fetchUsageEvents(?string $sinceIso): array
    {
        if ($this->clickhouseClient === null) {
            // 关键路径：ClickHouse 未配置 → 必须显式失败，不能聚合为 0。
            throw new \RuntimeException(
                'ClickHouse client not configured. Refuse to aggregate usage with empty source. ' .
                'Inject App\\Services\\ClickHouseClient into UsageBillingService.'
            );
        }
        if (! method_exists($this->clickhouseClient, 'select')) {
            throw new \RuntimeException(
                'ClickHouse client does not implement select(). Cannot aggregate usage.'
            );
        }
        $sql = 'SELECT user_id, profile_id, device_id, billing_category, timestamp FROM usage_events';
        if ($sinceIso) {
            $sql .= " WHERE timestamp > '" . addslashes($sinceIso) . "'";
        }
        $sql .= ' ORDER BY timestamp LIMIT 10000';
        $rows = $this->clickhouseClient->select($sql);
        return is_array($rows) ? $rows : [];
    }
}
