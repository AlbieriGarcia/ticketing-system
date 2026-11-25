<?php

namespace App\Messaging;

use App\Models\OutboxMessage;
use Illuminate\Support\Facades\DB;

class OutboxRelay
{
    public function __construct(private readonly MessagePublisher $publisher) {}

    // Devuelve cuántos mensajes publicó; si el broker falla no marca ninguno y se reintentan después
    public function relayBatch(int $limit = 100): int
    {
        return DB::transaction(function () use ($limit) {
            // SKIP LOCKED permite levantar más de un relay sin que publiquen el mismo mensaje a la vez
            $messages = OutboxMessage::query()
                ->pending()
                ->orderBy('id')
                ->limit($limit)
                ->lock('for update skip locked')
                ->get();

            if ($messages->isEmpty()) {
                return 0;
            }

            $this->publisher->publish($messages);

            OutboxMessage::whereKey($messages->modelKeys())->update(['published_at' => now()]);

            return $messages->count();
        });
    }
}
