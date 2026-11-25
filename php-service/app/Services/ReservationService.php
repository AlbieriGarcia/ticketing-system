<?php

namespace App\Services;

use App\Enums\ReservationStatus;
use App\Exceptions\ReservationException;
use App\Messaging\IntegrationEvents;
use App\Models\Event;
use App\Models\Reservation;
use App\Models\TicketSale;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class ReservationService
{
    public function __construct(private readonly IntegrationEvents $integrationEvents) {}

    public function reserve(User $user, Event $event, int $quantity): Reservation
    {
        try {
            return DB::transaction(function () use ($user, $event, $quantity) {
                // El lock serializa las compras del mismo evento: nadie lee el stock mientras otro lo descuenta
                $event = Event::whereKey($event->id)->lockForUpdate()->firstOrFail();

                if (! $event->isOnSale()) {
                    throw ReservationException::eventNotOnSale();
                }

                if ($event->available_tickets < $quantity) {
                    throw ReservationException::insufficientTickets($event->available_tickets);
                }

                $event->decrement('available_tickets', $quantity);

                $reservation = Reservation::create([
                    'user_id' => $user->id,
                    'event_id' => $event->id,
                    'quantity' => $quantity,
                    'expires_at' => now()->addMinutes(config('tickets.reservation_ttl_minutes')),
                ]);

                $this->integrationEvents->eventChanged($event->id);

                return $reservation;
            });
        } catch (UniqueConstraintViolationException) {
            // El índice parcial uq_reservations_active garantiza una sola reserva pendiente por usuario y evento
            throw ReservationException::alreadyActive();
        }
    }

    public function confirm(Reservation $reservation): TicketSale
    {
        $result = DB::transaction(function () use ($reservation) {
            $reservation = Reservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();

            if (! $reservation->isPending()) {
                throw ReservationException::notPending($reservation->status);
            }

            if ($reservation->isExpired()) {
                $this->release($reservation, ReservationStatus::Expired);

                return ReservationException::expired();
            }

            $event = $reservation->event;

            if (! $event->isOnSale()) {
                $this->release($reservation, ReservationStatus::Cancelled);

                return ReservationException::eventNotOnSale();
            }

            $reservation->status = ReservationStatus::Confirmed;
            $reservation->save();

            $sale = TicketSale::create([
                'user_id' => $reservation->user_id,
                'event_id' => $reservation->event_id,
                'reservation_id' => $reservation->id,
                'quantity' => $reservation->quantity,
                'unit_price' => $event->ticket_price,
                'total_amount' => bcmul($event->ticket_price, (string) $reservation->quantity, 2),
                'purchase_date' => now(),
            ]);

            $this->integrationEvents->saleCompleted($sale);

            return $sale;
        });

        // Se lanza fuera de la transacción: si se lanzara dentro, el rollback desharía la liberación de las entradas
        if ($result instanceof ReservationException) {
            throw $result;
        }

        return $result;
    }

    public function cancel(Reservation $reservation): void
    {
        DB::transaction(function () use ($reservation) {
            $reservation = Reservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();

            if (! $reservation->isPending()) {
                throw ReservationException::notPending($reservation->status);
            }

            $this->release($reservation, ReservationStatus::Cancelled);
        });
    }

    public function expireOverdue(): int
    {
        $expired = 0;

        Reservation::query()
            ->where('status', ReservationStatus::Pending)
            ->where('expires_at', '<=', now())
            ->select('id')
            ->chunkById(100, function ($reservations) use (&$expired) {
                foreach ($reservations as $reservation) {
                    $expired += $this->expireOne($reservation->id) ? 1 : 0;
                }
            });

        return $expired;
    }

    private function expireOne(int $reservationId): bool
    {
        return DB::transaction(function () use ($reservationId) {
            // SKIP LOCKED: si la reserva se está confirmando en este momento, el job la salta en vez de esperar
            $reservation = Reservation::whereKey($reservationId)
                ->where('status', ReservationStatus::Pending)
                ->lock('for update skip locked')
                ->first();

            if (! $reservation) {
                return false;
            }

            $this->release($reservation, ReservationStatus::Expired);

            return true;
        });
    }

    private function release(Reservation $reservation, ReservationStatus $status): void
    {
        $reservation->status = $status;
        $reservation->save();
        Event::whereKey($reservation->event_id)->increment('available_tickets', $reservation->quantity);
        $this->integrationEvents->eventChanged($reservation->event_id);
    }
}
