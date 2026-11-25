<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake(['*' => Http::response([], 503)]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_customer_is_sent_back_to_the_store(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin/users?page=2')
            ->assertRedirect('/')
            ->assertSessionHas('filament.notifications');
    }

    public function test_remember_me_token_is_persisted(): void
    {
        $admin = User::factory()->admin()->create();

        auth()->login($admin, remember: true);

        $this->assertNotNull($admin->fresh()->getRememberToken());
    }

    public function test_admin_can_access_panel_pages(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->get('/admin')->assertOk();
        $this->get('/admin/events')->assertOk();
        $this->get('/admin/ticket-sales')->assertOk();
        $this->get('/admin/users')->assertOk();
    }
}
