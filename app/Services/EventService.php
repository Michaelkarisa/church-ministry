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

        $query->whereHas('church', fn ($q) => $q->visibleTo($user));

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
        return $event->church && (new \App\Services\ChurchService())->canAccess($user, $event->church);
    }

    public function contributions(Event $event)
    {
       return $event->contributions();
    }
}
