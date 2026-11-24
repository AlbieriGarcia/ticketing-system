<?php

namespace App\Filament\Store\Resources\Events\Pages;

use App\Filament\Store\Resources\Events\EventResource;
use Filament\Resources\Pages\ViewRecord;

class ViewEvent extends ViewRecord
{
    protected static string $resource = EventResource::class;

    public function getTitle(): string
    {
        return $this->getRecord()->name;
    }
}
