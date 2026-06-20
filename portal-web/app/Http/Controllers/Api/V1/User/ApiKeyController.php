<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Domain\ApiKey\ApiKeyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class ApiKeyController
{
    public function __construct(
        private readonly ApiKeyService $service,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $keys = $this->service->list($request->user()->uid);

        return response()->json(['data' => $keys]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'scopes' => 'sometimes|array',
            'scopes.*' => 'string|in:dns:query,logs:read,stats:read,admin:read',
        ]);

        $scopes = $validated['scopes'] ?? ['dns:query', 'logs:read', 'stats:read'];

        $result = $this->service->create(
            $request->user()->uid,
            $validated['name'],
            $scopes,
        );

        return response()->json(['data' => $result], 201);
    }

    public function destroy(Request $request, int $keyId): JsonResponse
    {
        try {
            $this->service->revoke($request->user()->uid, $keyId);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            throw ValidationException::withMessages(['key' => 'API key not found.']);
        }

        return response()->json(['data' => ['ok' => true]]);
    }
}
