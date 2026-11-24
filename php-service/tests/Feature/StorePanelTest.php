<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Filament\Auth\Pages\Register;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StorePanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_is_the_public_event_catalog(): void
    {
        $this->get('/')->assertOk()->assertSee('Upcoming events');
    }

    public function test_guest_sees_only_upcoming_published_events(): void
    {
        Event::factory()->published()->create(['name' => 'Upcoming concert']);
        Event::factory()->create(['name' => 'Draft concert']);
        Event::factory()->published()->create(['name' => 'Past concert', 'event_date' => now()->subDay()]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Upcoming concert')
            ->assertDontSee('Draft concert')
            ->assertDontSee('Past concert');
    }

    public function test_guest_can_view_published_event_but_not_draft(): void
    {
        $published = Event::factory()->published()->create();
        $draft = Event::factory()->create();

        $this->get("/events/{$published->id}")->assertOk()->assertSee($published->name);
        $this->get("/events/{$draft->id}")->assertNotFound();
    }

    public function test_customer_and_admin_can_access_store(): void
    {
        $this->actingAs(User::factory()->create())->get('/')->assertOk();
        $this->actingAs(User::factory()->admin()->create())->get('/')->assertOk();
    }

    public function test_registration_creates_a_customer(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('store'));

        Livewire::test(Register::class)
            ->fillForm([
                'name' => 'Ana Perez',
                'email' => 'ana@example.com',
                'password' => 'secret123',
                'passwordConfirmation' => 'secret123',
            ])
            ->call('register')
            ->assertHasNoFormErrors();

        $user = User::where('email', 'ana@example.com')->firstOrFail();

        $this->assertFalse($user->isAdmin());
        $this->assertAuthenticatedAs($user);
    }
}
