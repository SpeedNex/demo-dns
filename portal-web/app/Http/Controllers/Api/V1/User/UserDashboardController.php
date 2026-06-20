<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Application\Member\MemberCenterOverviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class UserDashboardController
{
    public function __construct(
        private readonly MemberCenterOverviewService $service,
    ) {
    }

    public function overview(Request $request): JsonResponse
    {
        $overview = $this->service->getOverview($request->user()->uid);

        return response()->json(['data' => $overview]);
    }
}
