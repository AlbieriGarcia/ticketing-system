<?php

namespace App\Console\Commands;

use App\Services\ReservationService;
use Illuminate\Console\Command;

class ExpireReservations extends Command
{
    protected $signature = 'reservations:expire';

    protected $description = 'Expire overdue pending reservations and release their tickets';

    public function handle(ReservationService $reservationService): int
    {
        $count = $reservationService->expireOverdue();

        $this->info("Expired reservations: {$count}");

        return self::SUCCESS;
    }
}
