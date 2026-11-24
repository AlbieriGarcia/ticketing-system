<?php

namespace App\Filament\Resources\Users\Tables;

use App\Enums\UserRole;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('sales')->withSum('sales', 'total_amount'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->searchable()->weight('medium')->description(fn ($record) => $record->email),
                TextColumn::make('role')->badge(),
                TextColumn::make('sales_count')->label('Purchases')->alignCenter()->sortable(),
                TextColumn::make('sales_sum_total_amount')->label('Total spent')->money('USD')->sortable()->placeholder('—'),
                TextColumn::make('created_at')->label('Joined')->date('M j, Y')->sortable(),
            ])
            ->filters([
                SelectFilter::make('role')->options(UserRole::class),
            ]);
    }
}
