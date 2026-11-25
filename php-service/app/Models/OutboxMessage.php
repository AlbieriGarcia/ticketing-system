<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;

class OutboxMessage extends Model
{
    use Prunable;

    public $timestamps = false;

    protected $fillable = [
        'message_id',
        'type',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'created_at' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
        ];
    }

    public function scopePending(Builder $query): void
    {
        $query->whereNull('published_at');
    }

    // Los ya publicados solo sirven para auditoría reciente
    public function prunable(): Builder
    {
        return static::query()->where('published_at', '<', now()->subDays(7));
    }
}
