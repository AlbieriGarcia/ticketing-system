<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\OutboxMessage;
use App\Models\User;
use App\Observability\TraceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TracePropagationTest extends TestCase
{
    use RefreshDatabase;

    private const TRACE_ID = '4bf92f3577b34da6a3ce929d0e0e4736';

    private const CALLER_SPAN_ID = '00f067aa0ba902b7';

    public function test_request_without_trace_starts_a_new_one(): void
    {
        $response = $this->getJson('/api/events');

        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $response->headers->get('X-Trace-Id'));
    }

    public function test_request_continues_the_caller_trace(): void
    {
        $this->getJson('/api/events', ['traceparent' => $this->callerTraceparent()])
            ->assertHeader('X-Trace-Id', self::TRACE_ID);

        $trace = app(TraceContext::class);
        $this->assertSame(self::CALLER_SPAN_ID, $trace->parentSpanId);
    }

    public function test_outbox_message_carries_the_request_trace(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $event = Event::factory()->published()->create(['total_tickets' => 10, 'available_tickets' => 10]);

        $this->postJson("/api/events/{$event->id}/reservations", ['quantity' => 1], ['traceparent' => $this->callerTraceparent()])
            ->assertCreated();

        $traceparent = OutboxMessage::sole()->traceparent;
        $this->assertSame(self::TRACE_ID, TraceContext::parse($traceparent)->traceId);
        // El mensaje cuelga del span de esta request, no del de quien llamó
        $this->assertSame(app(TraceContext::class)->spanId, TraceContext::parse($traceparent)->spanId);
    }

    private function callerTraceparent(): string
    {
        return '00-'.self::TRACE_ID.'-'.self::CALLER_SPAN_ID.'-01';
    }
}
