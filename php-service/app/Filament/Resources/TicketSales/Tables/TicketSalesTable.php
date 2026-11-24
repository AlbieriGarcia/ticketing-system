<?php

namespace App\Filament\Resources\TicketSales\Tables;

use App\Enums\SaleStatus;
use App\Models\TicketSale;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TicketSalesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['user:id,name,email', 'event:id,name']))
            ->defaultSort('purchase_date', 'desc')
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),
                TextColumn::make('purchase_date')->dateTime('M j, Y · H:i')->sortable(),
                TextColumn::make('user.name')
                    ->label('Customer')
                    ->description(fn (TicketSale $record) => $record->user->email)
                    ->searchable(),
                TextColumn::make('event.name')->label('Event')->searchable()->limit(40),
                TextColumn::make('quantity')->alignCenter(),
                TextColumn::make('total_amount')->money('USD')->sortable()->weight('medium'),
                TextColumn::make('status')->badge(),
            ])
            ->filters([
                SelectFilter::make('status')->options(SaleStatus::class),
                SelectFilter::make('event')->relationship('event', 'name')->searchable()->preload(),
            ]);
    }
}
