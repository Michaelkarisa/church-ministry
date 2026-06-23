<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Church;
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
     * Return all roles ordered by hierarchy level (ministry → zone → church).
     */
    public function listRoles(): Collection
    {
        return Role::orderBy('level')->get();
    }

    /**
     * Return a paginated list of users the actor is authorised to manage.
     *
     * MinistryAdmin → all users except other MinistryAdmins.
     * ZoneAdmin     → ChurchAdmins whose church/zone belongs to the actor's zone.
     *
     * Accepted filters: search, role_id, is_active (bool|null)
     */
    public function listManagedUsers(User $actor, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = User::with(['role', 'zone', 'church'])
            ->when($filters['search'] ?? null, fn ($q, $v) =>
                $q->where('name', 'like', "%{$v}%")
                  ->orWhere('email', 'like', "%{$v}%")
            )
            ->when($filters['role_id'] ?? null, fn ($q, $v) => $q->where('role_id', $v))
            ->when(isset($filters['is_active']), fn ($q) => $q->where('is_active', $filters['is_active']));

        if ($actor->isMinistryAdmin()) {
            // See everyone except fellow ministry admins
            $ministryAdminRole = Role::where('name', Role::MINISTRY_ADMIN)->first();
            if ($ministryAdminRole) {
                $query->where('role_id', '!=', $ministryAdminRole->id)
                      ->orWhereNull('role_id');
            }
            $query->where('id', '!=', $actor->id);
        } else {
            // ZoneAdmin: only ChurchAdmins within their zone
            $churchAdminRole = Role::where('name', Role::CHURCH_ADMIN)->first();
            $churchIds       = Church::where('zone_id', $actor->zone_id)->pluck('id');

            $query->where('role_id', $churchAdminRole?->id)
                  ->where(fn ($q) =>
                      $q->where('zone_id', $actor->zone_id)
                        ->orWhereIn('church_id', $churchIds)
                  );
        }

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
     *  • ZoneAdmin may only assign `church_admin`.
     *  • MinistryAdmin may assign `zone_admin` or `church_admin`.
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

        return $target->fresh()->load(['role', 'zone', 'church']);
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

        return $target->fresh()->load(['zone', 'church']);
    }

    // ---------------------------------------------------------------
    // Public authorization helpers (usable by controllers)
    // ---------------------------------------------------------------

    /**
     * Whether the actor may manage the target user's role at all.
     */
    public function canManageUser(User $actor, User $target): bool
    {
        if ($actor->id === $target->id) {
            return false;
        }

        if ($actor->isMinistryAdmin()) {
            // Cannot touch other ministry admins
            return ! $target->isMinistryAdmin();
        }

        if ($actor->isZoneAdmin()) {
            // Only ChurchAdmins inside the actor's zone
            return $this->isChurchAdminInZone($target, $actor->zone_id);
        }

        return false;
    }

    /**
     * Which roles the actor is permitted to assign.
     * Returns a keyed collection of Role records.
     */
    public function assignableRoles(User $actor): Collection
    {
        if ($actor->isMinistryAdmin()) {
            return Role::whereIn('name', [Role::ZONE_ADMIN, Role::CHURCH_ADMIN])
                       ->orderBy('level')
                       ->get();
        }

        if ($actor->isZoneAdmin()) {
            return Role::where('name', Role::CHURCH_ADMIN)->get();
        }

        return collect();
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

    /**
     * Check whether the given user is a ChurchAdmin whose church (or zone) belongs to $zoneId.
     */
    private function isChurchAdminInZone(User $user, ?string $zoneId): bool
    {
        if (! $user->isChurchAdmin() || is_null($zoneId)) {
            return false;
        }

        // Fast path: zone_id is set directly on the user
        if ($user->zone_id && $user->zone_id === $zoneId) {
            return true;
        }

        // Fallback: resolve through the assigned church
        if ($user->church_id) {
            $church = $user->relationLoaded('church') ? $user->church : Church::find($user->church_id);
            return $church && $church->zone_id === $zoneId;
        }

        return false;
    }
}
