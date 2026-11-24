<?php

namespace App\Filament\Store\Resources\Events\Pages;

use App\Filament\Store\Resources\Events\EventResource;
use Filament\Resources\Pages\ListRecords;

class ListEvents extends ListRecords
{
    protected static string $resource = EventResource::class;

    protected static ?string $title = 'Upcoming events';
}
