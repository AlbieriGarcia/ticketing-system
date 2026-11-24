<?php

namespace App\Exceptions;

class InvalidEventDataException extends DomainException
{
    public static function totalBelowCommitted(int $committed): self
    {
        return new self("Total tickets cannot be lower than the {$committed} tickets already sold or reserved.");
    }

    public static function pastDate(): self
    {
        return new self('A published event must have a future date.');
    }

    public function statusCode(): int
    {
        return 422;
    }
}
