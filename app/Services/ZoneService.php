<?php

namespace App\Services;

use App\Models\Church;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Pagination\LengthAwarePaginator;

class ZoneService
{
    /**
     * Return a paginated, role-scoped list of zones.
     *
     * Accepted filters: search, region_id, is_active (bool|null)
     */
    public function index(User $user, array $filters, int $perPage): LengthAwarePaginator
    {
        // churches() is a scoped query (not a direct relation, since
        // churches now sit two hops down via Sub-zone), so its count is
        // added as a correlated subquery rather than withCount().
        $query = Zone::withCount('subZones')
            ->addSelect(['churches_count' => Church::selectRaw('count(*)')
                ->join('sub_zones', 'churches.sub_zone_id', '=', 'sub_zones.id')
                ->whereColumn('sub_zones.zone_id', 'zones.id'),
            ])
            ->when($filters['search'] ?? null, fn ($q, $v) =>
                $q->where('name', 'like', "%{$v}%")
                  ->orWhere('code', 'like', "%{$v}%")
            )
            ->when($filters['region_id'] ?? null, fn ($q, $v) => $q->where('region_id', $v))
            ->when(isset($filters['is_active']), fn ($q) => $q->where('is_active', $filters['is_active']));

        // Zone admins only see their own zone
        if ($user->isZoneAdmin()) {
            $query->where('id', $user->zone_id);
        }

        return $query->orderBy('name')->paginate($perPage);
    }

    /**
     * Create a zone under the given region.
     */
    public function store(array $data): Zone
    {
        return Zone::create($data);
    }

    /**
     * Load a zone with its sub-zones, each sub-zone's churches, and
     * each church's active-member count.
     */
    public function show(Zone $zone): Zone
    {
        return $zone->load(['region', 'subZones.churches' => fn ($q) =>
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
     * Throws \DomainException(422) when sub-zones (and therefore churches) still belong to it.
     */
    public function destroy(Zone $zone): void
    {
        if ($zone->subZones()->exists()) {
            throw new \DomainException(
                'Cannot delete a zone with existing sub-zones. Reassign or delete the sub-zones first.',
                422
            );
        }

        $zone->delete();
    }
}
