<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Domain\Team\TeamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class TeamController
{
    public function __construct(
        private readonly TeamService $teamService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $teams = $this->teamService->list($request->user()->uid);

        return response()->json([
            'data' => $teams->map(fn ($team) => [
                'id' => $team->id,
                'name' => $team->name,
                'identifier' => $team->slug,
                'description' => $team->description,
                'member_count' => $team->member_count,
                'max_members' => $team->max_members,
                'role' => $this->teamService->members($team->id)
                    ->where('user_id', $request->user()->uid)
                    ->value('role'),
                'created_at' => $team->created_at,
            ]),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'identifier' => 'nullable|string|max:100|regex:/^[a-z0-9\-]+$/',
            'description' => 'nullable|string|max:500',
            'max_members' => 'nullable|integer|min:1|max:1000',
        ]);

        // Auto-generate identifier from name if not provided
        $slug = $validated['identifier'] ?? \Illuminate\Support\Str::slug($validated['name']);
        if (empty($slug)) {
            $slug = 'team-' . \Illuminate\Support\Str::random(6);
        }
        // Ensure unique slug by appending suffix if taken
        $baseSlug = $slug;
        $counter = 0;
        while (\App\Models\Team::where('slug', $slug)->exists()) {
            $counter++;
            $slug = $baseSlug . '-' . $counter;
        }
        $validated['slug'] = $slug;
        unset($validated['identifier']);

        $team = $this->teamService->create($validated, $request->user()->uid);

        return response()->json([
            'data' => [
                'id' => $team->id,
                'name' => $team->name,
                'identifier' => $team->slug,
                'description' => $team->description,
                'member_count' => $team->member_count,
                'max_members' => $team->max_members,
                'created_at' => $team->created_at,
            ],
        ], 201);
    }

    public function show(Request $request, string $teamId): JsonResponse
    {
        $team = $this->teamService->get($teamId, $request->user()->uid);

        return response()->json([
            'data' => [
                'id' => $team->id,
                'name' => $team->name,
                'identifier' => $team->slug,
                'description' => $team->description,
                'owner_id' => $team->owner_id,
                'member_count' => $team->member_count,
                'max_members' => $team->max_members,
                'status' => $team->status,
                'created_at' => $team->created_at,
                'updated_at' => $team->updated_at,
            ],
        ]);
    }

    public function update(Request $request, string $teamId): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'description' => 'nullable|string|max:500',
        ]);

        $team = $this->teamService->update($teamId, $validated, $request->user()->uid);

        return response()->json([
            'data' => [
                'id' => $team->id,
                'name' => $team->name,
                'identifier' => $team->slug,
                'description' => $team->description,
                'updated_at' => $team->updated_at,
            ],
        ]);
    }

    public function destroy(Request $request, string $teamId): JsonResponse
    {
        $this->teamService->delete($teamId, $request->user()->uid);

        return response()->json(['data' => ['deleted' => true]]);
    }

    public function members(Request $request, string $teamId): JsonResponse
    {
        $this->teamService->get($teamId, $request->user()->uid);
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

    public function removeMember(Request $request, string $teamId, string $userId): JsonResponse
    {
        $this->teamService->removeMember($teamId, $userId, $request->user()->uid);

        return response()->json(['data' => ['removed' => true]]);
    }

    public function updateMemberRole(Request $request, string $teamId, string $userId): JsonResponse
    {
        $validated = $request->validate([
            'role' => ['required', Rule::in(['admin', 'member'])],
        ]);

        $this->teamService->updateMemberRole($teamId, $userId, $validated['role'], $request->user()->uid);

        return response()->json(['data' => ['role' => $validated['role']]]);
    }

    public function leaveTeam(Request $request, string $teamId): JsonResponse
    {
        $this->teamService->leaveTeam($teamId, $request->user()->uid);

        return response()->json(['data' => ['left' => true]]);
    }

    public function transferOwnership(Request $request, string $teamId): JsonResponse
    {
        $validated = $request->validate([
            'new_owner_id' => 'required',
        ]);

        $this->teamService->transferOwnership($teamId, (string) $validated['new_owner_id'], (string) $request->user()->uid);

        return response()->json(['data' => ['transferred' => true]]);
    }

    public function switchTeam(Request $request, string $teamId): JsonResponse
    {
        $this->teamService->switchTeam($teamId, $request->user()->uid);

        return response()->json(['data' => ['current_team_id' => $teamId]]);
    }

    public function invitations(Request $request, string $teamId): JsonResponse
    {
        $this->teamService->get($teamId, $request->user()->uid);
        $invitations = $this->teamService->listInvitations($teamId);

        return response()->json([
            'data' => $invitations->map(fn ($inv) => [
                'id' => $inv->id,
                'email' => $inv->email,
                'role' => $inv->role,
                'invited_by' => $inv->invited_by,
                'expires_at' => $inv->expires_at,
                'accepted_at' => $inv->accepted_at,
                'declined_at' => $inv->declined_at,
                'created_at' => $inv->created_at,
            ]),
        ]);
    }

    public function invite(Request $request, string $teamId): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email|max:255',
            'role' => ['sometimes', Rule::in(['admin', 'member'])],
        ]);

        $invitation = $this->teamService->invite($teamId, $validated, $request->user()->uid);

        return response()->json([
            'data' => [
                'id' => $invitation->id,
                'email' => $invitation->email,
                'role' => $invitation->role,
                'expires_at' => $invitation->expires_at,
            ],
        ], 201);
    }

    public function cancelInvitation(Request $request, string $teamId, string $invitationId): JsonResponse
    {
        $this->teamService->cancelInvitation($teamId, $invitationId, $request->user()->uid);

        return response()->json(['data' => ['cancelled' => true]]);
    }

    public function batchCancelInvitations(Request $request, string $teamId): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'string',
        ]);

        $count = $this->teamService->batchCancelInvitations($teamId, $validated['ids'], $request->user()->uid);

        return response()->json(['data' => ['cancelled' => $count]]);
    }

    public function acceptInvitation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => 'required|string',
        ]);

        $this->teamService->acceptInvite($validated['token'], $request->user()->uid);

        return response()->json(['data' => ['accepted' => true]]);
    }

    public function pendingInvitations(Request $request): JsonResponse
    {
        $user = $request->user();
        $invitations = $this->teamService->pendingInvitations($user->email);

        return response()->json([
            'data' => $invitations->map(fn ($inv) => [
                'id' => $inv->id,
                'team_id' => $inv->team_id,
                'team_name' => $inv->team?->name,
                'role' => $inv->role,
                'expires_at' => $inv->expires_at,
                'created_at' => $inv->created_at,
            ]),
        ]);
    }
}
