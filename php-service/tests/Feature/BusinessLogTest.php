<?php

namespace Tests\Feature;

use App\Exceptions\ReservationException;
use App\Filament\Store\Resources\Events\Pages\ViewEvent;
use App\Filament\Store\Resources\Reservations\Pages\ListReservations;
use App\Models\Event;
use App\Models\Reservation;
use App\Models\User;
use App\Observability\LivewireSpanName;
use App\Services\ReservationService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;
use Mockery\Matcher\Closure;
use Tests\TestCase;

class BusinessLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_steps_share_the_reservation_id(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->published()->create(['total_tickets' => 10, 'available_tickets' => 10]);
        Log::spy();

        $reservation = app(ReservationService::class)->reserve($user, $event, 2);
        $sale = app(ReservationService::class)->confirm($reservation);

        Log::shouldHaveReceived('info')->with('Reservation created', $this->contextFor($reservation));
        Log::shouldHaveReceived('info')->with('Purchase confirmed', \Mockery::on(
            fn (array $context) => $context['reservation_id'] === $reservation->id
                && $context['user_id'] === $user->id
                && $context['sale_id'] === $sale->id
        ));
    }

    public function test_rejected_reservation_logs_nothing(): void
    {
        $event = Event::factory()->published()->create(['total_tickets' => 1, 'available_tickets' => 1]);
        Log::spy();

        try {
            app(ReservationService::class)->reserve(User::factory()->create(), $event, 5);
        } catch (ReservationException) {
        }

        Log::shouldNotHaveReceived('info');
    }

    public function test_released_reservation_logs_the_reason(): void
    {
        $event = Event::factory()->published()->create(['total_tickets' => 10, 'available_tickets' => 10]);
        $reservation = app(ReservationService::class)->reserve(User::factory()->create(), $event, 2);
        Log::spy();

        app(ReservationService::class)->cancel($reservation);

        Log::shouldHaveReceived('info')->with('Reservation released', \Mockery::on(
            fn (array $context) => $context['reservation_id'] === $reservation->id && $context['reason'] === 'cancelled'
        ));
    }

    public function test_store_actions_get_a_readable_span_name(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('store'));
        $user = User::factory()->create();
        $event = Event::factory()->published()->create();
        $this->actingAs($user);

        Livewire::test(ViewEvent::class, ['record' => $event->id])->callAction('reserve', data: ['quantity' => 1]);
        $this->assertSame('ViewEvent::reserve', request()->attributes->get(LivewireSpanName::ATTRIBUTE));

        Livewire::test(ListReservations::class)->callTableAction('confirm', Reservation::sole());
        $this->assertSame('ListReservations::confirm', request()->attributes->get(LivewireSpanName::ATTRIBUTE));
    }

    private function contextFor(Reservation $reservation): Closure
    {
        return \Mockery::on(fn (array $context) => $context['reservation_id'] === $reservation->id
            && $context['user_id'] === $reservation->user_id
            && $context['event_id'] === $reservation->event_id
            && $context['quantity'] === $reservation->quantity);
    }
}
