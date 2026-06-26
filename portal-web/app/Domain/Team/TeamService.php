<?php

namespace App\Domain\Team;

use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class TeamService
{
    /**
     * Create a new team and add the creator as owner.
     *
     * @param  array{name: string, slug: string, description?: string, max_members?: int|null}  $data
     */
    public function create(array $data, string $userId): Team
    {
        if (Team::where('slug', $data['slug'])->exists()) {
            throw ValidationException::withMessages(['slug' => 'Slug already taken.']);
        }

        return DB::transaction(function () use ($data, $userId): Team {
            $team = Team::create([
                'name' => $data['name'],
                'slug' => strtolower($data['slug']),
                'description' => $data['description'] ?? null,
                'owner_id' => $userId,
                'max_members' => $data['max_members'] ?? null,
            ]);

            $this->addMember($team->id, $userId, 'owner');
            $team->update(['member_count' => 1]);

            return $team;
        });
    }

    /**
     * List teams that the user belongs to.
     *
     * @return Collection<int, Team>
     */
    public function list(string $userId): Collection
    {
        $teamIds = TeamMember::where('user_id', $userId)
            ->pluck('team_id');

        return Team::whereIn('id', $teamIds)
            ->where('status', 'active')
            ->get();
    }

    /**
     * Get team details (must be a member).
     */
    public function get(string $teamId, string $userId): Team
    {
        $team = Team::findOrFail($teamId);
        $this->assertMember($teamId, $userId);

        return $team;
    }

    /**
     * Update team details.
     *
     * @param  array{name?: string, description?: string}  $data
     */
    public function update(string $teamId, array $data, string $userId): Team
    {
        $team = Team::findOrFail($teamId);
        $this->assertRole($teamId, $userId, ['owner', 'admin']);

        $team->update($data);

        return $team;
    }

    /**
     * Delete team (soft delete). Only owner.
     */
    public function delete(string $teamId, string $userId): void
    {
        $team = Team::findOrFail($teamId);
        $this->assertRole($teamId, $userId, ['owner']);

        DB::transaction(function () use ($team): void {
            TeamMember::where('team_id', $team->id)->delete();
            TeamInvitation::where('team_id', $team->id)->delete();
            $team->delete();
        });
    }

    /**
     * Get team members.
     *
     * @return Collection<int, TeamMember>
     */
    public function members(string $teamId): Collection
    {
        return TeamMember::where('team_id', $teamId)
            ->with('user')
            ->get();
    }

    /**
     * Add a member to the team.
     */
    public function addMember(string $teamId, string $userId, string $role = 'member'): TeamMember
    {
        $existing = TeamMember::where('team_id', $teamId)
            ->where('user_id', $userId)
            ->first();

        if ($existing) {
            throw ValidationException::withMessages(['user_id' => 'User is already a member.']);
        }

        $team = Team::findOrFail($teamId);

        if ($team->max_members && $team->member_count >= $team->max_members) {
            throw ValidationException::withMessages(['team' => 'Team has reached maximum member limit.']);
        }

        return DB::transaction(function () use ($teamId, $userId, $role, $team): TeamMember {
            $member = TeamMember::create([
                'team_id' => $teamId,
                'user_id' => $userId,
                'role_key' => $role,
                'joined_at' => now(),
            ]);

            $team->increment('member_count');

            return $member;
        });
    }

    /**
     * Remove a member from the team.
     */
    public function removeMember(string $teamId, string $userId, string $actorId): void
    {
        $team = Team::findOrFail($teamId);

        $actorRole = $this->getMemberRole($teamId, $actorId);
        $targetRole = $this->getMemberRole($teamId, $userId);

        // Owner can remove anyone; admin can only remove member role
        if ($actorRole !== 'owner') {
            if ($actorRole !== 'admin' || $targetRole !== 'member') {
                throw ValidationException::withMessages(['permission' => 'You do not have permission to remove this member.']);
            }
        }

        if ($targetRole === 'owner') {
            throw ValidationException::withMessages(['user_id' => 'Cannot remove the team owner.']);
        }

        DB::transaction(function () use ($teamId, $userId, $team): void {
            TeamMember::where('team_id', $teamId)
                ->where('user_id', $userId)
                ->delete();

            $team->decrement('member_count');
        });
    }

    /**
     * Switch user's current team context.
     */
    public function switchTeam(string $teamId, string $userId): void
    {
        $this->assertMember($teamId, $userId);
        User::whereKey($userId)->update(['current_team_id' => $teamId]);
    }

    /**
     * Invite a user to the team.
     *
     * @param  array{email: string, role?: string}  $data
     */
    public function invite(string $teamId, array $data, string $invitedBy): TeamInvitation
    {
        $team = Team::findOrFail($teamId);
        $this->assertRole($teamId, $invitedBy, ['owner', 'admin']);

        $email = strtolower($data['email']);
        $role = $data['role'] ?? 'member';

        // Check if user already invited
        $pending = TeamInvitation::where('team_id', $teamId)
            ->where('email', $email)
            ->whereNull('accepted_at')
            ->whereNull('declined_at')
            ->where('expires_at', '>', now())
            ->first();

        if ($pending) {
            throw ValidationException::withMessages(['email' => 'An active invitation already exists for this email.']);
        }

        $token = bin2hex(random_bytes(32));

        return TeamInvitation::create([
            'team_id' => $teamId,
            'email' => $email,
            'role_key' => $role,
            'token_hash' => Hash::make($token),
            'invited_by' => $invitedBy,
            'expires_at' => now()->addDays(7),
        ]);
    }

    /**
     * Accept a team invitation.
     */
    public function acceptInvite(string $token, string $userId): void
    {
        $user = User::findOrFail($userId);
        $invitations = TeamInvitation::where('email', $user->email)
            ->whereNull('accepted_at')
            ->whereNull('declined_at')
            ->where('expires_at', '>', now())
            ->get();

        foreach ($invitations as $invitation) {
            if (Hash::check($token, $invitation->token_hash)) {
                DB::transaction(function () use ($invitation, $userId): void {
                    $this->addMember($invitation->team_id, $userId, $invitation->role);
                    $invitation->update(['accepted_at' => now()]);
                });

                return;
            }
        }

        throw ValidationException::withMessages(['token' => 'Invalid or expired invitation token.']);
    }

    /**
     * List invitations for a team.
     *
     * @return Collection<int, TeamInvitation>
     */
    public function listInvitations(string $teamId): Collection
    {
        return TeamInvitation::where('team_id', $teamId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Cancel a pending invitation. Requires owner or admin role.
     */
    public function cancelInvitation(string $teamId, string $invitationId, string $actorId): void
    {
        $this->assertRole($teamId, $actorId, ['owner', 'admin']);

        $invitation = TeamInvitation::where('team_id', $teamId)
            ->where('id', $invitationId)
            ->whereNull('accepted_at')
            ->firstOrFail();

        $invitation->delete();
    }

    /**
     * Get pending invitations for a user by email.
     *
     * @return Collection<int, TeamInvitation>
     */
    public function pendingInvitations(string $email): Collection
    {
        return TeamInvitation::where('email', $email)
            ->whereNull('accepted_at')
            ->whereNull('declined_at')
            ->where('expires_at', '>', now())
            ->with('team')
            ->get();
    }

    /**
     * Assert the user is a member of the team.
     */
    private function assertMember(string $teamId, string $userId): void
    {
        $exists = TeamMember::where('team_id', $teamId)
            ->where('user_id', $userId)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages(['team' => 'You are not a member of this team.']);
        }
    }

    /**
     * Assert the user has one of the required roles.
     *
     * @param  string[]  $roles
     */
    private function assertRole(string $teamId, string $userId, array $roles): void
    {
        $memberRole = $this->getMemberRole($teamId, $userId);

        if (! in_array($memberRole, $roles, true)) {
            throw ValidationException::withMessages(['permission' => 'You do not have permission to perform this action.']);
        }
    }

    /**
     * Update a member's role (owner only). Cannot change owner.
     *
     * @param  string  $role  One of 'admin', 'member'.
     */
    public function updateMemberRole(string $teamId, string $userId, string $role, string $actorId): void
    {
        Team::findOrFail($teamId);
        $this->assertRole($teamId, $actorId, ['owner']);

        if (! in_array($role, ['admin', 'member'], true)) {
            throw ValidationException::withMessages(['role' => 'Invalid role.']);
        }

        $currentRole = $this->getMemberRole($teamId, $userId);
        if ($currentRole === null) {
            throw ValidationException::withMessages(['user_id' => 'User is not a member of this team.']);
        }

        if ($currentRole === 'owner') {
            throw ValidationException::withMessages(['user_id' => 'Cannot change the role of the team owner.']);
        }

        DB::transaction(function () use ($teamId, $userId, $role): void {
            TeamMember::where('team_id', $teamId)
                ->where('user_id', $userId)
                ->update(['role_key' => $role]);
        });
    }

    /**
     * Batch cancel pending invitations (owner/admin only).
     *
     * @param  string[]  $invitationIds
     * @return int  Number of cancelled invitations.
     */
    public function batchCancelInvitations(string $teamId, array $invitationIds, string $actorId): int
    {
        $this->assertRole($teamId, $actorId, ['owner', 'admin']);

        if (empty($invitationIds)) {
            return 0;
        }

        return TeamInvitation::where('team_id', $teamId)
            ->whereIn('id', $invitationIds)
            ->whereNull('accepted_at')
            ->delete();
    }

    /**
     * Leave a team (member / admin only; owner must transfer ownership first).
     */
    public function leaveTeam(string $teamId, string $userId): void
    {
        $team = Team::findOrFail($teamId);
        $role = $this->getMemberRole($teamId, $userId);

        if ($role === null) {
            throw ValidationException::withMessages(['team' => 'You are not a member of this team.']);
        }

        if ($role === 'owner') {
            throw ValidationException::withMessages(['team' => 'Owner must transfer ownership before leaving.']);
        }

        DB::transaction(function () use ($teamId, $userId, $team): void {
            TeamMember::where('team_id', $teamId)
                ->where('user_id', $userId)
                ->delete();

            $team->decrement('member_count');
        });
    }

    /**
     * Transfer team ownership to another member.
     */
    public function transferOwnership(string $teamId, string $newOwnerId, string $actorId): void
    {
        $this->assertRole($teamId, $actorId, ['owner']);

        if ($actorId === $newOwnerId) {
            throw ValidationException::withMessages(['user_id' => 'You are already the owner.']);
        }

        $newOwnerRole = $this->getMemberRole($teamId, $newOwnerId);
        if ($newOwnerRole === null) {
            throw ValidationException::withMessages(['user_id' => 'Target user is not a member of this team.']);
        }

        DB::transaction(function () use ($teamId, $actorId, $newOwnerId): void {
            TeamMember::where('team_id', $teamId)
                ->where('user_id', $actorId)
                ->update(['role_key' => 'admin']);
            TeamMember::where('team_id', $teamId)
                ->where('user_id', $newOwnerId)
                ->update(['role_key' => 'owner']);
            Team::where('id', $teamId)->update(['owner_id' => $newOwnerId]);
        });
    }

    /**
     * Get a user's role in a team.
     */
    private function getMemberRole(string $teamId, string $userId): ?string
    {
        return TeamMember::where('team_id', $teamId)
            ->where('user_id', $userId)
            ->value('role_key');
    }
}
