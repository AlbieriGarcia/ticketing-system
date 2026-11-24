<?php

namespace App\Enums;

enum EventStatus: string
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
}
