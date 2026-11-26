<?php

namespace App\Console\Commands;

use App\Models\OutboxMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MonitorOutbox extends Command
{
    protected $signature = 'outbox:monitor {--threshold=120 : Seconds a message may stay pending before warning}';

    protected $description = 'Warn when outbox messages stay unpublished for too long';

    // Corre en el scheduler y no en el relay: si el relay se cae no podría avisar de sí mismo
    public function handle(): int
    {
        $threshold = (int) $this->option('threshold');

        $stale = OutboxMessage::query()
            ->pending()
            ->where('created_at', '<', now()->subSeconds($threshold));

        $count = $stale->count();

        if ($count > 0) {
            Log::warning('Outbox backlog: {outbox_pending} message(s) pending for more than {threshold_seconds} s', [
                'outbox_pending' => $count,
                'threshold_seconds' => $threshold,
                'oldest_created_at' => $stale->min('created_at'),
            ]);
        }

        $this->info("Stale outbox messages: {$count}");

        return self::SUCCESS;
    }
}
