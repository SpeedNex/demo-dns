<?php

declare(strict_types=1);

namespace App\Domain\Billing;

use App\Infrastructure\ClickHouse\ClickHouseClient;
use App\Domain\Jobs\JobRunner;
use Illuminate\Support\Facades\DB;

/**
 * UI.md #67/#70 — Usage 聚合 + 账单生成。
 *
 * 1) Usage Aggregation: ClickHouse usage_events → PostgreSQL usage_records
 * 2) Billing Generation: usage_records (按 period) → billings
 *
 * 增量偏移锁：
 *   用 aggregation_offsets.max_timestamp 代替 processed_at 做游标，
 *   避免 processed_at（PHP 写入完成时间）与 event timestamp（查询时间）不同步导致的漏聚合。
 *   max_timestamp 是本次拉取到的最后一条 usage_events.timestamp。
 *   window_start 固定为前置调用的时间戳，用作同一轮去重键（幂等）。
 */
final class UsageBillingService
{
    public function __construct(
        private readonly ?ClickHouseClient $clickhouseClient = new ClickHouseClient(),
    ) {
    }

    /**
     * 每 5 分钟调用：拉取 ClickHouse usage_events，按
     * (user_id, profile_id, device_id, billing_category, period) 聚合写入 usage_records。
     */
    public function aggregateOnce(?string $since = null): array
    {
        return JobRunner::run('usage_aggregation', function () use ($since) {
            // 1) 读取上次偏移量（max_timestamp 是最后一条 event 的 timestamp，不是 processed_at）
            $offset = DB::table('aggregation_offsets')
                ->where('topic', 'usage_aggregation')
                ->orderByDesc('id')
                ->first();
            $sinceIso = $since ?? $offset?->max_timestamp;

            // 2) 拉取增量 events（注意：WHERE timestamp > 用严格大于避免重复）
            $events = $this->fetchUsageEvents($sinceIso);

            if (empty($events)) {
                // 无新数据时只更新 processed_at，不改变 max_timestamp
                $now = now();
                $existingOffset = DB::table('aggregation_offsets')
                    ->where('topic', 'usage_aggregation')
                    ->where('window_start', $now->format('Y-m-d H:i:00'))
                    ->first();
                $payload = [
                    'processed_at' => $now,
                    'updated_at' => $now,
                ];
                if ($existingOffset === null) {
                    $payload['topic'] = 'usage_aggregation';
                    $payload['window_start'] = $now->format('Y-m-d H:i:00');
                    $payload['max_timestamp'] = $sinceIso;
                    $payload['record_count'] = 0;
                    $payload['status'] = 'done';
                    $payload['created_at'] = $now;
                    DB::table('aggregation_offsets')->insert($payload);
                } else {
                    DB::table('aggregation_offsets')
                        ->where('id', $existingOffset->id)
                        ->update($payload);
                }
                return ['buckets' => 0, 'events' => 0, 'skipped_orphans' => 0];
            }

            // 3) 算出拉取到的最大 timestamp，用作下次偏移量
            $maxTimestamp = '';
            foreach ($events as $e) {
                $ts = (string) ($e['timestamp'] ?? '');
                if ($ts !== '' && $ts > $maxTimestamp) {
                    $maxTimestamp = $ts;
                }
            }

            // 4) 预加载有效 profile_id / device_id 集合，孤儿事件直接 skip
            $profileIds = array_values(array_unique(array_filter(array_map(
                static fn (array $e) => (int) ($e['profile_id'] ?? 0),
                $events
            ))));
            $validProfileIds = $profileIds === []
                ? []
                : DB::table('profiles')->whereIn('id', $profileIds)->pluck('id')->all();
            $validProfileSet = array_flip(array_map('intval', $validProfileIds));

            $deviceIds = array_values(array_unique(array_filter(array_map(
                static fn (array $e) => (int) ($e['device_id'] ?? 0),
                $events
            ))));
            $validDeviceIds = $deviceIds === []
                ? []
                : DB::table('devices')->whereIn('id', $deviceIds)->pluck('id')->all();
            $validDeviceSet = array_flip(array_map('intval', $validDeviceIds));

            // 有效 billing_category 列表（对应数据库 ENUM）
            $validCategories = ['query', 'block', 'safe_search', 'parental'];
            $skippedOrphans = 0;

            // 5) 聚合成 bucket，跳过 profile/device/category 不合法的事件
            $buckets = [];
            foreach ($events as $e) {
                $pid = (int) ($e['profile_id'] ?? 0);
                $did = (int) ($e['device_id'] ?? 0);
                $cat = (string) ($e['billing_category'] ?? '');
                if ($pid <= 0 || ! isset($validProfileSet[$pid]) || ($did > 0 && ! isset($validDeviceSet[$did])) || ! in_array($cat, $validCategories, true)) {
                    $skippedOrphans++;
                    continue;
                }
                $key = sprintf(
                    '%s|%s|%s|%s',
                    $e['user_id'],
                    $e['profile_id'],
                    $e['device_id'],
                    $e['billing_category'] ?? 'normal_query',
                );
                $buckets[$key] = ($buckets[$key] ?? 0) + 1;
            }

            // 6) 写入 / 更新 usage_records
            $now = now();
            foreach ($buckets as $key => $count) {
                [$userId, $profileId, $deviceId, $category] = explode('|', $key);
                $period = $this->ensureOpenPeriod($userId);
                $existingUsage = DB::table('usage_records')
                    ->where('user_id', $userId)
                    ->where('profile_id', $profileId)
                    ->where('device_id', $deviceId !== '' ? $deviceId : null)
                    ->where('billing_category', $category)
                    ->where('billing_period_id', $period->id)
                    ->first();
                $usagePayload = [
                    'query_count' => (int) ($existingUsage->query_count ?? 0) + (int) $count,
                    'amount_minor' => (int) ($existingUsage->amount_minor ?? 0),
                    'last_aggregated_at' => $now,
                    'updated_at' => $now,
                ];
                if ($existingUsage === null) {
                    $usagePayload['user_id'] = $userId;
                    $usagePayload['profile_id'] = $profileId;
                    $usagePayload['device_id'] = $deviceId !== '' ? $deviceId : null;
                    $usagePayload['billing_category'] = $category;
                    $usagePayload['billing_period_id'] = $period->id;
                    $usagePayload['created_at'] = $now;
                    DB::table('usage_records')->insert($usagePayload);
                } else {
                    DB::table('usage_records')
                        ->where('id', $existingUsage->id)
                        ->update($usagePayload);
                }
            }

            // 7) 写回 offset：用 max_timestamp 做游标，window_start 固定为分钟粒度做幂等
            $windowKey = $now->format('Y-m-d H:i:00');
            $existingOffset = DB::table('aggregation_offsets')
                ->where('topic', 'usage_aggregation')
                ->where('window_start', $windowKey)
                ->first();
            $offsetPayload = [
                'topic' => 'usage_aggregation',
                'window_start' => $windowKey,
                'max_timestamp' => $maxTimestamp,
                'processed_at' => $now,
                'record_count' => count($events),
                'status' => 'done',
                'updated_at' => $now,
            ];
            if ($existingOffset === null) {
                $offsetPayload['created_at'] = $now;
                DB::table('aggregation_offsets')->insert($offsetPayload);
            } else {
                // 同一分钟窗口内重入时只更新 processed_at，不覆盖 max_timestamp（以第一次为准）
                DB::table('aggregation_offsets')
                    ->where('id', $existingOffset->id)
                    ->where('max_timestamp', $sinceIso) // 仅当本次是增量时才更新
                    ->update($offsetPayload);
            }

            return ['buckets' => count($buckets), 'events' => count($events), 'skipped_orphans' => $skippedOrphans];
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
                    $billingNo = 'BIL-' . now()->format('YmdHis') . '-' . str_pad((string) $period->id, 6, '0', STR_PAD_LEFT);
                    $billingId = DB::table('billings')->insertGetId([
                        'billing_no' => $billingNo,
                        'user_id' => $period->user_id,
                        'currency' => 'USD',
                        'subtotal_minor' => $totalMinor,
                        'discount_minor' => 0,
                        'tax_minor' => 0,
                        'total_minor' => $totalMinor,
                        'status' => 'pending',
                        'issued_at' => now(),
                        'billing_period_id' => $period->id,
                        'meta' => json_encode(['kind' => 'usage'], JSON_UNESCAPED_UNICODE),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    foreach ($records as $r) {
                        $amount = $this->priceFor($r->billing_category, (int) $r->query_count);
                        DB::table('billing_items')->insert([
                            'billing_id' => $billingId,
                            'item_type' => 'usage',
                            'source_type' => 'usage_record',
                            'source_id' => $r->id,
                            'description' => sprintf('DNS usage (%s) %d queries', $r->billing_category, $r->query_count),
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

    private function fetchUsageEvents(?string $sinceIso): array
    {
        if ($this->clickhouseClient === null) {
            throw new \RuntimeException(
                'ClickHouse client not configured. Refuse to aggregate usage with empty source.'
            );
        }
        // 用 max_timestamp 做游标，严格大于避免重复
        $sql = 'SELECT user_id, profile_id, device_id, billing_category, timestamp FROM usage_events';
        if ($sinceIso !== null && $sinceIso !== '') {
            $sql .= " WHERE timestamp > '" . addslashes($sinceIso) . "'";
        }
        $sql .= ' ORDER BY timestamp LIMIT 10000';
        $rows = $this->clickhouseClient->jsonSelect($sql, []);
        return is_array($rows) ? $rows : [];
    }
}
