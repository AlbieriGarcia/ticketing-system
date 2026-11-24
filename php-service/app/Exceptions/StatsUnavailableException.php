<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class StatsUnavailableException extends RuntimeException
{
    public function __construct(?Throwable $previous = null)
    {
        parent::__construct('Statistics service is unavailable.', 0, $previous);
    }
}
