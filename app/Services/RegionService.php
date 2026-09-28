<?php

namespace App\Services;

use App\Models\Church;
use App\Models\Ministry;
use App\Models\Region;
use App\Models\SubZone;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Pagination\LengthAwarePaginator;

class RegionService
{
    /**
     * Return a paginated list of regions (Ministry-wide entity, so no
     * role-based row filtering beyond Ministry Admin access, enforced
     * at the controller/middleware level).
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

        return $query->orderBy('name')->paginate($perPage);
    }

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
}
