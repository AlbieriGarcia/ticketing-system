<?php

namespace App\Filament\Resources\TicketSales;

use App\Filament\Resources\TicketSales\Pages\ListTicketSales;
use App\Filament\Resources\TicketSales\Tables\TicketSalesTable;
use App\Models\TicketSale;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class TicketSaleResource extends Resource
{
    protected static ?string $model = TicketSale::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static string|UnitEnum|null $navigationGroup = 'Sales';

    protected static ?string $navigationLabel = 'Sales';

    protected static ?string $pluralModelLabel = 'Sales';

    public static function table(Table $table): Table
    {
        return TicketSalesTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTicketSales::route('/'),
        ];
    }
}
