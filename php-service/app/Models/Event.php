<?php

namespace App\Models;

use App\Enums\EventStatus;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'event_date',
        'ticket_price',
        'total_tickets',
        'available_tickets',
    ];

    protected $attributes = [
        'status' => 'draft',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'immutable_datetime',
            'ticket_price' => 'decimal:2',
            'total_tickets' => 'integer',
            'available_tickets' => 'integer',
            'status' => EventStatus::class,
        ];
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(TicketSale::class);
    }

    public function isOnSale(): bool
    {
        return $this->status === EventStatus::Published && $this->event_date->isFuture();
    }

    public function scopePublished(Builder $query): void
    {
        $query->where('status', EventStatus::Published);
    }

    public function isVisibleTo(?User $user): bool
    {
        return $this->status === EventStatus::Published || $user?->isAdmin();
    }

    // Entradas ya vendidas o retenidas en reservas activas
    public function committedTickets(): int
    {
        return $this->total_tickets - $this->available_tickets;
    }
}
