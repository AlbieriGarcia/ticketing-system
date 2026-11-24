<?php

namespace App\Http\Controllers;

use App\Http\Requests\Event\ChangeEventStatusRequest;
use App\Http\Requests\Event\ListEventsRequest;
use App\Http\Requests\Event\StoreEventRequest;
use App\Http\Requests\Event\UpdateEventRequest;
use App\Http\Resources\EventResource;
use App\Models\Event;
use App\Services\EventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EventController extends Controller
{
    public function __construct(private readonly EventService $eventService)
    {
    }

    public function index(ListEventsRequest $request): AnonymousResourceCollection
    {
        $events = $this->eventService->list($request->validated(), $request->user('sanctum'));

        return EventResource::collection($events);
    }

    public function show(Event $event): EventResource
    {
        $this->ensureVisible($event);

        return new EventResource($event);
    }

    public function availability(Event $event): JsonResponse
    {
        $this->ensureVisible($event);

        return response()->json([
            'event_id' => $event->id,
            'total_tickets' => $event->total_tickets,
            'available_tickets' => $event->available_tickets,
            'is_sold_out' => $event->available_tickets === 0,
        ]);
    }

    public function store(StoreEventRequest $request): JsonResponse
    {
        $event = $this->eventService->create($request->validated());

        return (new EventResource($event))->response()->setStatusCode(201);
    }

    public function update(UpdateEventRequest $request, Event $event): EventResource
    {
        return new EventResource($this->eventService->update($event, $request->validated()));
    }

    public function changeStatus(ChangeEventStatusRequest $request, Event $event): EventResource
    {
        return new EventResource($this->eventService->changeStatus($event, $request->status()));
    }

    // Un evento no publicado responde 404 a quien no es admin, para no revelar su existencia
    private function ensureVisible(Event $event): void
    {
        abort_unless($event->isVisibleTo(request()->user('sanctum')), 404);
    }
}
