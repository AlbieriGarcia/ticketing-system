<?php

namespace App\Exceptions;

use App\Enums\ReservationStatus;

class ReservationException extends DomainException
{
    public static function eventNotOnSale(): self
    {
        return new self('This event is not available for sale.');
    }

    public static function insufficientTickets(int $available): self
    {
        return new self("Not enough tickets available. Remaining: {$available}.");
    }

    public static function alreadyActive(): self
    {
        return new self('You already have an active reservation for this event.');
    }

    public static function notPending(ReservationStatus $status): self
    {
        return new self("Reservation is already {$status->value}.");
    }

    public static function expired(): self
    {
        return new self('Reservation has expired. Tickets were released.');
    }

    public function statusCode(): int
    {
        return 409;
    }
}
