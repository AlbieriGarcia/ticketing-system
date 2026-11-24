<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
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

        if (Event::query()->doesntExist()) {
            Event::factory()->published()->count(5)->create();
            Event::factory()->count(2)->create();
        }
    }
}
