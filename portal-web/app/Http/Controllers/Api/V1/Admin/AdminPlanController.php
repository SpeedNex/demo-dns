<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Billing\PlanCatalogService;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class AdminPlanController
{
    public function __construct(
        private readonly PlanCatalogService $plans = new PlanCatalogService(),
    ) {
    }

    public function index(): JsonResponse
    {
        return response()->json(['data' => $this->plans->adminList()]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatePayload($request);

        return response()->json(['data' => $this->plans->store($validated)], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $plan = Plan::query()->findOrFail($id);
        $validated = $this->validatePayload($request, $plan);

        return response()->json(['data' => $this->plans->update($plan, $validated)]);
    }

    public function destroy(string $id): JsonResponse
    {
        $plan = Plan::query()->findOrFail($id);
        $this->plans->delete($plan);

        return response()->json(['data' => ['deleted' => true]]);
    }

    /**
     * 获取某个套餐下的用户列表（分页）
     */
    public function users(Request $request, string $code): JsonResponse
    {
        $page = (int) $request->query('page', 1);
        $perPage = (int) $request->query('per_page', 20);

        return response()->json($this->plans->getUsersByPlan($code, $page, $perPage));
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, ?Plan $plan = null): array
    {
        return $request->validate([
            'code' => [
                $plan === null ? 'required' : 'sometimes',
                'string',
                'max:50',
                Rule::unique('plans', 'code')->ignore($plan?->id),
            ],
            'name' => [$plan === null ? 'required' : 'sometimes', 'string', 'max:120'],
            'description' => 'sometimes|nullable|string|max:255',
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
            'sort_order' => 'sometimes|integer|min:0|max:9999',
            'is_featured' => 'sometimes|boolean',
            'badge' => 'sometimes|nullable|string|max:50',
            'features' => 'sometimes|array',
            'features.*' => 'string|max:255',
            'limits' => 'sometimes|array',
            'prices' => [$plan === null ? 'required' : 'sometimes', 'array', 'min:1'],
            'prices.*.billing_cycle' => ['required_with:prices', Rule::in(['monthly', 'yearly'])],
            'prices.*.currency' => 'required_with:prices|string|max:8',
            'prices.*.amount_minor' => 'required_with:prices|integer|min:0',
            'prices.*.original_amount_minor' => 'nullable|integer|min:0',
            'prices.*.status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ]);
    }
}
