<?php

namespace App\Services;

use App\Models\Church;
use App\Models\Ministry;
use App\Models\Region;
use App\Models\SubZone;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class RegionService
{
    /**
     * Return a paginated list of regions, restricted to the slice of
     * the hierarchy the user actually belongs to — a region admin
     * only ever sees their own region, a zone admin only the region
     * their zone sits in, and so on down to church admin.
     *
     * Accepted filters: search, is_active (bool|null)
     */
    public function index(User $user, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = Region::withCount('zones')
            ->addSelect(['sub_zones_count' => SubZone::selectRaw('count(*)')
                ->join('zones', 'sub_zones.zone_id', '=', 'zones.id')
                ->whereColumn('zones.region_id', 'regions.id'),
            ])
            ->addSelect(['churches_count' => Church::selectRaw('count(*)')
                ->join('sub_zones', 'churches.sub_zone_id', '=', 'sub_zones.id')
                ->join('zones', 'sub_zones.zone_id', '=', 'zones.id')
                ->whereColumn('zones.region_id', 'regions.id'),
            ])
            ->when($filters['search'] ?? null, fn ($q, $v) =>
                $q->where('name', 'like', "%{$v}%")
                  ->orWhere('code', 'like', "%{$v}%")
            )
            ->when(isset($filters['is_active']), fn ($q) => $q->where('is_active', $filters['is_active']));

        $scope = $user->accessScope();
        match ($scope['level']) {
            'ministry' => null,
            'region'   => $query->where('id', $scope['region_id']),
            'zone'     => $query->whereHas('zones', fn ($q) => $q->where('id', $scope['zone_id'])),
            'sub_zone' => $query->whereHas('zones.subZones', fn ($q) => $q->where('id', $scope['sub_zone_id'])),
            default    => $query->whereHas('zones.subZones.churches', fn ($q) => $q->where('id', $scope['church_id'])),
        };

        return $query->orderBy('name')->paginate($perPage);
    }

    /** Only Ministry Admin creates new regions — see canManage(). */
    public function store(array $data): Region
    {
        return Region::create(array_merge($data, ['ministry_id' => Ministry::currentId()]));
    }

    public function show(Region $region): Region
    {
        return $region->load(['zones' => fn ($q) => $q->withCount('subZones')]);
    }

    public function update(Region $region, array $data): Region
    {
        $region->update($data);
        return $region->fresh();
    }

    /**
     * Delete a region.
     * Throws \DomainException(422) when zones still belong to it.
     */
    public function destroy(Region $region): void
    {
        if ($region->zones()->exists()) {
            throw new \DomainException(
                'Cannot delete a region with existing zones. Reassign or delete the zones first.',
                422
            );
        }

        $region->delete();
    }

    /**
     * Whether the given user may edit/delete this specific region.
     * Creating a brand-new region is Ministry Admin only; a Region
     * Admin may only manage the one region they were assigned to.
     */
    public function canManage(User $user, Region $region): bool
    {
        if ($user->isMinistryAdmin()) return true;
        if ($user->isRegionAdmin()) return $region->id === $user->region_id;
        return false;
    }

    /**
     * Whether the given user's position in the hierarchy falls
     * anywhere under this region (used to gate direct by-id access;
     * index() already filters lists the same way).
     */
    public function canView(User $user, Region $region): bool
    {
        $scope = $user->accessScope();

        return match ($scope['level']) {
            'ministry' => true,
            'region'   => $region->id === $scope['region_id'],
            'zone'     => \App\Models\Zone::where('id', $scope['zone_id'])->where('region_id', $region->id)->exists(),
            'sub_zone' => \App\Models\SubZone::where('id', $scope['sub_zone_id'])
                ->whereHas('zone', fn ($q) => $q->where('region_id', $region->id))->exists(),
            default    => Church::where('id', $scope['church_id'])
                ->whereHas('subZone.zone', fn ($q) => $q->where('region_id', $region->id))->exists(),
        };
    }
}
