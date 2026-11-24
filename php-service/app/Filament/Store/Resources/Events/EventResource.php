<?php

namespace App\Filament\Store\Resources\Events;

use App\Filament\Store\Resources\Events\Pages\ListEvents;
use App\Filament\Store\Resources\Events\Pages\ViewEvent;
use App\Models\Event;
use BackedEnum;
use Filament\Http\Middleware\Authenticate;
use Filament\Infolists\Components\TextEntry;
use Filament\Panel;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EventResource extends Resource
{
    protected static ?string $model = Event::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 1;

    // El catálogo es público: se navega sin iniciar sesión
    protected static string|array $withoutRouteMiddleware = Authenticate::class;

    // Sin prefijo, el listado queda en la raíz y es la portada de la tienda
    public static function getRoutePrefix(Panel $panel): string
    {
        return '';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->published()
            ->where('event_date', '>', now())
            ->orderBy('event_date');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Stack::make([
                    TextColumn::make('name')
                        ->searchable()
                        ->weight(FontWeight::SemiBold)
                        ->size(TextSize::Large),
                    TextColumn::make('event_date')
                        ->dateTime('D, M j, Y · H:i')
                        ->icon(Heroicon::OutlinedCalendarDays)
                        ->color('gray'),
                    TextColumn::make('description')
                        ->limit(90)
                        ->color('gray'),
                    TextColumn::make('ticket_price')
                        ->money('USD')
                        ->weight(FontWeight::Bold)
                        ->color('primary'),
                    TextColumn::make('available_tickets')
                        ->badge()
                        ->formatStateUsing(fn (int $state) => $state > 0 ? "{$state} tickets left" : 'Sold out')
                        ->color(fn (int $state) => $state > 0 ? 'success' : 'danger'),
                ])->space(2),
            ])
            ->contentGrid(['md' => 2, 'xl' => 3])
            ->paginated([12, 24])
            ->emptyStateHeading('No upcoming events')
            ->emptyStateIcon(Heroicon::OutlinedTicket);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columnSpanFull()
                ->columns(3)
                ->schema([
                    TextEntry::make('event_date')
                        ->label('Date')
                        ->dateTime('l, F j, Y · H:i')
                        ->icon(Heroicon::OutlinedCalendarDays),
                    TextEntry::make('ticket_price')
                        ->label('Price per ticket')
                        ->money('USD')
                        ->weight(FontWeight::Bold),
                    TextEntry::make('available_tickets')
                        ->label('Availability')
                        ->badge()
                        ->formatStateUsing(fn (Event $record) => $record->available_tickets > 0
                            ? "{$record->available_tickets} of {$record->total_tickets} left"
                            : 'Sold out')
                        ->color(fn (int $state) => $state > 0 ? 'success' : 'danger'),
                    TextEntry::make('description')
                        ->hiddenLabel()
                        ->columnSpanFull()
                        ->placeholder('No description'),
                ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEvents::route('/'),
            'view' => ViewEvent::route('/events/{record}'),
        ];
    }
}
