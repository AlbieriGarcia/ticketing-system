<?php

namespace App\Filament\Store\Resources\Events\Pages;

use App\Exceptions\ReservationException;
use App\Filament\Store\Resources\Events\EventResource;
use App\Filament\Store\Resources\Reservations\ReservationResource;
use App\Models\Event;
use App\Services\ReservationService;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewEvent extends ViewRecord
{
    protected static string $resource = EventResource::class;

    public function getTitle(): string
    {
        return $this->getRecord()->name;
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->reserveAction(),
            $this->signInToReserveAction(),
        ];
    }

    private function reserveAction(): Action
    {
        return Action::make('reserve')
            ->label('Reserve tickets')
            ->icon(Heroicon::OutlinedTicket)
            ->visible(fn () => auth()->check())
            ->disabled(fn (Event $record) => $record->available_tickets === 0)
            ->modalDescription('Your tickets are held for '.config('tickets.reservation_ttl_minutes').' minutes while you complete the purchase.')
            ->modalSubmitActionLabel('Reserve')
            ->schema(fn (Event $record) => [
                TextInput::make('quantity')
                    ->integer()
                    ->required()
                    ->default(1)
                    ->minValue(1)
                    ->maxValue(min(10, $record->available_tickets)),
            ])
            ->action(function (Event $record, array $data, Action $action) {
                try {
                    app(ReservationService::class)->reserve(auth()->user(), $record, (int) $data['quantity']);
                } catch (ReservationException $e) {
                    Notification::make()->danger()->title($e->getMessage())->send();
                    $action->halt();
                }

                Notification::make()->success()->title('Tickets reserved')->send();
                $this->redirect(ReservationResource::getUrl());
            });
    }

    // El invitado inicia sesión y vuelve a este mismo evento para reservar
    private function signInToReserveAction(): Action
    {
        return Action::make('signInToReserve')
            ->label('Sign in to reserve')
            ->icon(Heroicon::OutlinedTicket)
            ->visible(fn () => auth()->guest())
            ->action(function (Event $record) {
                session()->put('url.intended', EventResource::getUrl('view', ['record' => $record]));
                $this->redirect(filament()->getLoginUrl());
            });
    }
}
