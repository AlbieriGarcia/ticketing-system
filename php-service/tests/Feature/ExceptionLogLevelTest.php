<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExceptionLogLevelTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_rejections_are_logged_as_warnings(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $event = Event::factory()->published()->create(['total_tickets' => 1, 'available_tickets' => 1]);
        Log::spy();

        $this->postJson("/api/events/{$event->id}/reservations", ['quantity' => 2])->assertConflict();

        Log::shouldHaveReceived('warning')->with('Not enough tickets available. Remaining: 1.', \Mockery::type('array'));
        Log::shouldNotHaveReceived('error');
    }
}
