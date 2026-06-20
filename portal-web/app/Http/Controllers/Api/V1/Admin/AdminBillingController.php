<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Billing\BillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AdminBillingController
{
    public function __construct(
        private readonly BillingService $service = new BillingService(),
    ) {
    }

    public function balance(Request $request, string $userId): JsonResponse
    {
        return response()->json(['data' => $this->service->getBalance($userId)]);
    }

    public function charge(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required',
            'amount_minor' => 'required|integer|min:1',
            'description' => 'nullable|string|max:255',
        ]);

        $result = $this->service->charge(
            (string) $validated['user_id'],
            (int) $validated['amount_minor'],
            $validated['description'] ?? 'Admin manual charge',
        );

        return response()->json(['data' => $result], 201);
    }

    public function refund(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required',
            'amount_minor' => 'required|integer|min:1',
            'description' => 'nullable|string|max:255',
        ]);

        $result = $this->service->refund(
            (string) $validated['user_id'],
            (int) $validated['amount_minor'],
            $validated['description'] ?? 'Admin manual refund',
        );

        return response()->json(['data' => $result], 201);
    }

    public function invoices(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'nullable|string|max:40',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $result = $this->service->invoices(
            $validated['user_id'] ?? '',
            (int) ($validated['page'] ?? 1),
            (int) ($validated['per_page'] ?? 20),
        );

        return response()->json($result);
    }

    public function export(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'nullable|string|max:40',
        ]);

        $result = $this->service->invoices(
            $validated['user_id'] ?? '',
            1,
            10000,
        );

        return response()->json(['data' => $result['data'] ?? []]);
    }
}
