<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class RoleService
{
    // ---------------------------------------------------------------
    // Queries
    // ---------------------------------------------------------------

    /**
     * Return all roles ordered by hierarchy level
     * (ministry -> region -> zone -> sub-zone -> church).
     */
    public function listRoles(): Collection
    {
        return Role::orderBy('level')->get();
    }

    /**
     * Return a paginated list of users the actor is authorised to manage.
     *
     * MinistryAdmin -> everyone except other MinistryAdmins.
     * Any other admin -> only users whose role sits strictly below
     * theirs AND whose own assignment falls within the actor's branch
     * of the hierarchy (see User::resolved*Id()).
     *
     * Accepted filters: search, role_id, is_active (bool|null)
     */
    public function listManagedUsers(User $actor, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = User::with(['role', 'region', 'zone', 'subZone', 'church'])
            ->when($filters['search'] ?? null, fn ($q, $v) =>
                $q->where('name', 'like', "%{$v}%")
                  ->orWhere('email', 'like', "%{$v}%")
            )
            ->when($filters['role_id'] ?? null, fn ($q, $v) => $q->where('role_id', $v))
            ->when(isset($filters['is_active']), fn ($q) => $q->where('is_active', $filters['is_active']));

        if ($actor->isMinistryAdmin()) {
            $ministryAdminRole = Role::where('name', Role::MINISTRY_ADMIN)->first();
            if ($ministryAdminRole) {
                $query->where('role_id', '!=', $ministryAdminRole->id)
                      ->orWhereNull('role_id');
            }
            $query->where('id', '!=', $actor->id);

            return $query->orderBy('name')->paginate($perPage);
        }

        $actorLevel = Role::LEVELS[$actor->role?->name] ?? 0;
        $manageableRoleIds = Role::where('level', '>', $actorLevel)->pluck('id');
        $scope = $actor->accessScope();

        $query->whereIn('role_id', $manageableRoleIds)
              ->where(function ($q) use ($scope) {
                  match ($scope['level']) {
                      'region' => $q->where('region_id', $scope['region_id'])
                          ->orWhereHas('zone', fn ($z) => $z->where('region_id', $scope['region_id']))
                          ->orWhereHas('subZone.zone', fn ($z) => $z->where('region_id', $scope['region_id']))
                          ->orWhereHas('church.subZone.zone', fn ($z) => $z->where('region_id', $scope['region_id'])),
                      'zone' => $q->where('zone_id', $scope['zone_id'])
                          ->orWhereHas('subZone', fn ($z) => $z->where('zone_id', $scope['zone_id']))
                          ->orWhereHas('church.subZone', fn ($z) => $z->where('zone_id', $scope['zone_id'])),
                      'sub_zone' => $q->where('sub_zone_id', $scope['sub_zone_id'])
                          ->orWhereHas('church', fn ($z) => $z->where('sub_zone_id', $scope['sub_zone_id'])),
                      default => $q->whereRaw('1 = 0'), // church admins manage no one
                  };
              });

        return $query->orderBy('name')->paginate($perPage);
    }

    // ---------------------------------------------------------------
    // Role operations
    // ---------------------------------------------------------------

    /**
     * Assign a new role to the target user.
     *
     * Rules enforced:
     *  • Actor cannot change their own role.
     *  • Actor cannot manage a peer or superior.
     *  • An actor may only assign a role strictly below their own
     *    level, to a user within their own branch of the hierarchy.
     *  • Role-switching revokes all active tokens immediately.
     *
     * Throws \DomainException on any rule violation.
     */
    public function assignRole(User $actor, User $target, string $roleId): User
    {
        $role = Role::findOrFail($roleId);

        $this->guardCanManage($actor, $target, 'manage');
        $this->guardCanAssignRole($actor, $role);

        $roleChanged = $target->role_id !== $role->id;

        $old = ['role_id' => $target->role_id, 'role_name' => $target->role?->name];

        $target->update(['role_id' => $role->id]);

        if ($roleChanged) {
            $target->tokens()->delete();
        }

        ActivityLog::record(
            $actor,
            'role_assigned',
            'system',
            "Assigned role '{$role->display_name}' to user {$target->email}.",
            [
                'record_id'   => $target->id,
                'record_type' => 'User',
                'old_values'  => $old,
                'new_values'  => ['role_id' => $role->id, 'role_name' => $role->name],
            ]
        );

        return $target->fresh()->load(['role', 'region', 'zone', 'subZone', 'church']);
    }

    /**
     * Revoke the target user's role (sets role_id to null).
     * Immediately invalidates all of the user's active tokens.
     *
     * Throws \DomainException on any authorization rule violation.
     */
    public function revokeRole(User $actor, User $target): User
    {
        $this->guardCanManage($actor, $target, 'revoke');

        if (is_null($target->role_id)) {
            throw new \DomainException('This user has no role assigned — nothing to revoke.', 422);
        }

        $old = ['role_id' => $target->role_id, 'role_name' => $target->role?->name];

        // Immediately kill all sessions — user loses access right away
        $target->tokens()->delete();

        $target->update(['role_id' => null]);

        ActivityLog::record(
            $actor,
            'role_revoked',
            'system',
            "Revoked role '{$old['role_name']}' from user {$target->email}.",
            [
                'record_id'   => $target->id,
                'record_type' => 'User',
                'old_values'  => $old,
                'new_values'  => ['role_id' => null, 'role_name' => null],
            ]
        );

        return $target->fresh()->load(['region', 'zone', 'subZone', 'church']);
    }

    // ---------------------------------------------------------------
    // Public authorization helpers (usable by controllers)
    // ---------------------------------------------------------------

    /**
     * Whether the actor may manage the target user's role at all.
     * Generalizes across all five tiers: an actor may manage any user
     * whose role sits strictly below theirs AND whose own assignment
     * resolves into the actor's branch of the hierarchy.
     */
    public function canManageUser(User $actor, User $target): bool
    {
        if ($actor->id === $target->id) {
            return false;
        }

        if ($actor->isMinistryAdmin()) {
            return ! $target->isMinistryAdmin();
        }

        $actorLevel  = Role::LEVELS[$actor->role?->name] ?? 0;
        $targetLevel = Role::LEVELS[$target->role?->name] ?? 99;

        if ($targetLevel <= $actorLevel) {
            return false;
        }

        $scope = $actor->accessScope();

        return match ($scope['level']) {
            'region'   => $target->resolvedRegionId() === $scope['region_id'],
            'zone'     => $target->resolvedZoneId() === $scope['zone_id'],
            'sub_zone' => $target->resolvedSubZoneId() === $scope['sub_zone_id'],
            default    => false, // church admins manage no one
        };
    }

    /**
     * Which roles the actor is permitted to assign — any role strictly
     * below their own level in the hierarchy.
     * Returns a keyed collection of Role records.
     */
    public function assignableRoles(User $actor): Collection
    {
        $actorLevel = Role::LEVELS[$actor->role?->name] ?? 0;

        if ($actorLevel === 0) {
            return collect();
        }

        return Role::where('level', '>', $actorLevel)->orderBy('level')->get();
    }

    // ---------------------------------------------------------------
    // Private guards
    // ---------------------------------------------------------------

    /**
     * Throw \DomainException if the actor cannot manage the target.
     */
    private function guardCanManage(User $actor, User $target, string $action): void
    {
        if ($actor->id === $target->id) {
            throw new \DomainException("You cannot {$action} your own role.", 422);
        }

        if (! $this->canManageUser($actor, $target)) {
            throw new \DomainException(
                'You do not have permission to manage this user\'s role.',
                403
            );
        }
    }

    /**
     * Throw \DomainException if the actor cannot assign this specific role.
     */
    private function guardCanAssignRole(User $actor, Role $role): void
    {
        $assignable = $this->assignableRoles($actor)->pluck('id');

        if (! $assignable->contains($role->id)) {
            throw new \DomainException(
                "You are not permitted to assign the '{$role->display_name}' role.",
                403
            );
        }
    }
}
