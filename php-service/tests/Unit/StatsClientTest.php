<?php

namespace Tests\Unit;

use App\Exceptions\StatsUnavailableException;
use App\Services\StatsClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StatsClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['services.stats.url' => 'http://stats-api:5000', 'services.stats.key' => 'secret-key']);
        Cache::flush();
    }

    public function test_summary_sends_api_key_and_returns_payload(): void
    {
        Http::fake(['stats-api:5000/api/stats/summary*' => Http::response(['sales' => ['revenue' => 100]])]);

        $summary = app(StatsClient::class)->summary();

        $this->assertSame(100, $summary['sales']['revenue']);
        Http::assertSent(fn (Request $request) => $request->hasHeader('X-Api-Key', 'secret-key'));
    }

    public function test_summary_is_cached(): void
    {
        Http::fake(['*' => Http::response(['sales' => []])]);

        app(StatsClient::class)->summary();
        app(StatsClient::class)->summary();

        Http::assertSentCount(1);
    }

    public function test_event_stats_returns_null_when_event_not_found(): void
    {
        Http::fake(['*' => Http::response(['title' => 'Not found'], 404)]);

        $this->assertNull(app(StatsClient::class)->eventStats(99));
    }

    public function test_server_error_becomes_unavailable(): void
    {
        Http::fake(['*' => Http::response([], 503)]);

        $this->expectException(StatsUnavailableException::class);

        app(StatsClient::class)->summary();
    }

    public function test_connection_failure_becomes_unavailable(): void
    {
        Http::fake(fn () => throw new ConnectionException('Connection refused'));

        $this->expectException(StatsUnavailableException::class);

        app(StatsClient::class)->eventStats(1);
    }
}
