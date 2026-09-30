<?php

namespace App\Http\Controllers;

use App\Models\Church;
use App\Models\Event;
use App\Services\EventService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventController extends Controller
{
    use ApiResponse;

    public function __construct(private EventService $eventService) {}

    /** GET /api/events */
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->get('per_page', 15), 100);

        $paginator = $this->eventService->index(
            $request->user(),
            [
                'church_id' => $request->church_id,
                'type'      => $request->type,
                'from'      => $request->from,
                'to'        => $request->to,
            ],
            $perPage,
        );

        return $this->successResponse($paginator);
    }

    /** POST /api/events */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'church_id'        => ['required', 'exists:churches,id'],
            // Free-text event type (e.g. "Sunday Service") — not a fixed dropdown.
            'type'             => ['required', 'string', 'max:150'],
            'event_date'       => ['required', 'date', 'before_or_equal:today'],
            'attendance_count' => ['required', 'integer', 'min:0'],
            // Sermon is optional — not every event has one.
            'sermon_topic'     => ['nullable', 'string', 'max:255'],
            'sermon_speaker'   => ['nullable', 'string', 'max:150'],
        ]);

        $user = $request->user();
        $targetChurch = Church::find($data['church_id']);
        if (! $targetChurch || ! (new \App\Services\ChurchService())->canAccess($user, $targetChurch)) {
            return $this->forbiddenResponse('You can only record events for churches in your own branch.');
        }

        $event = $this->eventService->store($user, $data);

        return $this->createdResponse($event, 'Event recorded successfully.');
    }

    /**
     * GET /api/events/{event}
     * Includes any linked contributions — this is the "drill into a
     * specific church/event" financial view, not the general overview.
     */
    public function show(Request $request, Event $event): JsonResponse
    {
        if (! $this->eventService->canAccess($request->user(), $event)) {
            return $this->forbiddenResponse();
        }

        return $this->successResponse($this->eventService->show($event));
    }

    /** PUT /api/events/{event} */
    public function update(Request $request, Event $event): JsonResponse
    {
        if (! $this->eventService->canAccess($request->user(), $event)) {
            return $this->forbiddenResponse();
        }

        $data = $request->validate([
            'type'             => ['sometimes', 'string', 'max:150'],
            'event_date'       => ['sometimes', 'date', 'before_or_equal:today'],
            'attendance_count' => ['sometimes', 'integer', 'min:0'],
            'sermon_topic'     => ['nullable', 'string', 'max:255'],
            'sermon_speaker'   => ['nullable', 'string', 'max:150'],
        ]);

        $event = $this->eventService->update($event, $data);

        return $this->successResponse($event, 'Event updated successfully.');
    }

    /** DELETE /api/events/{event} */
    public function destroy(Request $request, Event $event): JsonResponse
    {
        if (! $this->eventService->canAccess($request->user(), $event)) {
            return $this->forbiddenResponse();
        }

        $this->eventService->destroy($event);

        return $this->noContentResponse('Event deleted successfully.');
    }
}
