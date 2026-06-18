<?php

namespace App\Http\Controllers\Api\V1\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Admin Finance controller (balances, recharges, bills, refunds).
 * These endpoints return data from dns_users table for balances,
 * and empty arrays for recharge/bill/refund until dedicated tables are created.
 */
final class AdminFinanceController
{
    /** GET /admin/finance/balances — all user balances (SSOT: `wallets`) */
    public function balances(): JsonResponse
    {
        // SSOT 余额在 `wallets` 表；这里 join 拿真相，users.balance_minor 仅作 fallback
        $prefix = DB::getTablePrefix();
        $rows = DB::table('users')
            ->leftJoin('wallets', 'wallets.user_id', '=', 'users.id')
            ->select([
                'users.id',
                'users.username',
                'users.email',
                'users.plan_code',
                'users.role',
                'users.status',
                DB::raw("COALESCE({$prefix}wallets.balance, 0) as balance_minor"),
                DB::raw("COALESCE({$prefix}wallets.currency, {$prefix}users.currency, 'USD') as currency"),
                DB::raw("COALESCE({$prefix}wallets.updated_at, {$prefix}users.balance_updated_at) as balance_updated_at"),
                'users.created_at',
            ])
            ->orderBy('users.created_at', 'desc')
            ->limit(200)
            ->get();

        return response()->json(['data' => $rows]);
    }

    /** GET /admin/finance/recharges — recharge records (placeholder) */
    public function recharges(Request $request): JsonResponse
    {
        return response()->json($this->listTransactions($request, 'charge'));
    }

    /** GET /admin/finance/recharges/export */
    public function rechargeExport(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->exportTransactions($request, 'charge')]);
    }

    /** GET /admin/finance/bills — billing invoices */
    public function bills(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'nullable|string',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        // Bills = invoices from the existing BillingService
        $service = new \App\Domain\Billing\BillingService();
        $result = $service->invoices(
            $validated['user_id'] ?? '',
            (int) ($validated['page'] ?? 1),
            (int) ($validated['per_page'] ?? 20),
        );

        return response()->json($result);
    }

    /** GET /admin/finance/bills/export */
    public function billExport(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'nullable|string',
            'limit' => 'nullable|integer|min:1|max:1000',
        ]);

        $service = new \App\Domain\Billing\BillingService();
        $result = $service->invoices($validated['user_id'] ?? '', 1, (int) ($validated['limit'] ?? 1000));

        return response()->json(['data' => $result['data'] ?? []]);
    }

    /** GET /admin/finance/refunds — refund records (placeholder) */
    public function refunds(Request $request): JsonResponse
    {
        return response()->json($this->listTransactions($request, 'refund'));
    }

    /** POST /admin/finance/refunds/{id}/approve */
    public function approveRefund(string $id): JsonResponse
    {
        $refund = DB::table('wallet_transactions')->where('id', $id)->where('type', 'refund')->first();
        if ($refund === null) {
            abort(404, 'Refund not found.');
        }

        if ($refund->status !== 'pending') {
            return response()->json([
                'data' => [
                    'id' => (string) $refund->id,
                    'status' => $refund->status,
                    'approved' => false,
                ],
            ]);
        }

        DB::table('wallet_transactions')->where('id', $id)->update([
            'status' => 'completed',
            'updated_at' => now(),
        ]);

        return response()->json([
            'data' => [
                'id' => $id,
                'status' => 'completed',
                'approved' => true,
            ],
        ]);
    }

    /** GET /admin/finance/refunds/export */
    public function refundExport(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->exportTransactions($request, 'refund')]);
    }

    /**
     * @return array{data: array<int, array<string, mixed>>, meta: array<string, int>}
     */
    private function listTransactions(Request $request, string $type): array
    {
        $validated = $request->validate([
            'user_id' => 'nullable|string',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 20);
        $query = DB::table('wallet_transactions')->where('type', $type)->orderByDesc('created_at');
        if (! empty($validated['user_id'])) {
            $query->where('user_id', $validated['user_id']);
        }

        $total = (clone $query)->count();
        $items = $query->forPage($page, $perPage)->get()->map(fn ($row): array => [
            'id' => (string) $row->id,
            'user_id' => $row->user_id,
            'type' => $row->type,
            'amount_minor' => (int) $row->amount_minor,
            'currency' => $row->currency,
            'description' => $row->description,
            'status' => $row->status,
            'reference_type' => $row->reference_type,
            'reference_id' => $row->reference_id,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
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

    /**
     * @return array<int, array<string, mixed>>
     */
    private function exportTransactions(Request $request, string $type): array
    {
        $validated = $request->validate([
            'user_id' => 'nullable|string',
            'limit' => 'nullable|integer|min:1|max:1000',
        ]);

        $query = DB::table('wallet_transactions')->where('type', $type)->orderByDesc('created_at');
        if (! empty($validated['user_id'])) {
            $query->where('user_id', $validated['user_id']);
        }

        return $query->limit((int) ($validated['limit'] ?? 1000))->get()->map(fn ($row): array => [
            'id' => (string) $row->id,
            'user_id' => $row->user_id,
            'type' => $row->type,
            'amount_minor' => (int) $row->amount_minor,
            'currency' => $row->currency,
            'description' => $row->description,
            'status' => $row->status,
            'reference_type' => $row->reference_type,
            'reference_id' => $row->reference_id,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ])->all();
    }
}
