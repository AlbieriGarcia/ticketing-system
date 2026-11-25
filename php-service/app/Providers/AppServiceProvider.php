<?php

namespace App\Providers;

use App\Messaging\AmqpPublisher;
use App\Messaging\MessagePublisher;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Singleton: el relay reutiliza la misma conexión AMQP entre lotes
        $this->app->singleton(MessagePublisher::class, fn () => new AmqpPublisher(config('services.rabbitmq')));
    }

    public function boot(): void
    {
        //
    }
}
