<?php

namespace App\Http\Controllers;

use App\Http\Requests\Reservation\StoreReservationRequest;
use App\Http\Resources\ReservationResource;
use App\Http\Resources\TicketSaleResource;
use App\Models\Event;
use App\Models\Reservation;
use App\Services\ReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ReservationController extends Controller
{
    public function __construct(private readonly ReservationService $reservationService)
    {
    }

    public function store(StoreReservationRequest $request, Event $event): JsonResponse
    {
        $reservation = $this->reservationService->reserve(
            $request->user(),
            $event,
            $request->validated('quantity'),
        );

        return (new ReservationResource($reservation))->response()->setStatusCode(201);
    }

    public function show(Reservation $reservation): ReservationResource
    {
        return new ReservationResource($reservation);
    }

    public function confirm(Reservation $reservation): JsonResponse
    {
        $sale = $this->reservationService->confirm($reservation);

        return (new TicketSaleResource($sale->load('event')))->response()->setStatusCode(201);
    }

    public function cancel(Reservation $reservation): Response
    {
        $this->reservationService->cancel($reservation);

        return response()->noContent();
    }
}
