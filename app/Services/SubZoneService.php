<?php

namespace App\Services;

use App\Models\SubZone;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class SubZoneService
{
    /**
     * Return a paginated, role-scoped list of sub-zones.
     *
     * Accepted filters: search, zone_id, is_active (bool|null)
     */
    public function index(User $user, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = SubZone::withCount('churches')
            ->when($filters['search'] ?? null, fn ($q, $v) =>
                $q->where('name', 'like', "%{$v}%")
                  ->orWhere('code', 'like', "%{$v}%")
            )
            ->when($filters['zone_id'] ?? null, fn ($q, $v) => $q->where('zone_id', $v))
            ->when(isset($filters['is_active']), fn ($q) => $q->where('is_active', $filters['is_active']));

        // Zone admins only see sub-zones within their own zone
        if ($user->isZoneAdmin()) {
            $query->where('zone_id', $user->zone_id);
        }

        return $query->orderBy('name')->paginate($perPage);
    }

    public function store(array $data): SubZone
    {
        return SubZone::create($data);
    }

    public function show(SubZone $subZone): SubZone
    {
        return $subZone->load(['zone.region', 'churches' => fn ($q) =>
            $q->withCount(['members' => fn ($m) => $m->where('is_active', true)])
        ]);
    }

    public function update(SubZone $subZone, array $data): SubZone
    {
        $subZone->update($data);
        return $subZone->fresh();
    }

    /**
     * Delete a sub-zone.
     * Throws \DomainException(422) when churches still belong to it.
     */
    public function destroy(SubZone $subZone): void
    {
        if ($subZone->churches()->exists()) {
            throw new \DomainException(
                'Cannot delete a sub-zone with existing churches. Reassign or delete the churches first.',
                422
            );
        }

        $subZone->delete();
    }

    /**
     * Whether the given user may access this sub-zone.
     */
    public function canAccess(User $user, SubZone $subZone): bool
    {
        if ($user->isMinistryAdmin()) {
            return true;
        }

        if ($user->isZoneAdmin()) {
            return $subZone->zone_id === $user->zone_id;
        }

        // Church admins may view (not manage) the sub-zone their church sits in
        return $subZone->id === $user->church?->sub_zone_id;
    }
}
