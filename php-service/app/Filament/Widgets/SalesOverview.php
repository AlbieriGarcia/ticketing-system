<?php

namespace App\Filament\Widgets;

use App\Exceptions\StatsUnavailableException;
use App\Services\StatsClient;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

class SalesOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Sales overview';

    protected function getStats(): array
    {
        try {
            $sales = app(StatsClient::class)->summary()['sales'];
        } catch (StatsUnavailableException) {
            return [
                Stat::make('Statistics', 'Unavailable')
                    ->description('Temporarily unavailable, please try again later')
                    ->descriptionIcon(Heroicon::OutlinedExclamationTriangle)
                    ->color('danger'),
            ];
        }

        return [
            Stat::make('Revenue', Number::currency($sales['revenue']))
                ->description('Avg. order '.Number::currency($sales['average_order_value']))
                ->descriptionIcon(Heroicon::OutlinedBanknotes)
                ->color('success'),
            Stat::make('Tickets sold', Number::format($sales['tickets_sold']))
                ->descriptionIcon(Heroicon::OutlinedTicket)
                ->description('Across all events'),
            Stat::make('Orders', Number::format($sales['orders']))
                ->descriptionIcon(Heroicon::OutlinedShoppingBag)
                ->description('Confirmed purchases'),
            Stat::make('Customers', Number::format($sales['customers']))
                ->descriptionIcon(Heroicon::OutlinedUsers)
                ->description('With at least one purchase'),
        ];
    }
}
