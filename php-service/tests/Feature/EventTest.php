<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EventTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        return $admin;
    }

    private function validPayload(array $overrides = []): array
    {
        return [
            'name' => 'Rock Festival',
            'description' => 'Annual rock festival',
            'event_date' => now()->addMonth()->toIso8601String(),
            'ticket_price' => 50,
            'total_tickets' => 100,
            ...$overrides,
        ];
    }

    public function test_admin_can_create_event_as_draft(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/events', $this->validPayload())
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.available_tickets', 100);
    }

    public function test_customer_cannot_create_event(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/events', $this->validPayload())->assertForbidden();
    }

    public function test_guest_cannot_create_event(): void
    {
        $this->postJson('/api/events', $this->validPayload())->assertUnauthorized();
    }

    public function test_create_validates_input(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/events', $this->validPayload([
            'event_date' => now()->subDay()->toIso8601String(),
            'total_tickets' => 0,
            'ticket_price' => -5,
        ]))->assertUnprocessable()->assertJsonValidationErrors(['event_date', 'total_tickets', 'ticket_price']);
    }

    public function test_public_listing_only_shows_published_events(): void
    {
        Event::factory()->published()->count(2)->create();
        Event::factory()->create();

        $this->getJson('/api/events')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_admin_listing_can_filter_by_status(): void
    {
        $this->actingAsAdmin();
        Event::factory()->published()->create();
        Event::factory()->count(3)->create();

        $this->getJson('/api/events?status=draft')->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_draft_event_is_hidden_from_public(): void
    {
        $event = Event::factory()->create();

        $this->getJson("/api/events/{$event->id}")->assertNotFound();
    }

    public function test_availability_endpoint(): void
    {
        $event = Event::factory()->published()->create(['total_tickets' => 10, 'available_tickets' => 0]);

        $this->getJson("/api/events/{$event->id}/availability")
            ->assertOk()
            ->assertJson(['total_tickets' => 10, 'available_tickets' => 0, 'is_sold_out' => true]);
    }

    public function test_update_recalculates_availability_keeping_committed_tickets(): void
    {
        $this->actingAsAdmin();
        $event = Event::factory()->published()->create(['total_tickets' => 100, 'available_tickets' => 70]);

        $this->putJson("/api/events/{$event->id}", ['total_tickets' => 150])
            ->assertOk()
            ->assertJsonPath('data.available_tickets', 120);
    }

    public function test_update_rejects_total_below_committed_tickets(): void
    {
        $this->actingAsAdmin();
        $event = Event::factory()->published()->create(['total_tickets' => 100, 'available_tickets' => 70]);

        $this->putJson("/api/events/{$event->id}", ['total_tickets' => 20])->assertUnprocessable();
    }

    public function test_cancelled_event_cannot_be_updated(): void
    {
        $this->actingAsAdmin();
        $event = Event::factory()->cancelled()->create();

        $this->putJson("/api/events/{$event->id}", ['name' => 'New name'])->assertConflict();
    }

    public function test_admin_can_publish_draft_event(): void
    {
        $this->actingAsAdmin();
        $event = Event::factory()->create();

        $this->patchJson("/api/events/{$event->id}/status", ['status' => 'published'])
            ->assertOk()
            ->assertJsonPath('data.status', 'published');
    }

    public function test_invalid_status_transition_is_rejected(): void
    {
        $this->actingAsAdmin();
        $event = Event::factory()->cancelled()->create();

        $this->patchJson("/api/events/{$event->id}/status", ['status' => 'published'])->assertConflict();
    }

    public function test_unknown_event_returns_not_found(): void
    {
        $this->getJson('/api/events/999')
            ->assertNotFound()
            ->assertJsonPath('message', 'Resource not found.');
    }
}
