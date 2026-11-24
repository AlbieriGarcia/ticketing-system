<?php

namespace Database\Seeders;

use App\Enums\EventStatus;
use App\Enums\UserRole;
use App\Models\Event;
use App\Models\User;
use App\Services\ReservationService;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    private const EVENTS = [
        ['Los Prisioneros en vivo', 'Reunion tour at the national stadium.', 26, 45.00, 500, EventStatus::Published],
        ['Jazz Night at the Opera', 'An evening of classic and modern jazz.', 12, 60.00, 120, EventStatus::Published],
        ['Tech Summit 2026', 'Talks on cloud, AI and distributed systems.', 40, 150.00, 300, EventStatus::Published],
        ['Stand-up Comedy Marathon', 'Six comedians, one night.', 8, 25.00, 200, EventStatus::Published],
        ['Symphony Under the Stars', 'Open-air concert by the city orchestra.', 60, 35.00, 800, EventStatus::Draft],
        ['Indie Rock Festival', 'Cancelled due to weather conditions.', 18, 55.00, 400, EventStatus::Cancelled],
    ];

    public function run(ReservationService $reservations): void
    {
        $admin = User::firstOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@tickets.test')],
            ['name' => 'Admin', 'password' => env('ADMIN_PASSWORD', 'admin1234')],
        );
        $admin->forceFill(['role' => UserRole::Admin])->save();

        User::firstOrCreate(
            ['email' => 'customer@tickets.test'],
            ['name' => 'Demo Customer', 'password' => 'customer1234'],
        );

        if (Event::query()->exists()) {
            return;
        }

        $customers = User::factory()->count(25)->create();

        foreach (self::EVENTS as [$name, $description, $daysAhead, $price, $tickets, $status]) {
            $event = Event::create([
                'name' => $name,
                'description' => $description,
                'event_date' => now()->addDays($daysAhead)->setTime(21, 0),
                'ticket_price' => $price,
                'total_tickets' => $tickets,
                'available_tickets' => $tickets,
            ]);

            // Las ventas demo pasan por el servicio real para respetar stock, precios y transacciones
            if ($status !== EventStatus::Draft) {
                $event->forceFill(['status' => EventStatus::Published])->save();

                foreach ($customers->random(random_int(8, 20)) as $customer) {
                    $reservation = $reservations->reserve($customer, $event, random_int(1, 4));
                    $sale = $reservations->confirm($reservation);
                    $sale->forceFill(['purchase_date' => now()->subDays(random_int(0, 6))->subMinutes(random_int(0, 600))])->save();
                }
            }

            $event->forceFill(['status' => $status])->save();
        }
    }
}
