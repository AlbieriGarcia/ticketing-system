<?php

namespace App\Filament\Resources\Events\Tables;

use App\Enums\EventStatus;
use App\Exceptions\DomainException;
use App\Models\Event;
use App\Services\EventService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class EventsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('event_date')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->weight('medium')
                    ->description(fn (Event $record) => str($record->description)->limit(60)),
                TextColumn::make('event_date')->dateTime('M j, Y · H:i')->sortable(),
                TextColumn::make('ticket_price')->money('USD')->sortable(),
                TextColumn::make('available_tickets')
                    ->label('Availability')
                    ->formatStateUsing(fn (Event $record) => "{$record->available_tickets} / {$record->total_tickets}")
                    ->color(fn (Event $record) => $record->available_tickets === 0 ? 'danger' : null),
                TextColumn::make('status')->badge(),
            ])
            ->filters([
                SelectFilter::make('status')->options(EventStatus::class),
            ])
            ->recordActions([
                EditAction::make()->visible(fn (Event $record) => $record->status->isEditable()),
                ActionGroup::make([
                    self::statusAction(EventStatus::Published, 'Publish', Heroicon::OutlinedCheckCircle, 'success'),
                    self::statusAction(EventStatus::Finished, 'Mark as finished', Heroicon::OutlinedFlag, 'info'),
                    self::statusAction(EventStatus::Cancelled, 'Cancel event', Heroicon::OutlinedXCircle, 'danger'),
                ]),
            ]);
    }

    // Cada acción solo aparece si la máquina de estados permite la transición, y delega en EventService
    private static function statusAction(EventStatus $target, string $label, Heroicon $icon, string $color): Action
    {
        return Action::make("mark_{$target->value}")
            ->label($label)
            ->icon($icon)
            ->color($color)
            ->requiresConfirmation()
            ->visible(fn (Event $record) => $record->status->canTransitionTo($target))
            ->action(function (Event $record) use ($target) {
                try {
                    app(EventService::class)->changeStatus($record, $target);
                    Notification::make()->success()->title("Event {$target->getLabel()}")->send();
                } catch (DomainException $e) {
                    Notification::make()->danger()->title($e->getMessage())->send();
                }
            });
    }
}
