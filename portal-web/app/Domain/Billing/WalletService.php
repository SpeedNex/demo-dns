<?php

declare(strict_types=1);

namespace App\Domain\Billing;

use Illuminate\Support\Facades\DB;

/**
 * UI.md #54 — 钱包系统。
 *
 * 设计：钱包余额真相在 `wallets` 表，`users.balance_minor` 已废弃。
 * 余额变化 = 流水变化，每次都写 `wallet_transactions`。
 * 乐观锁：`wallets.version`，避免并发充值丢失。
 */
final class WalletService
{
    public function balance(string $userId): array
    {
        $row = $this->getOrCreate($userId);
        return [
            'user_id' => $userId,
            'balance_minor' => (int) $row->balance,
            'frozen_minor' => (int) $row->frozen,
            'currency' => $row->currency,
        ];
    }

    /**
     * 充值 / 退款等正向入账。
     */
    public function credit(
        string $userId,
        int $amountMinor,
        string $referenceType,
        string $referenceId,
        ?string $description = null
    ): int {
        return DB::transaction(function () use ($userId, $amountMinor, $referenceType, $referenceId, $description): int {
            $wallet = $this->lock($userId);
            $newBalance = (int) $wallet->balance + $amountMinor;
            DB::table('wallets')->where('id', $wallet->id)->update([
                'balance' => $newBalance,
                'version' => $wallet->version + 1,
                'updated_at' => now(),
            ]);
            DB::table('wallet_transactions')->insert([
                'user_id' => $userId,
                'type' => $referenceType, // charge / refund / adjustment
                'amount_minor' => $amountMinor,
                'currency' => $wallet->currency,
                'description' => $description,
                'status' => 'completed',
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            // 同步到 users.balance_minor 只读缓存
            DB::table('users')->where('id', $userId)->update([
                'balance_minor' => $newBalance,
                'balance_updated_at' => now(),
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
        string $referenceType,
        string $referenceId,
        ?string $description = null
    ): int {
        return DB::transaction(function () use ($userId, $amountMinor, $referenceType, $referenceId, $description): int {
            $wallet = $this->lock($userId);
            if ((int) $wallet->balance < $amountMinor) {
                throw new \RuntimeException('Insufficient balance');
            }
            $newBalance = (int) $wallet->balance - $amountMinor;
            DB::table('wallets')->where('id', $wallet->id)->update([
                'balance' => $newBalance,
                'version' => $wallet->version + 1,
                'updated_at' => now(),
            ]);
            DB::table('wallet_transactions')->insert([
                'wallet_id' => $wallet->id,
                'transaction_no' => 'WT' . now()->format('YmdHis') . \Illuminate\Support\Str::random(8),
                'user_id' => $userId,
                'type' => $referenceType, // usage_deduction
                'amount_minor' => -$amountMinor,
                'balance_after' => $newBalance,
                'currency' => $wallet->currency,
                'description' => $description,
                'status' => 'completed',
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('users')->where('id', $userId)->update([
                'balance_minor' => $newBalance,
                'balance_updated_at' => now(),
            ]);
            return $newBalance;
        });
    }

    private function getOrCreate(string $userId)
    {
        $row = DB::table('wallets')->where('user_id', $userId)->first();
        if ($row === null) {
            $currency = DB::table('users')->where('id', $userId)->value('currency') ?? 'USD';
            DB::table('wallets')->insert([
                'user_id' => $userId,
                'currency' => $currency,
                'balance' => 0,
                'frozen' => 0,
                'version' => 0,
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
