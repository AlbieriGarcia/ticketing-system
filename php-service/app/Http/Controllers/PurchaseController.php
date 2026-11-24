<?php

namespace App\Http\Controllers;

use App\Http\Resources\TicketSaleResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PurchaseController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $sales = $request->user()
            ->sales()
            ->with('event:id,name,event_date')
            ->latest('purchase_date')
            ->paginate(max(1, min((int) $request->query('per_page', 15), 50)));

        return TicketSaleResource::collection($sales);
    }
}
