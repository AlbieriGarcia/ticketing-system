<?php

namespace App\Messaging;

use App\Models\Event;
use App\Models\OutboxMessage;
use App\Models\TicketSale;
use App\Observability\TraceContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

class IntegrationEvents
{
    public const EVENT_CHANGED = 'event.changed';

    public const SALE_COMPLETED = 'sale.completed';

    public function __construct(private readonly TraceContext $trace) {}

    // Foto completa del evento: el consumidor reemplaza su copia sin depender de mensajes anteriores
    public function eventChanged(int $eventId): void
    {
        $event = Event::query()->findOrFail($eventId);

        $this->record(self::EVENT_CHANGED, [
            'id' => $event->id,
            'name' => $event->name,
            'description' => $event->description,
            'event_date' => $event->event_date->toIso8601String(),
            'ticket_price' => $event->ticket_price,
            'total_tickets' => $event->total_tickets,
            'available_tickets' => $event->available_tickets,
            'status' => $event->status->value,
        ]);
    }

    public function saleCompleted(TicketSale $sale): void
    {
        $this->record(self::SALE_COMPLETED, [
            'id' => $sale->id,
            'event_id' => $sale->event_id,
            'user_id' => $sale->user_id,
            'quantity' => $sale->quantity,
            'total_amount' => $sale->total_amount,
            'purchase_date' => $sale->purchase_date->toIso8601String(),
        ]);
    }

    private function record(string $type, array $payload): void
    {
        // El mensaje debe confirmarse o descartarse junto con el cambio que anuncia
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Integration events must be recorded inside a database transaction.');
        }

        OutboxMessage::create([
            'message_id' => (string) Str::uuid(),
            'type' => $type,
            'payload' => $payload,
            'traceparent' => $this->trace->traceparent(),
        ]);
    }
}
