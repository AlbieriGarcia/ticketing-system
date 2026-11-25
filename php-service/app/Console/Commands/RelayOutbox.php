<?php

namespace App\Console\Commands;

use App\Messaging\OutboxRelay;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class RelayOutbox extends Command
{
    protected $signature = 'outbox:relay
        {--once : Publish the pending messages once and exit}
        {--batch=100 : Maximum messages per batch}
        {--sleep=1 : Seconds to wait when there is nothing to publish}';

    protected $description = 'Publish pending outbox messages to RabbitMQ';

    private const MAX_BACKOFF_SECONDS = 30;

    public function handle(OutboxRelay $relay): int
    {
        $batch = (int) $this->option('batch');
        $failures = 0;

        do {
            try {
                $published = $relay->relayBatch($batch);
                $failures = 0;

                if ($published > 0) {
                    $this->info("Published {$published} message(s)");
                }
            } catch (Throwable $e) {
                $failures++;
                Log::warning('Outbox relay failed, messages stay pending', ['error' => $e->getMessage(), 'attempt' => $failures]);
                $this->error("Relay failed: {$e->getMessage()}");

                if ($this->option('once')) {
                    return self::FAILURE;
                }
            }

            if ($this->option('once')) {
                break;
            }

            // Sin trabajo se espera el intervalo normal; tras fallos, backoff exponencial para no saturar al broker caído
            if ($failures > 0) {
                sleep(min(self::MAX_BACKOFF_SECONDS, 2 ** $failures));
            } elseif ($published === 0) {
                sleep((int) $this->option('sleep'));
            }
        } while (true);

        return self::SUCCESS;
    }
}
