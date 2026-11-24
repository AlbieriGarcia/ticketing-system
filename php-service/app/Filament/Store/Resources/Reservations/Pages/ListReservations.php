<?php

namespace App\Filament\Store\Resources\Reservations\Pages;

use App\Filament\Store\Resources\Reservations\ReservationResource;
use Filament\Resources\Pages\ListRecords;

class ListReservations extends ListRecords
{
    protected static string $resource = ReservationResource::class;

    protected static ?string $title = 'My cart';
}
