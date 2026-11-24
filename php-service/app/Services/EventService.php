<?php

namespace App\Services;

use App\Enums\EventStatus;
use App\Exceptions\EventNotEditableException;
use App\Exceptions\InvalidEventDataException;
use App\Exceptions\InvalidStatusTransitionException;
use App\Models\Event;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class EventService
{
    public function list(array $filters, ?User $viewer): LengthAwarePaginator
    {
        $query = Event::query();

        if ($viewer?->isAdmin()) {
            $query->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status));
        } else {
            $query->published();
        }

        return $query
            ->when($filters['search'] ?? null, fn ($q, $search) => $q->where('name', 'ilike', "%{$search}%"))
            ->when($filters['from'] ?? null, fn ($q, $from) => $q->where('event_date', '>=', $from))
            ->when($filters['to'] ?? null, fn ($q, $to) => $q->where('event_date', '<=', $to))
            ->orderBy('event_date')
            ->paginate($filters['per_page'] ?? 15);
    }

    public function create(array $data): Event
    {
        return Event::create([...$data, 'available_tickets' => $data['total_tickets']]);
    }

    public function update(Event $event, array $data): Event
    {
        return DB::transaction(function () use ($event, $data) {
            // Bloqueo la fila: una compra concurrente no puede cambiar la disponibilidad mientras recalculo
            $event = Event::whereKey($event->id)->lockForUpdate()->firstOrFail();

            if (! $event->status->isEditable()) {
                throw new EventNotEditableException($event->status);
            }

            if (isset($data['total_tickets'])) {
                $committed = $event->committedTickets();

                if ($data['total_tickets'] < $committed) {
                    throw InvalidEventDataException::totalBelowCommitted($committed);
                }

                $data['available_tickets'] = $data['total_tickets'] - $committed;
            }

            $event->update($data);

            return $event;
        });
    }

    public function changeStatus(Event $event, EventStatus $next): Event
    {
        if (! $event->status->canTransitionTo($next)) {
            throw new InvalidStatusTransitionException($event->status, $next);
        }

        if ($next === EventStatus::Published && $event->event_date->isPast()) {
            throw InvalidEventDataException::pastDate();
        }

        $event->status = $next;
        $event->save();

        return $event;
    }
}
