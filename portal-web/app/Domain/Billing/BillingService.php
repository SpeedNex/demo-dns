<?php

namespace App\Domain\Billing;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class BillingService
{
    /**
     * 获取用户余额信息
     */
    public function getBalance(string $userId): array
    {
        $user = User::findOrFail($userId);
        $sub = (new SubscriptionService())->getActive($userId) ?? ['plan_code' => 'free'];
        $wallet = (new WalletService())->balance($userId);

        return [
            'user_id' => $userId,
            'balance_minor' => $wallet['balance_minor'],
            'currency' => $wallet['currency'],
            'plan_code' => $sub['plan_code'],
            'status' => $user->status,
            'balance_updated_at' => optional($user->wallet)->updated_at?->toIso8601String(),
        ];
    }

    /**
     * 充值（SSOT: `wallets`，`users.balance_minor` 仅作只读缓存）
     */
    public function charge(string $userId, int $amountMinor, string $description): array
    {
        return DB::transaction(function () use ($userId, $amountMinor, $description): array {
            User::lockForUpdate()->findOrFail($userId);
            $wallet = (new WalletService())->balance($userId);
            $before = (int) $wallet['balance_minor'];
            $now = now();
            $currency = $wallet['currency'] ?? 'USD';
            $transactionKey = 'wallet:credit:manual:' . $userId . ':' . $now->format('YmdHisv');

            $newBalance = (new WalletService())->credit(
                userId: $userId,
                amountMinor: $amountMinor,
                source: 'manual',
                idempotencyKey: $transactionKey,
                description: $description,
            );
            $after = $newBalance;

            $billingNo = 'BIL-' . $now->format('YmdHis') . '-' . str_pad((string) $userId, 6, '0', STR_PAD_LEFT);
            $billingId = DB::table('billings')->insertGetId([
                'billing_no' => $billingNo,
                'user_id' => $userId,
                'currency' => $currency,
                'subtotal_minor' => $amountMinor,
                'discount_minor' => 0,
                'tax_minor' => 0,
                'total_minor' => $amountMinor,
                'status' => 'paid',
                'issued_at' => $now,
                'paid_at' => $now,
                'meta' => json_encode(['kind' => 'wallet_topup', 'description' => $description], JSON_UNESCAPED_UNICODE),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $transaction = DB::table('wallet_transactions')
                ->where('user_id', $userId)
                ->where('idempotency_key', $transactionKey)
                ->latest('updated_at')
                ->first();
            $transactionId = $transaction->id ?? null;
            if ($transactionId !== null) {
                DB::table('wallet_transactions')->where('id', $transactionId)->update(['billing_id' => $billingId]);
            }
            DB::table('billing_items')->insert([
                'billing_id' => $billingId,
                'item_type' => 'wallet_topup',
                'source_type' => 'wallet_transaction',
                'source_id' => $transactionId,
                'description' => $description !== '' ? $description : 'Wallet recharge',
                'quantity' => 1,
                'unit_price_minor' => $amountMinor,
                'amount_minor' => $amountMinor,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return [
                'transaction_id' => (string) $transactionId,
                'billing_id' => (string) $billingId,
                'billing_no' => $billingNo,
                'type' => 'charge',
                'amount_minor' => $amountMinor,
                'currency' => $currency,
                'balance_before' => $before,
                'balance_after' => $after,
                'description' => $description,
                'status' => 'succeeded',
                'created_at' => $now->toIso8601String(),
            ];
        });
    }

    /**
     * 退款（SSOT: `wallets`）
     */
    public function refund(string $userId, int $amountMinor, string $description): array
    {
        return DB::transaction(function () use ($userId, $amountMinor, $description): array {
            User::lockForUpdate()->findOrFail($userId);

            $wallet = (new WalletService())->balance($userId);
            $before = (int) $wallet['balance_minor'];
            if ($before < $amountMinor) {
                throw ValidationException::withMessages([
                    'amount_minor' => 'Insufficient balance for refund.',
                ]);
            }
            $currency = $wallet['currency'] ?? 'USD';
            $now = now();
            $transactionKey = 'wallet:debit:refund:' . $userId . ':' . $now->format('YmdHisv');

            $newBalance = (new WalletService())->debit(
                userId: $userId,
                amountMinor: $amountMinor,
                source: 'refund',
                idempotencyKey: $transactionKey,
                description: $description,
            );
            $after = $newBalance;

            $transaction = DB::table('wallet_transactions')
                ->where('user_id', $userId)
                ->where('idempotency_key', $transactionKey)
                ->latest('updated_at')
                ->first();
            $transactionId = $transaction->id ?? null;

            $billingNo = 'BIL-' . $now->format('YmdHis') . '-R' . str_pad((string) $userId, 5, '0', STR_PAD_LEFT);
            $billingId = DB::table('billings')->insertGetId([
                'billing_no' => $billingNo,
                'user_id' => $userId,
                'currency' => $currency,
                'subtotal_minor' => 0,
                'discount_minor' => 0,
                'tax_minor' => 0,
                'total_minor' => 0,
                'status' => 'paid',
                'issued_at' => $now,
                'paid_at' => $now,
                'meta' => json_encode(['kind' => 'refund', 'description' => $description], JSON_UNESCAPED_UNICODE),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            if ($transactionId !== null) {
                DB::table('wallet_transactions')->where('id', $transactionId)->update(['billing_id' => $billingId]);
            }
            DB::table('billing_items')->insert([
                'billing_id' => $billingId,
                'item_type' => 'credit',
                'source_type' => 'wallet_transaction',
                'source_id' => $transactionId,
                'description' => $description !== '' ? $description : 'Wallet refund',
                'quantity' => 1,
                'unit_price_minor' => $amountMinor,
                'amount_minor' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return [
                'transaction_id' => (string) $transactionId,
                'billing_id' => (string) $billingId,
                'billing_no' => $billingNo,
                'type' => 'refund',
                'amount_minor' => $amountMinor,
                'currency' => $currency,
                'balance_before' => $before,
                'balance_after' => $after,
                'description' => $description,
                'status' => 'succeeded',
                'created_at' => $now->toIso8601String(),
            ];
        });
    }

    /**
     * 账单历史
     */
    public function invoices(string $userId, int $page = 1, int $perPage = 20): array
    {
        $query = DB::table('billings')->orderByDesc('created_at');
        if ($userId !== '') {
            $query->where('user_id', $userId);
        }

        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $total = (clone $query)->count();
        $items = $query->forPage($page, $perPage)->get()->map(fn ($row): array => [
            'id' => (string) $row->id,
            'user_id' => $row->user_id,
            'invoice_no' => $row->billing_no,
            'amount_minor' => (int) $row->total_minor,
            'currency' => $row->currency,
            'status' => $row->status,
            'type' => data_get(json_decode((string) ($row->meta ?? '[]'), true), 'kind', 'billing'),
            'description' => data_get(json_decode((string) ($row->meta ?? '[]'), true), 'description'),
            'finalized' => in_array($row->status, ['paid', 'cancelled'], true),
            'paid_at' => $row->paid_at,
            'finalized_at' => $row->paid_at ?? $row->cancelled_at,
            'created_at' => $row->created_at,
        ])->all();

        return [
            'data' => $items,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
            ],
        ];
    }
}
