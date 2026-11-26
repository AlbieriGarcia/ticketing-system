<?php

namespace Tests\Unit;

use App\Observability\SeqHandler;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Monolog\Level;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class SeqHandlerTest extends TestCase
{
    private array $sent = [];

    public function test_sends_clef_event_with_trace_fields(): void
    {
        $logger = $this->logger([new Response(201)]);

        $logger->info('HTTP GET /api/events', [
            'trace_id' => '4bf92f3577b34da6a3ce929d0e0e4736',
            'span_id' => 'c4d3087338911cd7',
            'parent_span_id' => '00f067aa0ba902b7',
            'span_start' => '2026-11-25T10:00:00.100000+00:00',
            'status' => 200,
        ]);

        $event = $this->sentEvent();
        $this->assertSame('http://seq/ingest/clef', (string) $this->sent[0]->getUri());
        $this->assertSame('HTTP GET /api/events', $event['@m']);
        $this->assertSame('Information', $event['@l']);
        $this->assertSame('4bf92f3577b34da6a3ce929d0e0e4736', $event['@tr']);
        $this->assertSame('c4d3087338911cd7', $event['@sp']);
        $this->assertSame('00f067aa0ba902b7', $event['@ps']);
        $this->assertSame('2026-11-25T10:00:00.100000+00:00', $event['@st']);
        $this->assertSame('php-api', $event['Application']);
        $this->assertSame(200, $event['status']);
        $this->assertArrayNotHasKey('trace_id', $event);
    }

    public function test_exception_goes_to_the_reserved_field(): void
    {
        $logger = $this->logger([new Response(201)]);

        $logger->error('Unexpected failure', ['exception' => new RuntimeException('boom')]);

        $event = $this->sentEvent();
        $this->assertSame('Error', $event['@l']);
        $this->assertStringContainsString('RuntimeException: boom', $event['@x']);
    }

    public function test_stops_sending_for_a_while_when_seq_is_down(): void
    {
        $logger = $this->logger([new ConnectException('Connection refused', new Request('POST', 'http://seq'))]);

        $logger->info('first');
        $logger->info('second');

        // Solo el primero llega a intentarse; el segundo se descarta sin esperar al timeout
        $this->assertCount(1, $this->sent);
    }

    public function test_without_url_sends_nothing(): void
    {
        $logger = $this->logger([], url: null);

        $logger->info('ignored');

        $this->assertCount(0, $this->sent);
    }

    private function logger(array $responses, ?string $url = 'http://seq'): Logger
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::mapRequest(function ($request) {
            $this->sent[] = $request;

            return $request;
        }));

        return new Logger('test', [new SeqHandler($url, 'php-api', Level::Debug, new Client(['handler' => $stack]))]);
    }

    private function sentEvent(): array
    {
        return json_decode((string) $this->sent[0]->getBody(), true, flags: JSON_THROW_ON_ERROR);
    }
}
