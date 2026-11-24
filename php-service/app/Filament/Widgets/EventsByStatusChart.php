<?php

namespace App\Filament\Widgets;

use App\Enums\EventStatus;
use App\Exceptions\StatsUnavailableException;
use App\Services\StatsClient;
use Filament\Widgets\ChartWidget;

class EventsByStatusChart extends ChartWidget
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'Events by status';

    protected ?string $maxHeight = '240px';

    private const COLORS = [
        'draft' => '#94a3b8',
        'published' => '#22c55e',
        'cancelled' => '#ef4444',
        'finished' => '#6366f1',
    ];

    protected function getData(): array
    {
        try {
            $rows = collect(app(StatsClient::class)->summary()['events_by_status']);
        } catch (StatsUnavailableException) {
            $rows = collect();
        }

        return [
            'datasets' => [[
                'data' => $rows->pluck('events')->all(),
                'backgroundColor' => $rows->map(fn ($row) => self::COLORS[$row['status']] ?? '#cbd5e1')->all(),
                'borderWidth' => 0,
            ]],
            'labels' => $rows->map(fn ($row) => EventStatus::tryFrom($row['status'])?->getLabel() ?? $row['status'])->all(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['position' => 'bottom']],
            'scales' => ['x' => ['display' => false], 'y' => ['display' => false]],
        ];
    }
}
