<?php

namespace App\Filament\Resources\Events\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EventForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('Details')
                    ->columnSpan(2)
                    ->schema([
                        TextInput::make('name')->required()->maxLength(200),
                        Textarea::make('description')->required()->rows(6)->maxLength(5000),
                    ]),
                Section::make('Tickets & schedule')
                    ->columnSpan(1)
                    ->schema([
                        DateTimePicker::make('event_date')->required()->seconds(false)->after('now'),
                        TextInput::make('ticket_price')->required()->numeric()->minValue(0)->prefix('$'),
                        TextInput::make('total_tickets')
                            ->required()
                            ->integer()
                            ->minValue(1)
                            ->helperText('Sold and reserved tickets are kept when this changes.'),
                    ]),
            ]);
    }
}
