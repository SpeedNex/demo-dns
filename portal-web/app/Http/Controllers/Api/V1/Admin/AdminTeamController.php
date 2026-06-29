<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\Team\TeamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AdminTeamController
{
    public function __construct(
        private readonly TeamService $teamService = new TeamService(),
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $query = \App\Models\Team::query();

        if ($keyword = $request->get('keyword')) {
            $query->where(function ($q) use ($keyword): void {
                $q->where('name', 'like', "%{$keyword}%")
                    ->orWhere('slug', 'like', "%{$keyword}%");
            });
        }

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        $teams = $query->orderBy('created_at', 'desc')
            ->paginate(min((int) ($request->get('per_page', 20)), 100));

        return response()->json([
            'data' => $teams->items(),
            'meta' => [
                'page' => $teams->currentPage(),
                'per_page' => $teams->perPage(),
                'total' => $teams->total(),
            ],
        ]);
    }

    public function show(string $teamId): JsonResponse
    {
        $team = \App\Models\Team::with('owner')->findOrFail($teamId);

        return response()->json(['data' => $team]);
    }

    public function members(string $teamId): JsonResponse
    {
        $members = $this->teamService->members($teamId);

        return response()->json([
            'data' => $members->map(fn ($member) => [
                'user_id' => $member->user_id,
                'name' => $member->user?->username,
                'email' => $member->user?->email,
                'role' => $member->role,
                'joined_at' => $member->joined_at,
            ]),
        ]);
    }

    public function disable(string $teamId): JsonResponse
    {
        $team = \App\Models\Team::findOrFail($teamId);
        $team->update(['status' => 'archived']);

        return response()->json(['data' => ['status' => 'archived']]);
    }

    public function enable(string $teamId): JsonResponse
    {
        $team = \App\Models\Team::findOrFail($teamId);
        $team->update(['status' => 'active']);

        return response()->json(['data' => ['status' => 'active']]);
    }
}
