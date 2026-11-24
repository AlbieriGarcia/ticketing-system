<?php

namespace App\Filament\Store\Resources\Reservations;

use App\Enums\ReservationStatus;
use App\Exceptions\ReservationException;
use App\Filament\Store\Resources\Reservations\Pages\ListReservations;
use App\Models\Reservation;
use App\Services\ReservationService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

class ReservationResource extends Resource
{
    protected static ?string $model = Reservation::class;

    protected static ?string $slug = 'cart';

    protected static ?string $navigationLabel = 'My cart';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        return auth()->check();
    }

    // El carrito son las reservas pendientes y vigentes del usuario; las vencidas las libera el scheduler
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with('event')
            ->whereBelongsTo(auth()->user())
            ->where('status', ReservationStatus::Pending)
            ->where('expires_at', '>', now())
            ->latest();
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('event.name')
                    ->label('Event')
                    ->weight(FontWeight::SemiBold)
                    ->description(fn (Reservation $record) => $record->event->event_date->format('D, M j, Y · H:i')),
                TextColumn::make('quantity')
                    ->label('Tickets')
                    ->alignCenter(),
                TextColumn::make('total')
                    ->state(fn (Reservation $record) => bcmul($record->event->ticket_price, (string) $record->quantity, 2))
                    ->money('USD')
                    ->weight(FontWeight::Bold),
                ViewColumn::make('expires_at')
                    ->label('Expires in')
                    ->view('filament.store.countdown'),
            ])
            ->recordActions([
                Action::make('confirm')
                    ->label('Buy now')
                    ->icon(Heroicon::OutlinedCreditCard)
                    ->button()
                    ->authorize('manage')
                    ->requiresConfirmation()
                    ->modalHeading('Confirm purchase')
                    ->modalDescription(fn (Reservation $record) => "{$record->quantity} ticket(s) for {$record->event->name}.")
                    ->action(fn (Reservation $record, Component $livewire) => self::run(
                        $livewire,
                        fn () => app(ReservationService::class)->confirm($record),
                        'Purchase completed',
                    )),
                Action::make('cancel')
                    ->label('Remove')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->authorize('manage')
                    ->requiresConfirmation()
                    ->action(fn (Reservation $record, Component $livewire) => self::run(
                        $livewire,
                        fn () => app(ReservationService::class)->cancel($record),
                        'Reservation cancelled',
                    )),
            ])
            ->paginated(false)
            ->emptyStateHeading('Your cart is empty')
            ->emptyStateDescription('Reserve tickets from an event page and they will appear here.')
            ->emptyStateIcon(Heroicon::OutlinedShoppingCart);
    }

    // Ejecuta la operación del servicio, notifica el resultado y refresca el contador del carrito en la barra superior
    private static function run(Component $livewire, callable $operation, string $successMessage): void
    {
        try {
            $operation();
            Notification::make()->success()->title($successMessage)->send();
            $livewire->dispatch('refresh-topbar');
        } catch (ReservationException $e) {
            Notification::make()->danger()->title($e->getMessage())->send();
        }
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReservations::route('/'),
        ];
    }
}
