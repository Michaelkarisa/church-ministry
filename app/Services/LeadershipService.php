<?php

namespace App\Services;

use App\Models\Church;
use App\Models\Leadership;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class LeadershipService
{
    /**
     * Return a paginated, role-scoped list of leadership records.
     *
     * Accepted filters: church_id, role, is_active (bool|null)
     */
    public function index(User $user, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = Leadership::with('church')
            ->when($filters['church_id'] ?? null, fn ($q, $v) => $q->where('church_id', $v))
            ->when($filters['role'] ?? null, fn ($q, $v) => $q->where('role', 'like', "%{$v}%"))
            ->when(isset($filters['is_active']), fn ($q) => $q->where('is_active', $filters['is_active']));

        $this->scopeToUser($query, $user);

        return $query->orderByDesc('is_primary')->orderBy('name')->paginate($perPage);
    }

    /** All leaders for one church — the common "get leaders of a specific church" query. */
    public function forChurch(Church $church, bool $activeOnly = true)
    {
        return $church->leadership()
            ->when($activeOnly, fn ($q) => $q->where('is_active', true))
            ->orderByDesc('is_primary')
            ->orderBy('name')
            ->get();
    }

    public function store(array $data): Leadership
    {
        if ($data['is_primary'] ?? false) {
            $this->clearExistingPrimary($data['church_id']);
        }

        return Leadership::create($data);
    }

    public function update(Leadership $leadership, array $data): Leadership
    {
        if ($data['is_primary'] ?? false) {
            $this->clearExistingPrimary($leadership->church_id, $leadership->id);
        }

        $leadership->update($data);
        return $leadership->fresh();
    }

    public function destroy(Leadership $leadership): void
    {
        $leadership->delete();
    }

    public function canAccess(User $user, Leadership $leadership): bool
    {
        if ($user->isMinistryAdmin()) {
            return true;
        }

        if ($user->isZoneAdmin()) {
            return $leadership->church?->zone_id === $user->zone_id;
        }

        return $leadership->church_id === $user->church_id;
    }

    private function scopeToUser($query, User $user): void
    {
        if ($user->isZoneAdmin()) {
            $query->whereHas('church.subZone', fn ($q) => $q->where('zone_id', $user->zone_id));
        } elseif ($user->isChurchAdmin()) {
            $query->where('church_id', $user->church_id);
        }
    }

    /** Only one leader per church may be flagged primary at a time. */
    private function clearExistingPrimary(string $churchId, ?string $exceptId = null): void
    {
        Leadership::where('church_id', $churchId)
            ->where('is_primary', true)
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->update(['is_primary' => false]);
    }
}
