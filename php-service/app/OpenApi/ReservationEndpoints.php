<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Reservations', description: 'Temporary ticket holds (10 minutes) and purchase confirmation')]
#[OA\Tag(name: 'Purchases', description: 'Purchase history of the authenticated user')]
#[OA\Schema(
    schema: 'Reservation',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'event_id', type: 'integer', example: 1),
        new OA\Property(property: 'quantity', type: 'integer', example: 2),
        new OA\Property(property: 'status', type: 'string', enum: ['pending', 'confirmed', 'expired', 'cancelled']),
        new OA\Property(property: 'expires_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'seconds_remaining', type: 'integer', example: 600),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ],
)]
#[OA\Schema(
    schema: 'TicketSale',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'reservation_id', type: 'integer', example: 1),
        new OA\Property(property: 'quantity', type: 'integer', example: 2),
        new OA\Property(property: 'unit_price', type: 'string', example: '30000.00'),
        new OA\Property(property: 'total_amount', type: 'string', example: '60000.00'),
        new OA\Property(property: 'status', type: 'string', enum: ['completed', 'refunded']),
        new OA\Property(property: 'purchase_date', type: 'string', format: 'date-time'),
        new OA\Property(property: 'event', properties: [
            new OA\Property(property: 'id', type: 'integer'),
            new OA\Property(property: 'name', type: 'string'),
            new OA\Property(property: 'event_date', type: 'string', format: 'date-time'),
        ], type: 'object'),
    ],
)]
class ReservationEndpoints
{
    #[OA\Post(
        path: '/events/{event}/reservations',
        summary: 'Reserve tickets for 10 minutes (stock is held until confirmed, cancelled or expired)',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['quantity'],
            properties: [new OA\Property(property: 'quantity', type: 'integer', maximum: 10, minimum: 1, example: 2)],
        )),
        tags: ['Reservations'],
        parameters: [new OA\Parameter(name: 'event', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 201, description: 'Reservation created', content: new OA\JsonContent(
                properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Reservation')],
            )),
            new OA\Response(ref: '#/components/responses/Unauthorized', response: 401),
            new OA\Response(ref: '#/components/responses/NotFound', response: 404),
            new OA\Response(response: 409, description: 'Not enough tickets, event not on sale, or active reservation already exists', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
            new OA\Response(ref: '#/components/responses/ValidationFailed', response: 422),
        ],
    )]
    public function store(): void
    {
    }

    #[OA\Get(
        path: '/reservations/{reservation}',
        summary: 'Get one of your reservations',
        security: [['bearerAuth' => []]],
        tags: ['Reservations'],
        parameters: [new OA\Parameter(name: 'reservation', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Reservation', content: new OA\JsonContent(
                properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Reservation')],
            )),
            new OA\Response(ref: '#/components/responses/Unauthorized', response: 401),
            new OA\Response(ref: '#/components/responses/Forbidden', response: 403),
            new OA\Response(ref: '#/components/responses/NotFound', response: 404),
        ],
    )]
    public function show(): void
    {
    }

    #[OA\Post(
        path: '/reservations/{reservation}/confirm',
        summary: 'Confirm a pending reservation and register the purchase',
        security: [['bearerAuth' => []]],
        tags: ['Reservations'],
        parameters: [new OA\Parameter(name: 'reservation', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 201, description: 'Purchase registered', content: new OA\JsonContent(
                properties: [new OA\Property(property: 'data', ref: '#/components/schemas/TicketSale')],
            )),
            new OA\Response(ref: '#/components/responses/Unauthorized', response: 401),
            new OA\Response(ref: '#/components/responses/Forbidden', response: 403),
            new OA\Response(ref: '#/components/responses/NotFound', response: 404),
            new OA\Response(response: 409, description: 'Reservation expired, not pending, or event no longer on sale', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ],
    )]
    public function confirm(): void
    {
    }

    #[OA\Post(
        path: '/reservations/{reservation}/cancel',
        summary: 'Cancel a pending reservation and release its tickets',
        security: [['bearerAuth' => []]],
        tags: ['Reservations'],
        parameters: [new OA\Parameter(name: 'reservation', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 204, description: 'Reservation cancelled'),
            new OA\Response(ref: '#/components/responses/Unauthorized', response: 401),
            new OA\Response(ref: '#/components/responses/Forbidden', response: 403),
            new OA\Response(ref: '#/components/responses/NotFound', response: 404),
            new OA\Response(ref: '#/components/responses/Conflict', response: 409),
        ],
    )]
    public function cancel(): void
    {
    }

    #[OA\Get(
        path: '/me/purchases',
        summary: 'Purchase history of the authenticated user',
        security: [['bearerAuth' => []]],
        tags: ['Purchases'],
        parameters: [
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', maximum: 50, minimum: 1)),
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Paginated purchases', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/TicketSale')),
                new OA\Property(property: 'links', type: 'object'),
                new OA\Property(property: 'meta', type: 'object'),
            ])),
            new OA\Response(ref: '#/components/responses/Unauthorized', response: 401),
        ],
    )]
    public function purchases(): void
    {
    }
}
