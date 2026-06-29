<?php

declare(strict_types=1);

namespace App\Domain\Billing;

use App\Application\Member\ProfilePublishApplicationService;
use App\Models\Profile;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * SaaS 订阅服务 — 管理订阅生命周期。
 *
 * 流程: create(pending) → checkout → activate(active) → cancel/expire
 */
final class SubscriptionService
{
    public const STATUS_PENDING   = 'pending';
    public const STATUS_ACTIVE    = 'active';
    public const STATUS_TRIALING  = 'trialing';
    public const STATUS_PAST_DUE  = 'past_due';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_EXPIRED   = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * 创建订阅（pending 状态）。
     */
    public function create(int $userId, string $planCode, string $billingCycle = 'monthly'): Subscription
    {
        $plan = DB::table('plans')->where('code', $planCode)->first();
        if ($plan === null) {
            throw new \RuntimeException("Plan not found: {$planCode}");
        }

        $price = DB::table('plan_prices')
            ->where('plan_id', $plan->id)
            ->where('billing_cycle', $billingCycle)
            ->first();

        $amountMinor = $price ? (int) $price->amount_minor : 0;
        $subscriptionNo = 'SUB-' . now()->format('YmdHis') . '-' . strtoupper(Str::random(6));
        $defaultCurrency = (new PaymentService())->getDefaultCurrency();

        $subscription = Subscription::create([
            'subscription_no' => $subscriptionNo,
            'user_id' => $userId,
            'plan_id' => $plan->id,
            'plan_code' => $planCode,
            'billing_cycle' => $billingCycle,
            'amount_minor' => $amountMinor,
            'currency' => $price->currency ?? $defaultCurrency,
            'status' => self::STATUS_PENDING,
            'quota_status' => 'normal',
            'auto_renew' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $subscription;
    }

    /**
     * 激活订阅（支付成功后调用）。
     * 1. 更新 subscription.status=active + current_period
     * 2. 生成 Invoice（dns_billings）
     * 3. 更新 Profile.plan_id + 触发 republish
     */
    public function activate(string $subscriptionId, string $paymentRef): void
    {
        $sub = Subscription::findOrFail($subscriptionId);
        if ($sub->status === self::STATUS_ACTIVE) {
            return; // 幂等
        }

        $now = Carbon::now();
        $periodStart = $now;
        $periodEnd = $sub->billing_cycle === 'yearly'
            ? $now->copy()->addYear()
            : $now->copy()->addMonth();

        $sub->update([
            'status' => self::STATUS_ACTIVE,
            'started_at' => $periodStart,
            'current_period_start' => $periodStart,
            'current_period_end' => $periodEnd,
            'updated_at' => $now,
        ]);

        // 生成 Invoice
        $this->generateInvoice($sub, $paymentRef);

        // 更新 Profile.plan_id + 触发 republish
        $this->setPlan($sub->user_id, $sub->plan_code);
    }

    /**
     * 取消订阅（cancel_at_period_end=true）。
     * 当前周期继续使用，到期后自动降级。
     */
    public function cancel(string $subscriptionId): void
    {
        $sub = Subscription::findOrFail($subscriptionId);
        $sub->update([
            'cancel_at_period_end' => true,
            'updated_at' => now(),
        ]);
    }

    /**
     * 恢复订阅，取消取消标记（cancel_at_period_end=false）。
     * 用户反悔后可重新开启自动续费。
     */
    public function resume(string $subscriptionId): void
    {
        $sub = Subscription::findOrFail($subscriptionId);
        $sub->update([
            'cancel_at_period_end' => false,
            'updated_at' => now(),
        ]);
    }

    /**
     * 到期后降级为 Free。
     */
    public function markExpired(int $userId): void
    {
        $now = now();
        DB::table('subscriptions')->where('user_id', $userId)->update([
            'status' => self::STATUS_EXPIRED,
            'expired_at' => $now,
            'updated_at' => $now,
        ]);

        // 降级为 Free
        $this->setPlan($userId, 'free');
    }

    /**
     * 进入 past_due：默认 7 天宽限期。
     * 保持当前套餐，但同步 users.plan_code。
     */
    public function markPastDue(int $userId, int $graceDays = 7): void
    {
        $now = now();
        $currentPlanCode = DB::table('subscriptions')->where('user_id', $userId)->value('plan_code') ?? 'free';

        DB::table('subscriptions')->where('user_id', $userId)->update([
            'status' => self::STATUS_PAST_DUE,
            'grace_until' => $now->copy()->addDays($graceDays),
            'updated_at' => $now,
        ]);

        // 同步 users.plan_code
        User::whereKey($userId)->update(['plan_code' => $currentPlanCode]);
    }

    /**
     * 进入 suspended：超过 grace 期，强制停服。
     * 降级为 Free 并同步。
     */
    public function markSuspended(int $userId): void
    {
        $now = now();
        DB::table('subscriptions')->where('user_id', $userId)->update([
            'status' => self::STATUS_SUSPENDED,
            'suspended_at' => $now,
            'updated_at' => $now,
        ]);

        // 降级为 Free 并同步
        $this->setPlan($userId, 'free');
    }

    /**
     * 获取当前活跃订阅。
     */
    public function getActive(int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }
        $row = DB::table('subscriptions')->where('user_id', $userId)->first();
        if ($row === null) {
            if (! User::whereKey($userId)->exists()) {
                return null;
            }
            $planId = $this->resolvePlanId('free');
            DB::table('subscriptions')->insert([
                'user_id' => $userId,
                'plan_id' => $planId,
                'plan_code' => 'free',
                'status' => self::STATUS_ACTIVE,
                'started_at' => now(),
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

    public function isActive(int $userId): bool
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
     * 设置/更新用户 plan，同步 Profile + 触发 republish。
     */
    public function setPlan(int $userId, string $planCode, ?int $monthlyLimit = null): void
    {
        $now = now();
        $planId = $this->resolvePlanId($planCode);
        $currentQuotaStatus = DB::table('subscriptions')->where('user_id', $userId)->value('quota_status');

        DB::table('subscriptions')->updateOrInsert(
            ['user_id' => $userId],
            [
                'plan_id' => $planId,
                'plan_code' => $planCode,
                'status' => self::STATUS_ACTIVE,
                'monthly_query_limit' => $monthlyLimit,
                'quota_status' => 'normal',
                'started_at' => $now,
                'grace_until' => null,
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );

        // Write-through cache
        User::whereKey($userId)->update(['plan_code' => $planCode]);

        // 升级后触发 re-publish
        if ($currentQuotaStatus === 'exceeded' || $planCode !== 'free') {
            $profiles = Profile::where('user_id', $userId)->get(['profile_id']);
            if ($profiles->isNotEmpty()) {
                $publishService = app(ProfilePublishApplicationService::class);
                foreach ($profiles as $profile) {
                    try {
                        $publishService->publishForUser($userId, $profile->profile_id);
                    } catch (\Throwable $e) {
                        logger()->error('setPlan: republish failed', [
                            'user_id' => $userId,
                            'profile_id' => $profile->profile_id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }
        }
    }

    /**
     * 生成 Invoice（账单）。
     */
    private function generateInvoice(Subscription $sub, string $paymentRef): void
    {
        $now = now();
        $billingNo = 'INV-' . $now->format('YmdHis') . '-' . strtoupper(Str::random(6));

        DB::table('billings')->insert([
            'billing_no' => $billingNo,
            'user_id' => $sub->user_id,
            'currency' => $sub->currency,
            'subtotal_minor' => $sub->amount_minor,
            'discount_minor' => 0,
            'tax_minor' => 0,
            'total_minor' => $sub->amount_minor,
            'status' => 'paid',
            'issued_at' => $now,
            'paid_at' => $now,
            'meta' => json_encode([
                'kind' => 'subscription',
                'subscription_id' => $sub->id,
                'subscription_no' => $sub->subscription_no,
                'plan_code' => $sub->plan_code,
                'billing_cycle' => $sub->billing_cycle,
                'payment_ref' => $paymentRef,
            ], JSON_UNESCAPED_UNICODE),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function resolvePlanId(string $planCode): int
    {
        (new PlanCatalogService())->ensureDefaults();

        $planId = DB::table('plans')->where('code', $planCode)->value('id');

        if ($planId === null) {
            $planId = DB::table('plans')->where('code', 'free')->value('id');
        }

        if ($planId === null) {
            throw new \RuntimeException('No available plan found for subscription bootstrap.');
        }

        return (int) $planId;
    }
}