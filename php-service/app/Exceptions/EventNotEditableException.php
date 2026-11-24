<?php

namespace App\Exceptions;

use App\Enums\EventStatus;

class EventNotEditableException extends DomainException
{
    public function __construct(EventStatus $status)
    {
        parent::__construct("Events with status '{$status->value}' cannot be modified.");
    }

    public function statusCode(): int
    {
        return 409;
    }
}
