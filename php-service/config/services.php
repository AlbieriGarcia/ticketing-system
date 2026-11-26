<?php

return [

    // Servicios externos: estadísticas (.NET) y broker de mensajería (RabbitMQ)

    'stats' => [
        'url' => env('STATS_API_URL', 'http://stats-api:5000'),
        'key' => env('STATS_API_KEY'),
    ],

    'rabbitmq' => [
        'host' => env('RABBITMQ_HOST', 'rabbitmq'),
        'port' => (int) env('RABBITMQ_PORT', 5672),
        'user' => env('RABBITMQ_USER', 'tickets'),
        'password' => env('RABBITMQ_PASSWORD', 'secret'),
        'vhost' => env('RABBITMQ_VHOST', '/'),
        'exchange' => env('RABBITMQ_EXCHANGE', 'ticketing'),
    ],

];
