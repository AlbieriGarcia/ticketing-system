<?php

namespace Tests\Feature;

use App\Filament\Store\Resources\Purchases\Pages\ListPurchases;
use App\Models\Reservation;
use App\Models\User;
use App\Services\ReservationService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StorePurchasesTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_sees_only_own_purchases(): void
    {
        $user = User::factory()->create();
        $service = app(ReservationService::class);

        $mine = $service->confirm(Reservation::factory()->for($user)->create());
        $foreign = $service->confirm(Reservation::factory()->create());

        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('store'));

        Livewire::test(ListPurchases::class)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$foreign]);
    }

    public function test_guest_cannot_open_purchases(): void
    {
        $this->get('/purchases')->assertRedirect('/login');
    }
}
