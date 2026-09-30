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
     * Accepted filters: search, zone_id, region_id, is_active (bool|null)
     */
    public function index(User $user, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = SubZone::withCount('churches')
            ->when($filters['search'] ?? null, fn ($q, $v) =>
                $q->where('name', 'like', "%{$v}%")
                  ->orWhere('code', 'like', "%{$v}%")
            )
            ->when($filters['zone_id'] ?? null, fn ($q, $v) => $q->where('zone_id', $v))
            ->when($filters['region_id'] ?? null, fn ($q, $v) =>
                $q->whereHas('zone', fn ($sq) => $sq->where('region_id', $v))
            )
            ->when(isset($filters['is_active']), fn ($q) => $q->where('is_active', $filters['is_active']));

        $scope = $user->accessScope();
        match ($scope['level']) {
            'ministry' => null,
            'region'   => $query->whereHas('zone', fn ($q) => $q->where('region_id', $scope['region_id'])),
            'zone'     => $query->where('zone_id', $scope['zone_id']),
            // Sub-zone and church admins only ever see their own sub-zone.
            'sub_zone' => $query->where('id', $scope['sub_zone_id']),
            default    => $query->whereHas('churches', fn ($q) => $q->where('id', $scope['church_id'])),
        };

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
     * Whether the given user may access this sub-zone, per the
     * five-tier hierarchy.
     */
    public function canAccess(User $user, SubZone $subZone): bool
    {
        $scope = $user->accessScope();

        return match ($scope['level']) {
            'ministry' => true,
            'region'   => $subZone->zone?->region_id === $scope['region_id'],
            'zone'     => $subZone->zone_id === $scope['zone_id'],
            'sub_zone' => $subZone->id === $scope['sub_zone_id'],
            // Church admins may view (not manage) the sub-zone their church sits in
            default    => $subZone->id === $user->church?->sub_zone_id,
        };
    }

    /**
     * Whether the given user may create/manage a sub-zone under the
     * given zone (used before the SubZone row exists, e.g. on create).
     */
    public function canManageWithinZone(User $user, string $zoneId): bool
    {
        $scope = $user->accessScope();

        return match ($scope['level']) {
            'ministry' => true,
            'region'   => \App\Models\Zone::where('id', $zoneId)->where('region_id', $scope['region_id'])->exists(),
            'zone'     => $zoneId === $scope['zone_id'],
            default    => false,
        };
    }
}
