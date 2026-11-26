<?php

namespace App\Providers;

use App\Messaging\AmqpPublisher;
use App\Messaging\MessagePublisher;
use App\Observability\LivewireSpanName;
use App\Observability\TraceContext;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Singleton: el relay reutiliza la misma conexión AMQP entre lotes
        $this->app->singleton(MessagePublisher::class, fn () => new AmqpPublisher(config('services.rabbitmq')));

        // Fuera de una request (comandos, scheduler) cada proceso empieza su propia traza; el middleware la reemplaza en HTTP
        $this->app->scoped(TraceContext::class, fn () => TraceContext::generate());
    }

    public function boot(): void
    {
        LivewireSpanName::register();
    }
}
