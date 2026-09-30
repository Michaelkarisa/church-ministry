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
     * Accepted filters: search, sub_zone_id, zone_id, region_id, is_active (bool|null)
     */
    public function index(User $user, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = Church::with('subZone.zone.region')
            ->withCount(['members' => fn ($q) => $q->where('is_active', true)])
            ->when($filters['search'] ?? null, fn ($q, $v) =>
                $q->where('name', 'like', "%{$v}%")
                  ->orWhere('code', 'like', "%{$v}%")
            )
            ->when($filters['sub_zone_id'] ?? null, fn ($q, $v) => $q->where('sub_zone_id', $v))
            // zone_id / region_id are one or two hops up from sub_zone_id,
            // so they need the nested relation.
            ->when($filters['zone_id'] ?? null, fn ($q, $v) =>
                $q->whereHas('subZone', fn ($sq) => $sq->where('zone_id', $v))
            )
            ->when($filters['region_id'] ?? null, fn ($q, $v) =>
                $q->whereHas('subZone.zone', fn ($sq) => $sq->where('region_id', $v))
            )
            ->when(isset($filters['is_active']), fn ($q) => $q->where('is_active', $filters['is_active']))
            ->visibleTo($user);

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
     * Load a church with its full hierarchy chain, leadership, and
     * active-member count. Financial data is deliberately NOT loaded
     * here — it's a separate drill-down (see AnalyticsService::churchSummary).
     */
    public function show(Church $church): Church
    {
        return $church->load(['subZone.zone.region.ministry', 'leadership' => fn ($q) => $q->where('is_active', true)])
                      ->loadCount(['members' => fn ($q) => $q->where('is_active', true)]);
    }

    /**
     * Update church fields and return the refreshed model.
     */
    public function update(Church $church, array $data): Church
    {
        $church->update($data);
        return $church->fresh()->load('subZone.zone.region');
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
     * Whether the given user is allowed to access this church, per the
     * five-tier hierarchy (Ministry -> Region -> Zone -> Sub-zone -> Church).
     */
    public function canAccess(User $user, Church $church): bool
    {
        $scope = $user->accessScope();

        return match ($scope['level']) {
            'ministry' => true,
            'region'   => $church->region_id === $scope['region_id'],
            'zone'     => $church->zone_id === $scope['zone_id'],
            'sub_zone' => $church->sub_zone_id === $scope['sub_zone_id'],
            default    => $church->id === $scope['church_id'],
        };
    }

    /**
     * Whether the given user may create/delete a church within the
     * given sub-zone — a church's parent level (sub-zone admin and
     * above) manages its lifecycle; a plain Church Administrator does not.
     */
    public function canManageWithinSubZone(User $user, string $subZoneId): bool
    {
        $scope = $user->accessScope();

        return match ($scope['level']) {
            'ministry' => true,
            'region'   => \App\Models\SubZone::where('id', $subZoneId)
                ->whereHas('zone', fn ($q) => $q->where('region_id', $scope['region_id']))->exists(),
            'zone'     => \App\Models\SubZone::where('id', $subZoneId)->where('zone_id', $scope['zone_id'])->exists(),
            'sub_zone' => $subZoneId === $scope['sub_zone_id'],
            default    => false,
        };
    }
}
