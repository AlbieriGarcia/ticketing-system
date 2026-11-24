<?php

namespace App\Filament\Store\Resources\Purchases\Pages;

use App\Filament\Store\Resources\Purchases\PurchaseResource;
use Filament\Resources\Pages\ListRecords;

class ListPurchases extends ListRecords
{
    protected static string $resource = PurchaseResource::class;

    protected static ?string $title = 'My purchases';
}
