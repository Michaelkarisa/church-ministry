<?php

namespace App\Services;

use App\Models\Ministry;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Pagination\LengthAwarePaginator;

class ZoneService
{
    /**
     * Return a paginated, role-scoped list of zones.
     *
     * Accepted filters: search, is_active (bool|null)
     */
    public function index(User $user, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = Zone::withCount('churches')
            ->when($filters['search'] ?? null, fn ($q, $v) =>
                $q->where('name', 'like', "%{$v}%")
                  ->orWhere('code', 'like', "%{$v}%")
            )
            ->when(isset($filters['is_active']), fn ($q) => $q->where('is_active', $filters['is_active']));

        // Zone admins only see their own zone
        if ($user->isZoneAdmin()) {
            $query->where('id', $user->zone_id);
        }

        return $query->orderBy('name')->paginate($perPage);
    }

    /**
     * Create a zone, automatically assigning the current ministry.
     */
    public function store(array $data): Zone
    {
        return Zone::create(array_merge($data, ['ministry_id' => Ministry::currentId()]));
    }

    /**
     * Load a zone with its churches and each church's active-member count.
     */
    public function show(Zone $zone): Zone
    {
        return $zone->load(['churches' => fn ($q) =>
            $q->withCount(['members' => fn ($m) => $m->where('is_active', true)])
        ]);
    }

    /**
     * Update zone fields and return the refreshed model.
     */
    public function update(Zone $zone, array $data): Zone
    {
        $zone->update($data);
        return $zone->fresh();
    }

    /**
     * Delete a zone.
     * Throws \DomainException(422) when churches still belong to it.
     */
    public function destroy(Zone $zone): void
    {
        if ($zone->churches()->exists()) {
            throw new \DomainException(
                'Cannot delete a zone with existing churches. Reassign or delete the churches first.',
                422
            );
        }

        $zone->delete();
    }
}
