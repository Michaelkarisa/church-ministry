<?php

namespace App\Services;

use App\Models\Church;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Pagination\LengthAwarePaginator;

class ZoneService
{
    /**
     * Return a paginated list of zones, restricted to the user's own
     * branch of the hierarchy.
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

        $scope = $user->accessScope();
        match ($scope['level']) {
            'ministry' => null,
            'region'   => $query->where('region_id', $scope['region_id']),
            'zone'     => $query->where('id', $scope['zone_id']),
            'sub_zone' => $query->whereHas('subZones', fn ($q) => $q->where('id', $scope['sub_zone_id'])),
            default    => $query->whereHas('subZones.churches', fn ($q) => $q->where('id', $scope['church_id'])),
        };

        return $query->orderBy('name')->paginate($perPage);
    }

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

    /**
     * Whether the given user's position in the hierarchy falls
     * anywhere under this zone (used to gate by-id read access).
     */
    public function canAccess(User $user, Zone $zone): bool
    {
        $scope = $user->accessScope();

        return match ($scope['level']) {
            'ministry' => true,
            'region'   => $zone->region_id === $scope['region_id'],
            'zone'     => $zone->id === $scope['zone_id'],
            'sub_zone' => \App\Models\SubZone::where('id', $scope['sub_zone_id'])->where('zone_id', $zone->id)->exists(),
            default    => Church::where('id', $scope['church_id'])
                ->whereHas('subZone', fn ($q) => $q->where('zone_id', $zone->id))->exists(),
        };
    }

    /**
     * Whether the given user may edit this specific zone — its own
     * zone admin, or anyone above them in its branch.
     */
    public function canManage(User $user, Zone $zone): bool
    {
        $scope = $user->accessScope();

        return match ($scope['level']) {
            'ministry' => true,
            'region'   => $zone->region_id === $scope['region_id'],
            'zone'     => $zone->id === $scope['zone_id'],
            default    => false,
        };
    }

    /**
     * Whether the given user may create/delete a zone within the given
     * region — a zone's parent level (region admin and above) manages
     * its lifecycle.
     */
    public function canManageWithinRegion(User $user, string $regionId): bool
    {
        $scope = $user->accessScope();

        return match ($scope['level']) {
            'ministry' => true,
            'region'   => $regionId === $scope['region_id'],
            default    => false,
        };
    }
}
