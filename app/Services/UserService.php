<?php

namespace App\Services;

use App\Models\Ministry;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class UserService
{
    /**
     * Return a paginated list of users.
     *
     * Accepted filters: search, role_id, zone_id, church_id, is_active (bool|null)
     */
    public function index(array $filters, int $perPage): LengthAwarePaginator
    {
        return User::with('role')
            ->when($filters['search'] ?? null, fn ($q, $v) =>
                $q->where('name', 'like', "%{$v}%")
                  ->orWhere('email', 'like', "%{$v}%")
            )
            ->when($filters['role_id'] ?? null,   fn ($q, $v) => $q->where('role_id',   $v))
            ->when($filters['zone_id'] ?? null,   fn ($q, $v) => $q->where('zone_id',   $v))
            ->when($filters['church_id'] ?? null, fn ($q, $v) => $q->where('church_id', $v))
            ->when(isset($filters['is_active']), fn ($q) => $q->where('is_active', $filters['is_active']))
            ->orderBy('name')
            ->paginate($perPage);
    }

    /**
     * Create a user, automatically assigning the current ministry.
     */
    public function store(array $data): User
    {
        $data['ministry_id'] = Ministry::currentId();
        return User::create($data);
    }

    /**
     * Load a user with role, zone, and church relationships.
     */
    public function show(User $user): User
    {
        return $user->load(['role', 'zone', 'church']);
    }

    /**
     * Update a user's fields and return the refreshed model.
     * The ministry_id is stripped even if supplied — it is immutable.
     */
    public function update(User $user, array $data): User
    {
        unset($data['ministry_id']);
        $user->update($data);
        return $user->fresh()->load('role');
    }

    /**
     * Delete a user and revoke all their tokens.
     * Throws \DomainException(422) when the actor tries to delete themselves.
     */
    public function destroy(User $user, User $actor): void
    {
        if ($user->id === $actor->id) {
            throw new \DomainException('You cannot delete your own account.', 422);
        }

        $user->tokens()->delete();
        $user->delete();
    }

    /**
     * Toggle the user's active status.
     * Revokes all tokens when deactivating.
     */
    public function toggleStatus(User $user): User
    {
        $user->update(['is_active' => ! $user->is_active]);

        if (! $user->is_active) {
            $user->tokens()->delete();
        }

        return $user->fresh();
    }
}
