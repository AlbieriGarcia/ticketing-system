<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

enum EventStatus: string implements HasColor, HasIcon, HasLabel
{
    case Draft = 'draft';
    case Published = 'published';
    case Cancelled = 'cancelled';
    case Finished = 'finished';

    // Máquina de estados: única fuente de verdad sobre qué transiciones están permitidas
    public function canTransitionTo(self $next): bool
    {
        return in_array($next, match ($this) {
            self::Draft => [self::Published, self::Cancelled],
            self::Published => [self::Cancelled, self::Finished],
            self::Cancelled, self::Finished => [],
        }, true);
    }

    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::Published], true);
    }

    public function getLabel(): string
    {
        return ucfirst($this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Published => 'success',
            self::Cancelled => 'danger',
            self::Finished => 'info',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::Draft => Heroicon::OutlinedPencilSquare,
            self::Published => Heroicon::OutlinedCheckCircle,
            self::Cancelled => Heroicon::OutlinedXCircle,
            self::Finished => Heroicon::OutlinedFlag,
        };
    }
}
