<?php

namespace App\Messaging;

use App\Models\OutboxMessage;
use App\Observability\TraceContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OutboxRelay
{
    public function __construct(private readonly MessagePublisher $publisher) {}

    // Devuelve cuántos mensajes publicó; si el broker falla no marca ninguno y se reintentan después
    public function relayBatch(int $limit = 100): int
    {
        $published = DB::transaction(function () use ($limit): Collection {
            // SKIP LOCKED permite levantar más de un relay sin que publiquen el mismo mensaje a la vez
            $messages = OutboxMessage::query()
                ->pending()
                ->orderBy('id')
                ->limit($limit)
                ->lock('for update skip locked')
                ->get();

            if ($messages->isEmpty()) {
                return $messages;
            }

            $this->publisher->publish($messages);

            OutboxMessage::whereKey($messages->modelKeys())->update(['published_at' => now()]);

            return $messages;
        });

        // Cada mensaje pertenece a la traza de la operación que lo originó, no a la del relay
        foreach ($published as $message) {
            Log::info('Outbox message published', [
                'message_id' => $message->message_id,
                'type' => $message->type,
                'sequence' => $message->id,
                'trace_id' => TraceContext::parse($message->traceparent)?->traceId,
            ]);
        }

        return $published->count();
    }
}
