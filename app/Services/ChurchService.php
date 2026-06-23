<?php

namespace App\Services;

use App\Models\Church;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class ChurchService
{
    /**
     * Return a paginated, role-scoped list of churches.
     *
     * Accepted filters: search, zone_id, is_active (bool|null)
     */
    public function index(User $user, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = Church::with('zone')
            ->withCount(['members' => fn ($q) => $q->where('is_active', true)])
            ->when($filters['search'] ?? null, fn ($q, $v) =>
                $q->where('name', 'like', "%{$v}%")
                  ->orWhere('code', 'like', "%{$v}%")
            )
            ->when($filters['zone_id'] ?? null, fn ($q, $v) => $q->where('zone_id', $v))
            ->when(isset($filters['is_active']), fn ($q) => $q->where('is_active', $filters['is_active']));

        if ($user->isZoneAdmin()) {
            $query->where('zone_id', $user->zone_id);
        } elseif ($user->isChurchAdmin()) {
            $query->where('id', $user->church_id);
        }

        return $query->orderBy('name')->paginate($perPage);
    }

    /**
     * Create a church from validated data.
     */
    public function store(array $data): Church
    {
        return Church::create($data);
    }

    /**
     * Load a church with zone and active-member count.
     */
    public function show(Church $church): Church
    {
        return $church->load(['zone.ministry'])
                      ->loadCount(['members' => fn ($q) => $q->where('is_active', true)]);
    }

    /**
     * Update church fields and return the refreshed model.
     */
    public function update(Church $church, array $data): Church
    {
        $church->update($data);
        return $church->fresh()->load('zone');
    }

    /**
     * Delete a church.
     * Throws \DomainException(422) when members or transactions still reference it.
     */
    public function destroy(Church $church): void
    {
        if ($church->members()->exists() || $church->transactions()->exists()) {
            throw new \DomainException(
                'Cannot delete a church with existing members or financial records.',
                422
            );
        }

        $church->delete();
    }

    /**
     * Whether the given user is allowed to access this church.
     */
    public function canAccess(User $user, Church $church): bool
    {
        if ($user->isMinistryAdmin()) {
            return true;
        }

        if ($user->isZoneAdmin()) {
            return $church->zone_id === $user->zone_id;
        }

        return $church->id === $user->church_id;
    }
}
