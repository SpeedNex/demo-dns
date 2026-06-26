<?php

namespace App\Domain\Billing;

use Illuminate\Support\Facades\DB;

/**
 * SaaS 订阅模式：账单（Invoice）服务。
 * 仅保留 bills() 查询方法，删除钱包充值/退款逻辑。
 */
final class BillingService
{
    /**
     * 账单历史
     */
    public function bills(string $userId, int $page = 1, int $perPage = 20, string $status = ''): array
    {
        $query = DB::table('billings as b')
            ->leftJoin('users as u', 'u.uid', '=', 'b.user_id')
            ->select([
                'b.*',
                'u.username as user_name',
                'u.email as user_email',
            ])
            ->orderByDesc('b.created_at');
        if ($userId !== '') {
            $query->where('b.user_id', $userId);
        }
        if ($status !== '') {
            $query->where('b.status', $status);
        }

        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $total = (clone $query)->count();
        $items = $query->forPage($page, $perPage)->get()->map(function ($row): array {
            $meta = json_decode((string) ($row->meta ?? '[]'), true);
            $meta = is_array($meta) ? $meta : [];
            $totalMinor = (int) $row->total_minor;
            $paidMinor = $row->status === 'paid' ? $totalMinor : 0;

            return [
                'id' => (string) $row->id,
                'user_id' => $row->user_id,
                'username' => $row->user_name,
                'user_name' => $row->user_name,
                'user_email' => $row->user_email,
                'billing_no' => $row->billing_no,
                'amount_minor' => $totalMinor,
                'subtotal_amount_minor' => (int) $row->subtotal_minor,
                'discount_amount_minor' => (int) $row->discount_minor,
                'tax_amount_minor' => (int) $row->tax_minor,
                'total_amount_minor' => $totalMinor,
                'amount_paid_minor' => $paidMinor,
                'amount_due_minor' => max(0, $totalMinor - $paidMinor),
                'currency' => $row->currency,
                'status' => $row->status,
                'type' => data_get($meta, 'kind', 'billing'),
                'description' => data_get($meta, 'description'),
                'finalized' => in_array($row->status, ['paid', 'cancelled', 'canceled'], true),
                'issued_at' => $row->issued_at,
                'paid_at' => $row->paid_at,
                'finalized_at' => $row->paid_at ?? $row->cancelled_at,
                'created_at' => $row->created_at,
            ];
        })->all();

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