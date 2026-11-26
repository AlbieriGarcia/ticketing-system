<?php

namespace Tests\Unit;

use App\Observability\TraceContext;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TraceContextTest extends TestCase
{
    public function test_parses_a_valid_traceparent(): void
    {
        $trace = TraceContext::parse('00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01');

        $this->assertSame('4bf92f3577b34da6a3ce929d0e0e4736', $trace->traceId);
        $this->assertSame('00f067aa0ba902b7', $trace->spanId);
    }

    #[DataProvider('invalidTraceparents')]
    public function test_rejects_invalid_traceparent(?string $header): void
    {
        $this->assertNull(TraceContext::parse($header));
    }

    public static function invalidTraceparents(): array
    {
        return [
            'missing' => [null],
            'garbage' => ['not-a-trace'],
            'unknown version' => ['01-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01'],
            'zero trace id' => ['00-00000000000000000000000000000000-00f067aa0ba902b7-01'],
            'zero span id' => ['00-4bf92f3577b34da6a3ce929d0e0e4736-0000000000000000-01'],
        ];
    }

    public function test_child_keeps_the_trace_and_points_to_its_parent(): void
    {
        $parent = TraceContext::generate();
        $child = $parent->child();

        $this->assertSame($parent->traceId, $child->traceId);
        $this->assertSame($parent->spanId, $child->parentSpanId);
        $this->assertNotSame($parent->spanId, $child->spanId);
    }

    public function test_generated_context_round_trips_through_traceparent(): void
    {
        $trace = TraceContext::generate();

        $this->assertMatchesRegularExpression('/^00-[0-9a-f]{32}-[0-9a-f]{16}-01$/', $trace->traceparent());
        $this->assertSame($trace->traceId, TraceContext::parse($trace->traceparent())->traceId);
    }
}
