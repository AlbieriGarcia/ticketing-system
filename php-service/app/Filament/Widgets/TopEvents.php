<?php

namespace App\Filament\Widgets;

use App\Exceptions\StatsUnavailableException;
use App\Services\StatsClient;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class TopEvents extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = ['md' => 2];

    public function table(Table $table): Table
    {
        return $table
            ->heading('Top events by revenue')
            ->records(fn () => $this->topEvents())
            ->paginated(false)
            ->emptyStateHeading('No sales yet, or statistics are unavailable')
            ->columns([
                TextColumn::make('name')->weight('medium')->limit(45),
                TextColumn::make('tickets_sold')->label('Tickets')->alignCenter(),
                TextColumn::make('revenue')->money('USD'),
                TextColumn::make('occupancy_percent')->label('Occupancy')->suffix('%')->badge()->color('primary'),
            ]);
    }

    private function topEvents(): array
    {
        try {
            return collect(app(StatsClient::class)->summary()['top_events'])->keyBy('event_id')->all();
        } catch (StatsUnavailableException) {
            return [];
        }
    }
}
