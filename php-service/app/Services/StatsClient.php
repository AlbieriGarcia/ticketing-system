<?php

namespace App\Services;

use App\Exceptions\StatsUnavailableException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class StatsClient
{
    private const CACHE_SECONDS = 30;

    public function summary(int $top = 5): array
    {
        // Los widgets del dashboard se cargan por separado: el caché evita una llamada a .NET por cada uno
        return Cache::remember("stats.summary.{$top}", self::CACHE_SECONDS, function () use ($top) {
            $response = $this->get('/api/stats/summary', ['top' => $top]);

            if ($response->failed()) {
                throw new StatsUnavailableException();
            }

            return $response->json();
        });
    }

    public function eventStats(int $eventId): ?array
    {
        $response = $this->get("/api/stats/events/{$eventId}");

        if ($response->notFound()) {
            return null;
        }

        if ($response->failed()) {
            throw new StatsUnavailableException();
        }

        return $response->json();
    }

    private function get(string $path, array $query = []): Response
    {
        try {
            return Http::baseUrl(config('services.stats.url'))
                ->withHeaders(['X-Api-Key' => config('services.stats.key')])
                ->acceptJson()
                ->connectTimeout(2)
                ->timeout(8)
                ->get($path, $query);
        } catch (ConnectionException $e) {
            throw new StatsUnavailableException($e);
        }
    }
}
