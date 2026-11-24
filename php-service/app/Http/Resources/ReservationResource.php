<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Reservation
 */
class ReservationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'quantity' => $this->quantity,
            'status' => $this->status,
            'expires_at' => $this->expires_at->toIso8601String(),
            'seconds_remaining' => max(0, (int) now()->diffInSeconds($this->expires_at, false)),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
