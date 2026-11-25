<?php

namespace App\Messaging;

use App\Models\OutboxMessage;
use Illuminate\Support\Collection;

interface MessagePublisher
{
    /**
     * Publica el lote completo o lanza una excepción; nunca deja una publicación parcial sin avisar.
     *
     * @param  Collection<int, OutboxMessage>  $messages
     */
    public function publish(Collection $messages): void;
}
