<?php

namespace Tests\Feature;

use App\Enums\EventStatus;
use App\Exceptions\ReservationException;
use App\Messaging\IntegrationEvents;
use App\Messaging\MessagePublisher;
use App\Messaging\OutboxRelay;
use App\Models\Event;
use App\Models\OutboxMessage;
use App\Models\Reservation;
use App\Models\User;
use App\Services\EventService;
use App\Services\ReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use RuntimeException;
use Tests\TestCase;

class OutboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_reserving_records_event_snapshot_with_new_stock(): void
    {
        $event = Event::factory()->published()->create(['total_tickets' => 10, 'available_tickets' => 10]);

        app(ReservationService::class)->reserve(User::factory()->create(), $event, 3);

        $message = OutboxMessage::sole();
        $this->assertSame(IntegrationEvents::EVENT_CHANGED, $message->type);
        $this->assertSame($event->id, $message->payload['id']);
        $this->assertSame(7, $message->payload['available_tickets']);
        $this->assertNull($message->published_at);
    }

    public function test_confirming_records_sale_completed(): void
    {
        $reservation = Reservation::factory()->create();

        $sale = app(ReservationService::class)->confirm($reservation);

        $message = OutboxMessage::where('type', IntegrationEvents::SALE_COMPLETED)->sole();
        $this->assertSame($sale->id, $message->payload['id']);
        $this->assertSame($reservation->event_id, $message->payload['event_id']);
        $this->assertSame($sale->total_amount, $message->payload['total_amount']);
    }

    public function test_rejected_reservation_records_nothing(): void
    {
        $event = Event::factory()->published()->create(['total_tickets' => 2, 'available_tickets' => 2]);

        try {
            app(ReservationService::class)->reserve(User::factory()->create(), $event, 5);
            $this->fail('Expected the reservation to be rejected.');
        } catch (ReservationException) {
            // El rollback de la transacción descarta también el mensaje
        }

        $this->assertSame(0, OutboxMessage::count());
    }

    public function test_releasing_a_reservation_records_restored_stock(): void
    {
        $event = Event::factory()->published()->create(['total_tickets' => 10, 'available_tickets' => 6]);
        $reservation = Reservation::factory()->for($event)->create(['quantity' => 4]);

        app(ReservationService::class)->cancel($reservation);

        $this->assertSame(10, OutboxMessage::sole()->payload['available_tickets']);
    }

    public function test_status_change_records_event_snapshot(): void
    {
        $event = Event::factory()->create();

        app(EventService::class)->changeStatus($event, EventStatus::Published);

        $this->assertSame('published', OutboxMessage::sole()->payload['status']);
    }

    public function test_relay_publishes_pending_messages_in_order_and_marks_them(): void
    {
        $publisher = $this->fakePublisher();
        $this->createThreeEvents();
        OutboxMessage::query()->whereKey(OutboxMessage::min('id'))->update(['published_at' => now()]);

        $published = app(OutboxRelay::class)->relayBatch();

        $this->assertSame(2, $published);
        $this->assertSame(OutboxMessage::orderBy('id')->skip(1)->take(2)->pluck('id')->all(), $publisher->publishedIds);
        $this->assertSame(0, OutboxMessage::pending()->count());
    }

    public function test_relay_leaves_messages_pending_when_broker_fails(): void
    {
        $this->app->instance(MessagePublisher::class, new class implements MessagePublisher
        {
            public function publish(Collection $messages): void
            {
                throw new RuntimeException('Broker unavailable');
            }
        });
        $this->createThreeEvents();

        try {
            app(OutboxRelay::class)->relayBatch();
            $this->fail('Expected the relay to propagate the broker failure.');
        } catch (RuntimeException) {
            // Se reintentará en la siguiente vuelta del relay
        }

        $this->assertSame(3, OutboxMessage::pending()->count());
    }

    private function createThreeEvents(): void
    {
        $service = app(EventService::class);

        foreach (range(1, 3) as $i) {
            $service->create(Event::factory()->raw());
        }
    }

    private function fakePublisher(): object
    {
        $publisher = new class implements MessagePublisher
        {
            public array $publishedIds = [];

            public function publish(Collection $messages): void
            {
                array_push($this->publishedIds, ...$messages->modelKeys());
            }
        };

        $this->app->instance(MessagePublisher::class, $publisher);

        return $publisher;
    }
}
