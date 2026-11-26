<?php

namespace Tests\Feature;

use App\Models\OutboxMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tests\TestCase;

class OutboxMonitorTest extends TestCase
{
    use RefreshDatabase;

    public function test_warns_when_messages_stay_pending_too_long(): void
    {
        $this->pendingMessage(createdSecondsAgo: 300);
        $this->pendingMessage(createdSecondsAgo: 10);
        Log::spy();

        $this->artisan('outbox:monitor')->assertSuccessful();

        Log::shouldHaveReceived('warning')->once()->withArgs(
            fn (string $message, array $context) => $context['outbox_pending'] === 1 && $context['threshold_seconds'] === 120
        );
    }

    public function test_stays_silent_when_the_relay_keeps_up(): void
    {
        $this->pendingMessage(createdSecondsAgo: 10);
        $published = $this->pendingMessage(createdSecondsAgo: 600);
        $published->forceFill(['published_at' => now()])->save();
        Log::spy();

        $this->artisan('outbox:monitor')->assertSuccessful();

        Log::shouldNotHaveReceived('warning');
    }

    private function pendingMessage(int $createdSecondsAgo): OutboxMessage
    {
        $message = OutboxMessage::create(['message_id' => (string) Str::uuid(), 'type' => 'event.changed', 'payload' => []]);
        $message->forceFill(['created_at' => now()->subSeconds($createdSecondsAgo)])->save();

        return $message;
    }
}
