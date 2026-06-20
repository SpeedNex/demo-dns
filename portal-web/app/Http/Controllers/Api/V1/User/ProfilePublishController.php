<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\User;

use App\Application\Member\ProfilePublishApplicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProfilePublishController
{
    public function __construct(
        private readonly ProfilePublishApplicationService $service,
    ) {
    }

    public function store(Request $request, string $profileId): JsonResponse
    {
        $result = $this->service->publishForUser((string) $request->user()->uid, $profileId);

        return response()->json(['data' => ['payload' => $result]]);
    }
}
