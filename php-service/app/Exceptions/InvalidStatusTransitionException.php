<?php

namespace App\Exceptions;

use App\Enums\EventStatus;

class InvalidStatusTransitionException extends DomainException
{
    public function __construct(EventStatus $from, EventStatus $to)
    {
        parent::__construct("Cannot change event status from '{$from->value}' to '{$to->value}'.");
    }

    public function statusCode(): int
    {
        return 409;
    }
}
