<?php

declare(strict_types=1);

namespace App\Domain\Billing;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * UI.md #50 — Single Source of Truth for plan / subscription state.
 *
 * All callers that previously did `$user->plan_code` for *permission*
 * decisions MUST go through `SubscriptionService::getActive($userId)`
 * instead.  The legacy `users.plan_code` column is kept only as a
 * write-through cache; reading from it for business decisions is
 * forbidden.
 */
final class SubscriptionService
{
    public const STATUS_ACTIVE    = 'active';
    public const STATUS_TRIALING  = 'trialing';
    public const STATUS_PAST_DUE  = 'past_due';
    public const STATUS_SUSPENDED = 'suspended';   // 欠费超过 grace
    public const STATUS_EXPIRED   = 'expired';     // 周期到期未续
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * Return the active subscription for a user, materialising a free
     * row on first access.  Returns null only if the user does not exist.
     *
     * @return array{
     *   plan_code: string,
     *   status: string,
     *   monthly_query_limit: int|null,
     *   current_period_end: string|null,
     *   grace_until: string|null
     * }|null
     */
    public function getActive(string $userId): ?array
    {
        if ($userId === '') {
            return null;
        }
        $row = DB::table('subscriptions')->where('user_id', $userId)->first();
        if ($row === null) {
            if (! User::whereKey($userId)->exists()) {
                return null;
            }
            DB::table('subscriptions')->insert([
                'user_id' => $userId,
                'plan_code' => 'free',
                'status' => self::STATUS_ACTIVE,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $row = DB::table('subscriptions')->where('user_id', $userId)->first();
        }
        return [
            'plan_code' => (string) ($row->plan_code ?? 'free'),
            'status' => (string) ($row->status ?? self::STATUS_ACTIVE),
            'monthly_query_limit' => isset($row->monthly_query_limit) ? (int) $row->monthly_query_limit : null,
            'current_period_end' => isset($row->current_period_end) ? (string) $row->current_period_end : null,
            'grace_until' => isset($row->grace_until) ? (string) $row->grace_until : null,
        ];
    }

    /**
     * Returns true when the user has a usable subscription.  This is the
     * canonical entry point for permission checks.
     *
     *  - active / trialing：直接可用
     *  - past_due：在 grace 期内仍可用
     *  - suspended / expired / cancelled：不可用
     */
    public function isActive(string $userId): bool
    {
        $sub = $this->getActive($userId);
        if ($sub === null) {
            return false;
        }
        if (in_array($sub['status'], [self::STATUS_ACTIVE, self::STATUS_TRIALING], true)) {
            return true;
        }
        if ($sub['status'] === self::STATUS_PAST_DUE) {
            $grace = $sub['grace_until'];
            if ($grace !== null && strtotime($grace) > time()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Set / change the user's plan.  Keep users.plan_code in sync so
     * legacy read paths still work, but the source of truth is the
     * subscriptions row.
     *
     * 幂等：传入同一个 $orderId 重复调用 setPlan 不会创建新订阅。
     */
    public function setPlan(string $userId, string $planCode, ?int $monthlyLimit = null, ?string $orderId = null): void
    {
        $now = now();

        // 幂等回查：orderId 命中则直接返回（重复 webhook）
        if ($orderId !== null && $orderId !== '') {
            $existingByOrder = DB::table('subscriptions')->where('order_id', $orderId)->first();
            if ($existingByOrder !== null) {
                return;
            }
        }

        $oldCode = DB::table('subscriptions')->where('user_id', $userId)->value('plan_code');
        DB::table('subscriptions')->updateOrInsert(
            ['user_id' => $userId],
            [
                'plan_code' => $planCode,
                'plan_code_old' => $oldCode,
                'order_id' => $orderId,
                'status' => self::STATUS_ACTIVE,
                'monthly_query_limit' => $monthlyLimit,
                'grace_until' => null,
                'updated_at' => $now,
            ]
        );
        // Write-through cache (column retained for compatibility).
        User::whereKey($userId)->update(['plan_code' => $planCode]);
    }

    /**
     * 进入 past_due：默认 7 天宽限期。期间可降级能力但服务可用。
     */
    public function markPastDue(string $userId, int $graceDays = 7): void
    {
        $now = now();
        DB::table('subscriptions')->where('user_id', $userId)->update([
            'status' => self::STATUS_PAST_DUE,
            'grace_until' => $now->copy()->addDays($graceDays),
            'updated_at' => $now,
        ]);
    }

    /**
     * 进入 suspended：超过 grace 期，强制停服。
     */
    public function markSuspended(string $userId): void
    {
        $now = now();
        DB::table('subscriptions')->where('user_id', $userId)->update([
            'status' => self::STATUS_SUSPENDED,
            'suspended_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * 进入 expired：周期到期且未续费。
     */
    public function markExpired(string $userId): void
    {
        $now = now();
        DB::table('subscriptions')->where('user_id', $userId)->update([
            'status' => self::STATUS_EXPIRED,
            'expired_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
