<?php

namespace Tests\Unit;

use App\Enums\EventStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class EventStatusTest extends TestCase
{
    public static function transitions(): array
    {
        return [
            'draft -> published' => [EventStatus::Draft, EventStatus::Published, true],
            'draft -> cancelled' => [EventStatus::Draft, EventStatus::Cancelled, true],
            'draft -> finished' => [EventStatus::Draft, EventStatus::Finished, false],
            'published -> cancelled' => [EventStatus::Published, EventStatus::Cancelled, true],
            'published -> finished' => [EventStatus::Published, EventStatus::Finished, true],
            'published -> draft' => [EventStatus::Published, EventStatus::Draft, false],
            'cancelled -> published' => [EventStatus::Cancelled, EventStatus::Published, false],
            'finished -> published' => [EventStatus::Finished, EventStatus::Published, false],
        ];
    }

    #[DataProvider('transitions')]
    public function test_transitions(EventStatus $from, EventStatus $to, bool $allowed): void
    {
        $this->assertSame($allowed, $from->canTransitionTo($to));
    }
}
