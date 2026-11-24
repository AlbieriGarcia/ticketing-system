<?php

namespace Database\Factories;

use App\Enums\EventStatus;
use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    public function definition(): array
    {
        $total = fake()->numberBetween(50, 500);

        return [
            'name' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'event_date' => now()->addMonth(),
            'ticket_price' => fake()->randomFloat(2, 10, 200),
            'total_tickets' => $total,
            'available_tickets' => $total,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => ['status' => EventStatus::Published]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => ['status' => EventStatus::Cancelled]);
    }
}
