<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Filament\Store\Resources\Events\Pages\ViewEvent;
use App\Filament\Store\Resources\Reservations\Pages\ListReservations;
use App\Models\Event;
use App\Models\Reservation;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StoreCartTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('store'));
    }

    public function test_guest_is_asked_to_sign_in_to_reserve(): void
    {
        $event = Event::factory()->published()->create();

        Livewire::test(ViewEvent::class, ['record' => $event->id])
            ->assertActionHidden('reserve')
            ->assertActionVisible('signInToReserve');
    }

    public function test_customer_reserves_tickets_from_event_page(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->published()->create(['total_tickets' => 50, 'available_tickets' => 50]);

        $this->actingAs($user);

        Livewire::test(ViewEvent::class, ['record' => $event->id])
            ->callAction('reserve', data: ['quantity' => 3])
            ->assertHasNoActionErrors()
            ->assertRedirect('/cart');

        $this->assertSame(47, $event->fresh()->available_tickets);
        $this->assertDatabaseHas('reservations', ['user_id' => $user->id, 'event_id' => $event->id, 'quantity' => 3]);
    }

    public function test_quantity_cannot_exceed_available_tickets(): void
    {
        $event = Event::factory()->published()->create(['total_tickets' => 2, 'available_tickets' => 2]);

        $this->actingAs(User::factory()->create());

        Livewire::test(ViewEvent::class, ['record' => $event->id])
            ->callAction('reserve', data: ['quantity' => 5])
            ->assertHasActionErrors(['quantity' => 'max']);

        $this->assertSame(2, $event->fresh()->available_tickets);
    }

    public function test_cart_lists_only_own_active_reservations(): void
    {
        $user = User::factory()->create();
        $mine = Reservation::factory()->for($user)->create();
        $expired = Reservation::factory()->for($user)->expired()->create();
        $foreign = Reservation::factory()->create();

        $this->actingAs($user);

        Livewire::test(ListReservations::class)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$expired, $foreign]);
    }

    public function test_customer_confirms_purchase_from_cart(): void
    {
        $user = User::factory()->create();
        $reservation = Reservation::factory()->for($user)->create();

        $this->actingAs($user);

        Livewire::test(ListReservations::class)->callTableAction('confirm', $reservation);

        $this->assertSame(ReservationStatus::Confirmed, $reservation->fresh()->status);
        $this->assertDatabaseHas('ticket_sales', ['reservation_id' => $reservation->id]);
    }

    public function test_guest_cannot_open_cart(): void
    {
        $this->get('/cart')->assertRedirect('/login');
    }
}
