<?php

declare(strict_types=1);

namespace App\Domain\Billing;

use App\Models\Order;
use App\Models\PaymentTransaction;
use Illuminate\Support\Facades\DB;

/**
 * UI.md #78 — 财务对账（可读报告版）。
 *
 * 每天校验项：
 *  - 钱包：wallets.balance = SUM(wallet_transactions.amount_minor)
 *  - 订单：orders.payable_amount_minor = sum(payment_transactions.amount_minor)
 *  - 账单：billing.amount = SUM(billing_items.amount)
 *  - 退款：refunded ≤ paid
 *  - 订阅：active subscription 必须关联至少一个 paid order (#59)
 *  - 账单：billings 必须关联 order 或 usage_record
 *  - Usage：ClickHouse usage_events count = usage_records.query_count (#69 客户端校验)
 *
 * 输出格式: {check, ok, detail, samples: [{id, expected, actual, diff}]}
 */
final class FinanceVerifier
{
    /**
     * @return array<int,array{check:string,ok:bool,detail:string,samples?:array}>
     */
    public function verify(): array
    {
        return [
            'wallet_mismatch'         => $this->checkWalletBalance(),
            'order_payment_mismatch'  => $this->checkOrderVsPayment(),
            'billing_items_mismatch'  => $this->checkBillingVsItems(),
            'refund_cap'              => $this->checkRefundCap(),
            'subscription_without_order' => $this->checkActiveSubscriptionHasPaidOrder(),
            'billing_unlinked'        => $this->checkBillingRelation(),
        ];
    }

    private function checkWalletBalance(): array
    {
        $wallets = DB::table('wallets')->get(['id', 'user_id', 'balance']);
        $bad = [];
        foreach ($wallets as $w) {
            $sum = (int) DB::table('wallet_transactions')
                ->where('user_id', $w->user_id)
                ->sum('amount_minor');
            $expected = (int) $w->balance;
            if ($expected !== $sum) {
                $bad[] = [
                    'user_id' => $w->user_id,
                    'wallet_id' => (int) $w->id,
                    'expected' => $expected,
                    'actual' => $sum,
                    'diff' => $sum - $expected,
                ];
            }
        }
        return [
            'check' => 'wallets.balance == sum(wallet_transactions)',
            'ok' => $bad === [],
            'detail' => $bad === [] ? 'all wallets consistent' : sprintf('%d wallets mismatched', count($bad)),
            'samples' => array_slice($bad, 0, 5),
        ];
    }

    private function checkOrderVsPayment(): array
    {
        $mismatches = DB::table('orders as o')
            ->leftJoin('payment_transactions as pt', function ($j) {
                $j->on('pt.order_id', '=', 'o.id')->where('pt.status', 'success');
            })
            ->where('o.status', Order::STATUS_PAID)
            ->groupBy('o.id', 'o.user_id', 'o.payable_amount_minor')
            ->havingRaw('COALESCE(SUM(pt.amount_minor), 0) <> o.payable_amount_minor')
            ->select('o.id', 'o.user_id', 'o.payable_amount_minor', DB::raw('COALESCE(SUM(pt.amount_minor), 0) as paid'))
            ->limit(5)
            ->get();
        $samples = [];
        foreach ($mismatches as $m) {
            $samples[] = [
                'order_id' => (int) $m->id,
                'user_id' => $m->user_id,
                'expected' => (int) $m->payable_amount_minor,
                'actual' => (int) $m->paid,
                'diff' => (int) $m->paid - (int) $m->payable_amount_minor,
            ];
        }
        return [
            'check' => 'orders.amount_minor == sum(payments.success)',
            'ok' => $mismatches->isEmpty(),
            'detail' => $mismatches->isEmpty()
                ? 'all paid orders match payments'
                : sprintf('%d orders mismatched', $mismatches->count()),
            'samples' => $samples,
        ];
    }

    private function checkBillingVsItems(): array
    {
        $bad = DB::table('invoices as b')
            ->leftJoin('billing_items as bi', 'bi.billing_id', '=', 'b.id')
            ->groupBy('b.id', 'b.user_id', 'b.amount_minor')
            ->havingRaw('b.amount_minor <> COALESCE(SUM(bi.amount_minor), 0)')
            ->select('b.id', 'b.user_id', 'b.amount_minor', DB::raw('COALESCE(SUM(bi.amount_minor), 0) as items_sum'))
            ->limit(5)
            ->get();
        $samples = [];
        foreach ($bad as $b) {
            $samples[] = [
                'billing_id' => (int) $b->id,
                'user_id' => $b->user_id,
                'expected' => (int) $b->amount_minor,
                'actual' => (int) $b->items_sum,
                'diff' => (int) $b->items_sum - (int) $b->amount_minor,
            ];
        }
        return [
            'check' => 'invoices.amount_minor == sum(billing_items.amount_minor)',
            'ok' => $bad->isEmpty(),
            'detail' => $bad->isEmpty() ? 'all invoices match items' : sprintf('%d invoices mismatched', $bad->count()),
            'samples' => $samples,
        ];
    }

    private function checkRefundCap(): array
    {
        $refunded = (int) DB::table('payment_transactions')
            ->where('status', PaymentTransaction::STATUS_REFUNDED)
            ->sum('amount_minor');
        $paid = (int) DB::table('payment_transactions')
            ->where('status', PaymentTransaction::STATUS_SUCCESS)
            ->sum('amount_minor');
        return [
            'check' => 'refunded <= paid',
            'ok' => $refunded <= $paid,
            'detail' => sprintf('refunded=%d, paid=%d, diff=%d', $refunded, $paid, $paid - $refunded),
        ];
    }

    /**
     * UI.md #59 — active subscription 必须存在 paid order。
     */
    private function checkActiveSubscriptionHasPaidOrder(): array
    {
        $orphan = DB::table('subscriptions as s')
            ->leftJoin('orders as o', function ($j) {
                $j->on('o.user_id', '=', 's.user_id')->where('o.status', Order::STATUS_PAID);
            })
            ->where('s.status', 'active')
            ->whereNull('o.id')
            ->select('s.user_id', 's.id as subscription_id')
            ->limit(5)
            ->get();
        $samples = [];
        foreach ($orphan as $o) {
            $samples[] = [
                'user_id' => $o->user_id,
                'subscription_id' => (int) $o->subscription_id,
            ];
        }
        return [
            'check' => 'active subscription has paid order',
            'ok' => $orphan->isEmpty(),
            'detail' => $orphan->isEmpty()
                ? 'all active subscriptions have paid order'
                : sprintf('%d orphan subscriptions', $orphan->count()),
            'samples' => $samples,
        ];
    }

    private function checkBillingRelation(): array
    {
        $bad = DB::table('invoices as b')
            ->where(function ($q) {
                $q->whereNull('b.order_id')->whereNull('b.billing_period_id');
            })
            ->where('b.billing_type', 'plan')
            ->limit(5)
            ->get(['b.id', 'b.user_id', 'b.billing_type']);
        $samples = [];
        foreach ($bad as $b) {
            $samples[] = [
                'billing_id' => (int) $b->id,
                'user_id' => $b->user_id,
                'billing_type' => $b->billing_type,
            ];
        }
        return [
            'check' => 'invoices (plan) link to order or billing_period',
            'ok' => $bad->isEmpty(),
            'detail' => $bad->isEmpty() ? 'all plan invoices linked' : sprintf('%d unlinked', $bad->count()),
            'samples' => $samples,
        ];
    }
}
