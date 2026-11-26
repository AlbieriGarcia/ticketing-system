<?php

use App\Models\OutboxMessage;
use Illuminate\Support\Facades\Schedule;

Schedule::command('reservations:expire')->everyMinute()->withoutOverlapping();
Schedule::command('model:prune', ['--model' => [OutboxMessage::class]])->daily();
Schedule::command('outbox:monitor')->everyMinute()->withoutOverlapping();
