<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReservationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    private function publishedEvent(int $tickets = 10, float $price = 25.50): Event
    {
        return Event::factory()->published()->create([
            'total_tickets' => $tickets,
            'available_tickets' => $tickets,
            'ticket_price' => $price,
        ]);
    }

    public function test_reservation_holds_tickets(): void
    {
        $event = $this->publishedEvent(10);

        $this->postJson("/api/events/{$event->id}/reservations", ['quantity' => 3])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.quantity', 3);

        $this->assertSame(7, $event->fresh()->available_tickets);
    }

    public function test_cannot_reserve_more_than_available(): void
    {
        $event = $this->publishedEvent(2);

        $this->postJson("/api/events/{$event->id}/reservations", ['quantity' => 3])
            ->assertConflict()
            ->assertJsonPath('message', 'Not enough tickets available. Remaining: 2.');

        $this->assertSame(2, $event->fresh()->available_tickets);
    }

    public function test_cannot_reserve_draft_or_past_events(): void
    {
        $draft = Event::factory()->create();
        $past = Event::factory()->published()->create(['event_date' => now()->subDay()]);

        $this->postJson("/api/events/{$draft->id}/reservations", ['quantity' => 1])->assertConflict();
        $this->postJson("/api/events/{$past->id}/reservations", ['quantity' => 1])->assertConflict();
    }

    public function test_only_one_active_reservation_per_event(): void
    {
        $event = $this->publishedEvent(10);

        $this->postJson("/api/events/{$event->id}/reservations", ['quantity' => 1])->assertCreated();
        $this->postJson("/api/events/{$event->id}/reservations", ['quantity' => 1])
            ->assertConflict()
            ->assertJsonPath('message', 'You already have an active reservation for this event.');

        $this->assertSame(9, $event->fresh()->available_tickets);
    }

    public function test_quantity_is_validated(): void
    {
        $event = $this->publishedEvent();

        $this->postJson("/api/events/{$event->id}/reservations", ['quantity' => 11])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quantity');
    }

    public function test_confirm_creates_sale_with_frozen_price(): void
    {
        $event = $this->publishedEvent(10, 25.50);
        $reservation = Reservation::factory()->for($this->user)->for($event)->create(['quantity' => 3]);

        $this->postJson("/api/reservations/{$reservation->id}/confirm")
            ->assertCreated()
            ->assertJsonPath('data.unit_price', '25.50')
            ->assertJsonPath('data.total_amount', '76.50')
            ->assertJsonPath('data.purchase_date', fn ($date) => $date !== null)
            ->assertJsonPath('data.event.id', $event->id);

        $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'status' => 'confirmed']);
        $this->assertDatabaseHas('ticket_sales', ['reservation_id' => $reservation->id, 'quantity' => 3]);
    }

    public function test_reservation_cannot_be_confirmed_twice(): void
    {
        $reservation = Reservation::factory()->for($this->user)->create();

        $this->postJson("/api/reservations/{$reservation->id}/confirm")->assertCreated();
        $this->postJson("/api/reservations/{$reservation->id}/confirm")->assertConflict();

        $this->assertDatabaseCount('ticket_sales', 1);
    }

    public function test_expired_reservation_cannot_be_confirmed_and_releases_tickets(): void
    {
        $event = $this->publishedEvent(10);
        $event->update(['available_tickets' => 8]);
        $reservation = Reservation::factory()->for($this->user)->for($event)->expired()->create(['quantity' => 2]);

        $this->postJson("/api/reservations/{$reservation->id}/confirm")
            ->assertConflict()
            ->assertJsonPath('message', 'Reservation has expired. Tickets were released.');

        $this->assertDatabaseHas('reservations', ['id' => $reservation->id, 'status' => 'expired']);
        $this->assertSame(10, $event->fresh()->available_tickets);
        $this->assertDatabaseCount('ticket_sales', 0);
    }

    public function test_cancel_releases_tickets(): void
    {
        $event = $this->publishedEvent(10);
        $event->update(['available_tickets' => 7]);
        $reservation = Reservation::factory()->for($this->user)->for($event)->create(['quantity' => 3]);

        $this->postJson("/api/reservations/{$reservation->id}/cancel")->assertNoContent();

        $this->assertSame(10, $event->fresh()->available_tickets);
    }

    public function test_user_cannot_touch_someone_elses_reservation(): void
    {
        $reservation = Reservation::factory()->create();

        $this->postJson("/api/reservations/{$reservation->id}/confirm")->assertForbidden();
        $this->postJson("/api/reservations/{$reservation->id}/cancel")->assertForbidden();
    }

    public function test_expire_command_releases_only_overdue_reservations(): void
    {
        $event = $this->publishedEvent(10);
        $event->update(['available_tickets' => 5]);
        Reservation::factory()->for($event)->expired()->create(['quantity' => 3]);
        Reservation::factory()->for($event)->create(['quantity' => 2]);

        $this->artisan('reservations:expire')
            ->expectsOutput('Expired reservations: 1')
            ->assertSuccessful();

        $this->assertSame(8, $event->fresh()->available_tickets);
    }

    public function test_purchase_history_lists_only_own_sales(): void
    {
        $mine = Reservation::factory()->for($this->user)->create();
        $this->postJson("/api/reservations/{$mine->id}/confirm")->assertCreated();

        $other = User::factory()->create();
        $theirs = Reservation::factory()->for($other)->create();
        Sanctum::actingAs($other);
        $this->postJson("/api/reservations/{$theirs->id}/confirm")->assertCreated();

        Sanctum::actingAs($this->user);
        $this->getJson('/api/me/purchases')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.reservation_id', $mine->id);
    }

    public function test_database_rejects_overselling_even_bypassing_the_app(): void
    {
        $event = $this->publishedEvent(1);

        $this->expectException(\Illuminate\Database\QueryException::class);

        $event->decrement('available_tickets', 2);
    }
}
