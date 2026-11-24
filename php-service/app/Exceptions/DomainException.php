<?php

namespace App\Exceptions;

use RuntimeException;

// Base de los errores de negocio: cada subclase define su código HTTP y el handler global la convierte en JSON
abstract class DomainException extends RuntimeException
{
    abstract public function statusCode(): int;
}
