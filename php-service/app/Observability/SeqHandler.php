<?php

namespace App\Observability;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Monolog\Formatter\FormatterInterface;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Throwable;

// Envía cada log a Seq. Es best-effort: si Seq no responde se deja de intentar un rato y la aplicación nunca falla por ello
final class SeqHandler extends AbstractProcessingHandler
{
    private const RETRY_AFTER_SECONDS = 30;

    private readonly ClientInterface $client;

    private ?int $unavailableUntil = null;

    public function __construct(
        private readonly ?string $url,
        private readonly string $application,
        int|string|Level $level = Level::Debug,
        ?ClientInterface $client = null,
    ) {
        parent::__construct($level);

        // Timeouts cortos: un log nunca debe retrasar de forma apreciable una request
        $this->client = $client ?? new Client(['connect_timeout' => 0.3, 'timeout' => 1]);
    }

    protected function write(LogRecord $record): void
    {
        if (blank($this->url) || ($this->unavailableUntil !== null && time() < $this->unavailableUntil)) {
            return;
        }

        try {
            $this->client->request('POST', rtrim($this->url, '/').'/ingest/clef', [
                'headers' => ['Content-Type' => 'application/vnd.serilog.clef'],
                'body' => $record->formatted,
            ]);

            $this->unavailableUntil = null;
        } catch (Throwable) {
            $this->unavailableUntil = time() + self::RETRY_AFTER_SECONDS;
        }
    }

    protected function getDefaultFormatter(): FormatterInterface
    {
        return new ClefFormatter($this->application);
    }
}
