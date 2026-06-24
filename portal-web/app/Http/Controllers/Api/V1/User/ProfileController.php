<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Domain\Profile\ProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProfileController
{
    public function __construct(
        private readonly ProfileService $service,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $profiles = $this->service->listForCurrentUser($request->user()->uid);

        return response()->json([
            'data' => $profiles,
            'meta' => [
                'page' => 1,
                'per_page' => 20,
                'total' => count($profiles),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $result = $this->service->create(
                $request->user()->uid,
                $request->only(['name', 'description', 'default_action', 'block_response', 'security_enabled', 'privacy_enabled']),
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $result], 201);
    }

    public function show(Request $request, string $profileId): JsonResponse
    {
        $result = $this->service->get($request->user()->uid, $profileId);

        return response()->json(['data' => $result]);
    }

    public function update(Request $request, string $profileId): JsonResponse
    {
        $result = $this->service->update(
            $request->user()->uid,
            $profileId,
            $request->only(['name', 'description', 'default_action', 'block_response', 'security_enabled', 'adblock_enabled', 'parental_enabled', 'privacy_enabled', 'safe_search_enabled', 'log_mode']),
        );

        return response()->json(['data' => $result]);
    }

    public function destroy(Request $request, string $profileId): JsonResponse
    {
        $result = $this->service->delete($request->user()->uid, $profileId);

        return response()->json(['data' => $result]);
    }

    public function copy(Request $request, string $profileId): JsonResponse
    {
        $result = $this->service->copy($request->user()->uid, $profileId);

        return response()->json(['data' => $result], 201);
    }

    public function batchDestroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'string',
        ]);

        $result = $this->service->batchDelete($request->user()->uid, $validated['ids']);

        return response()->json(['data' => $result]);
    }
}
