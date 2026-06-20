<?php

declare(strict_types=1);

namespace App\Domain\Billing;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * UI.md #54 — 钱包系统。
 *
 * 设计：钱包余额真相在 `wallets.balance_minor` / `wallets.frozen_minor`。
 * 每次变更都必须生成一条 `wallet_transactions`，状态统一为
 * pending/succeeded/failed/cancelled。
 */
final class WalletService
{
    public function balance(string $userId): array
    {
        $row = $this->getOrCreate($userId);
        return [
            'user_id' => $userId,
            'wallet_id' => (int) $row->id,
            'balance_minor' => (int) $row->balance_minor,
            'frozen_minor' => (int) $row->frozen_minor,
            'currency' => $row->currency,
            'status' => $row->status,
        ];
    }

    /**
     * 充值 / 退款等正向入账。
     */
    public function credit(
        string $userId,
        int $amountMinor,
        string $source,
        string $idempotencyKey,
        ?string $description = null,
        ?int $billingId = null,
    ): int {
        return DB::transaction(function () use ($userId, $amountMinor, $source, $idempotencyKey, $description, $billingId): int {
            $existing = DB::table('wallet_transactions')
                ->where('idempotency_key', $idempotencyKey)
                ->first();
            if ($existing !== null) {
                return (int) $existing->balance_after_minor;
            }

            $wallet = $this->lock($userId);
            $newBalance = (int) $wallet->balance_minor + $amountMinor;
            DB::table('wallets')->where('id', $wallet->id)->update([
                'balance_minor' => $newBalance,
                'updated_at' => now(),
            ]);
            DB::table('wallet_transactions')->insert([
                'wallet_id' => $wallet->id,
                'transaction_no' => 'WT' . now()->format('YmdHis') . Str::random(8),
                'user_id' => $userId,
                'billing_id' => $billingId,
                'type' => $source === 'refund' ? 'refund' : 'credit',
                'amount_minor' => $amountMinor,
                'balance_after_minor' => $newBalance,
                'currency' => $wallet->currency,
                'source' => $source,
                'description' => $description,
                'idempotency_key' => $idempotencyKey,
                'status' => 'succeeded',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            return $newBalance;
        });
    }

    /**
     * 扣款（如用量扣费）。
     */
    public function debit(
        string $userId,
        int $amountMinor,
        string $source,
        string $idempotencyKey,
        ?string $description = null,
        ?int $billingId = null,
    ): int {
        return DB::transaction(function () use ($userId, $amountMinor, $source, $idempotencyKey, $description, $billingId): int {
            $existing = DB::table('wallet_transactions')
                ->where('idempotency_key', $idempotencyKey)
                ->first();
            if ($existing !== null) {
                return (int) $existing->balance_after_minor;
            }

            $wallet = $this->lock($userId);
            if ((int) $wallet->balance_minor < $amountMinor) {
                throw new \RuntimeException('Insufficient balance');
            }
            $newBalance = (int) $wallet->balance_minor - $amountMinor;
            DB::table('wallets')->where('id', $wallet->id)->update([
                'balance_minor' => $newBalance,
                'updated_at' => now(),
            ]);
            DB::table('wallet_transactions')->insert([
                'wallet_id' => $wallet->id,
                'transaction_no' => 'WT' . now()->format('YmdHis') . Str::random(8),
                'user_id' => $userId,
                'billing_id' => $billingId,
                'type' => $source === 'refund' ? 'refund' : 'debit',
                'amount_minor' => $amountMinor,
                'balance_after_minor' => $newBalance,
                'currency' => $wallet->currency,
                'source' => $source,
                'description' => $description,
                'idempotency_key' => $idempotencyKey,
                'status' => 'succeeded',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            return $newBalance;
        });
    }

    private function getOrCreate(string $userId)
    {
        $row = DB::table('wallets')->where('user_id', $userId)->first();
        if ($row === null) {
            $currency = 'USD';
            DB::table('wallets')->insert([
                'user_id' => $userId,
                'currency' => $currency,
                'balance_minor' => 0,
                'frozen_minor' => 0,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $row = DB::table('wallets')->where('user_id', $userId)->first();
        }
        return $row;
    }

    private function lock(string $userId)
    {
        $row = DB::table('wallets')->where('user_id', $userId)->lockForUpdate()->first();
        if ($row === null) {
            $this->getOrCreate($userId);
            $row = DB::table('wallets')->where('user_id', $userId)->lockForUpdate()->first();
        }
        return $row;
    }
}
