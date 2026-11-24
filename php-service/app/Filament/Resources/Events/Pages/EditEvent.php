<?php

namespace App\Filament\Resources\Events\Pages;

use App\Exceptions\DomainException;
use App\Filament\Resources\Events\EventResource;
use App\Filament\Resources\Events\Widgets\EventStatsOverview;
use App\Services\EventService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditEvent extends EditRecord
{
    protected static string $resource = EventResource::class;

    protected function getHeaderWidgets(): array
    {
        return [EventStatsOverview::class];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return app(EventService::class)->update($record, $data);
        } catch (DomainException $e) {
            Notification::make()->danger()->title($e->getMessage())->send();
            $this->halt();
        }
    }
}
