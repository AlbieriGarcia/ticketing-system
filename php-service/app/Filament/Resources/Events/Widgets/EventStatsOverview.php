<?php

namespace App\Filament\Resources\Events\Widgets;

use App\Exceptions\StatsUnavailableException;
use App\Models\Event;
use App\Services\StatsClient;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

class EventStatsOverview extends StatsOverviewWidget
{
    public ?Event $record = null;

    protected function getStats(): array
    {
        try {
            $stats = app(StatsClient::class)->eventStats($this->record->id);
        } catch (StatsUnavailableException) {
            return [Stat::make('Statistics', 'Unavailable')->description('Temporarily unavailable, please try again later')->color('danger')];
        }

        if ($stats === null) {
            return [Stat::make('Statistics', '—')->description('Not available for draft events')];
        }

        $tickets = $stats['tickets'];
        $daily = collect($stats['daily_sales'])->pluck('tickets')->all();

        return [
            Stat::make('Occupancy', "{$tickets['occupancy_percent']}%")
                ->description("{$tickets['sold']} of {$tickets['total']} sold")
                ->descriptionIcon(Heroicon::OutlinedChartPie)
                ->color('primary'),
            Stat::make('Revenue', Number::currency($stats['sales']['revenue']))
                ->description("{$stats['sales']['orders']} orders")
                ->chart($daily ?: [0])
                ->color('success'),
            Stat::make('On hold', $tickets['on_hold'])
                ->description('In active reservations')
                ->descriptionIcon(Heroicon::OutlinedClock),
            Stat::make('Days until event', $stats['days_until_event'])
                ->descriptionIcon(Heroicon::OutlinedCalendarDays)
                ->description($this->record->event_date->format('M j, Y')),
        ];
    }
}
