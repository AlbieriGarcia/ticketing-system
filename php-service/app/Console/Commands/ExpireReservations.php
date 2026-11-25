<?php

namespace App\Console\Commands;

use App\Services\ReservationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ExpireReservations extends Command
{
    protected $signature = 'reservations:expire';

    protected $description = 'Expire overdue pending reservations and release their tickets';

    public function handle(ReservationService $reservationService): int
    {
        $count = $reservationService->expireOverdue();

        if ($count > 0) {
            Log::info('Expired reservations released', ['count' => $count]);
        }

        $this->info("Expired reservations: {$count}");

        return self::SUCCESS;
    }
}
