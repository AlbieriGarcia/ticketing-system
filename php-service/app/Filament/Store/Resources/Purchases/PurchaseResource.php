<?php

namespace App\Filament\Store\Resources\Purchases;

use App\Filament\Store\Resources\Purchases\Pages\ListPurchases;
use App\Models\TicketSale;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PurchaseResource extends Resource
{
    protected static ?string $model = TicketSale::class;

    protected static ?string $slug = 'purchases';

    protected static ?string $navigationLabel = 'My purchases';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static ?int $navigationSort = 3;

    public static function canAccess(): bool
    {
        return auth()->check();
    }

    // Cada usuario ve únicamente su propio historial
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with('event:id,name,event_date')
            ->whereBelongsTo(auth()->user());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('purchase_date', 'desc')
            ->columns([
                TextColumn::make('event.name')
                    ->label('Event')
                    ->searchable()
                    ->weight(FontWeight::SemiBold)
                    ->description(fn (TicketSale $record) => $record->event->event_date->format('D, M j, Y · H:i')),
                TextColumn::make('quantity')
                    ->label('Tickets')
                    ->alignCenter(),
                TextColumn::make('unit_price')
                    ->money('USD')
                    ->color('gray'),
                TextColumn::make('total_amount')
                    ->label('Total')
                    ->money('USD')
                    ->weight(FontWeight::Bold),
                TextColumn::make('purchase_date')
                    ->label('Purchased')
                    ->dateTime('M j, Y · H:i')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge(),
            ])
            ->emptyStateHeading('No purchases yet')
            ->emptyStateDescription('Tickets you buy will appear here.')
            ->emptyStateIcon(Heroicon::OutlinedReceiptPercent);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPurchases::route('/'),
        ];
    }
}
