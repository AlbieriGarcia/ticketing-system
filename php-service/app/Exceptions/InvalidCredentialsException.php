<?php

namespace App\Exceptions;

class InvalidCredentialsException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Invalid credentials.');
    }

    public function statusCode(): int
    {
        return 401;
    }
}
