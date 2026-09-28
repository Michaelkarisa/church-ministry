<?php

namespace App\Services;

use App\Models\Event;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class EventService
{
    /**
     * Return a paginated, role-scoped list of events.
     *
     * Accepted filters: church_id, type (free-text match), from, to
     */
    public function index(User $user, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = Event::with('church')
            ->withCount('contributions')
            ->when($filters['church_id'] ?? null, fn ($q, $v) => $q->where('church_id', $v))
            ->when($filters['type'] ?? null, fn ($q, $v) => $q->where('type', 'like', "%{$v}%"))
            ->when(
                ($filters['from'] ?? null) && ($filters['to'] ?? null),
                fn ($q) => $q->whereBetween('event_date', [$filters['from'], $filters['to']])
            );

        if ($user->isZoneAdmin()) {
            $query->whereHas('church.subZone', fn ($q) => $q->where('zone_id', $user->zone_id));
        } elseif ($user->isChurchAdmin()) {
            $query->where('church_id', $user->church_id);
        }

        return $query->orderByDesc('event_date')->paginate($perPage);
    }

    public function store(User $user, array $data): Event
    {
        $data['recorded_by'] = $user->id;
        return Event::create($data)->load('church');
    }

    /**
     * Load an event with its church and any linked contributions
     * (Transactions). Contributions are only meaningful here when the
     * viewer has drilled into this specific event/church — see
     * canAccess() and the church-financials access pattern generally.
     */
    public function show(Event $event): Event
    {
        return $event->load(['church', 'recorder', 'contributions.transactionType']);
    }

    public function update(Event $event, array $data): Event
    {
        $event->update($data);
        return $event->fresh()->load('church');
    }

    public function destroy(Event $event): void
    {
        $event->delete();
    }

    public function canAccess(User $user, Event $event): bool
    {
        if ($user->isMinistryAdmin()) {
            return true;
        }

        if ($user->isZoneAdmin()) {
            return $event->church?->zone_id === $user->zone_id;
        }

        return $event->church_id === $user->church_id;
    }
}
