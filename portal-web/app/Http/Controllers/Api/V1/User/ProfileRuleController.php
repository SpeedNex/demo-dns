<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Domain\Rule\ProfileRuleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProfileRuleController
{
    public function __construct(
        private readonly ProfileRuleService $service,
    ) {
    }

    public function index(Request $request, string $profileId): JsonResponse
    {
        $rules = $this->service->list($request->user()->uid, $profileId);

        return response()->json(['data' => $rules]);
    }

    public function store(Request $request, string $profileId): JsonResponse
    {
        try {
            $rule = $this->service->create($request->user()->uid, $profileId, $request->all());
        } catch (\InvalidArgumentException $e) {
            return \App\Helpers\ApiResponse::error('INVALID_ARGUMENT', $e->getMessage(), 422);
        }

        return response()->json(['data' => $rule], 201);
    }

    public function destroy(Request $request, string $profileId, string $ruleId): JsonResponse
    {
        $result = $this->service->delete($request->user()->uid, $profileId, $ruleId);

        return response()->json(['data' => $result]);
    }

    public function update(Request $request, string $profileId, string $ruleId): JsonResponse
    {
        $validated = $request->validate([
            'domain' => 'string|max:255',
            'match_type' => 'string|in:exact,suffix,wildcard',
            'list_type' => 'string|in:allow,block',
            'enabled' => 'boolean',
            'note' => 'nullable|string|max:500',
        ]);

        $result = $this->service->update($request->user()->uid, $profileId, $ruleId, $validated);

        return response()->json(['data' => $result]);
    }

    public function batchDestroy(Request $request, string $profileId): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'string',
        ]);

        $result = $this->service->batchDelete($request->user()->uid, $profileId, $validated['ids']);

        return response()->json(['data' => $result]);
    }
}
