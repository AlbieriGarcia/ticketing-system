<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    description: 'Ticketing API: authentication, events, reservations and ticket sales.',
    title: 'Ticketing API',
)]
#[OA\Server(url: '/api', description: 'Current host')]
#[OA\SecurityScheme(securityScheme: 'bearerAuth', type: 'http', bearerFormat: 'Sanctum token', scheme: 'bearer')]
#[OA\Tag(name: 'Auth', description: 'Registration, login and profile')]
#[OA\Tag(name: 'Events', description: 'Event management and availability')]
#[OA\Schema(
    schema: 'User',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Ana Pérez'),
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'ana@example.com'),
        new OA\Property(property: 'role', type: 'string', enum: ['admin', 'customer']),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ],
)]
#[OA\Schema(
    schema: 'Event',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Rock Festival'),
        new OA\Property(property: 'description', type: 'string', example: 'Annual rock festival'),
        new OA\Property(property: 'event_date', type: 'string', format: 'date-time'),
        new OA\Property(property: 'ticket_price', type: 'string', example: '30000.00'),
        new OA\Property(property: 'total_tickets', type: 'integer', example: 500),
        new OA\Property(property: 'available_tickets', type: 'integer', example: 500),
        new OA\Property(property: 'status', type: 'string', enum: ['draft', 'published', 'cancelled', 'finished']),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ],
)]
#[OA\Schema(
    schema: 'Error',
    properties: [new OA\Property(property: 'message', type: 'string', example: 'Resource not found.')],
)]
#[OA\Schema(
    schema: 'ValidationError',
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'The given data was invalid.'),
        new OA\Property(property: 'errors', type: 'object', example: ['email' => ['The email field is required.']]),
    ],
)]
#[OA\Response(response: 'Unauthorized', description: 'Missing or invalid token', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
#[OA\Response(response: 'Forbidden', description: 'Authenticated user lacks permission', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
#[OA\Response(response: 'NotFound', description: 'Resource not found', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
#[OA\Response(response: 'Conflict', description: 'Operation conflicts with current state', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
#[OA\Response(response: 'ValidationFailed', description: 'Invalid input', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError'))]
class ApiSpec
{
}
