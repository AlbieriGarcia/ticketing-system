<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'EventInput',
    properties: [
        new OA\Property(property: 'name', type: 'string', maxLength: 200, example: 'Rock Festival'),
        new OA\Property(property: 'description', type: 'string', example: 'Annual rock festival'),
        new OA\Property(property: 'event_date', type: 'string', format: 'date-time', example: '2026-12-20T21:00:00Z'),
        new OA\Property(property: 'ticket_price', type: 'number', format: 'float', minimum: 0, example: 30000),
        new OA\Property(property: 'total_tickets', type: 'integer', minimum: 1, example: 500),
    ],
)]
class EventEndpoints
{
    #[OA\Get(
        path: '/events',
        summary: 'List events (public users only see published events)',
        tags: ['Events'],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'from', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'to', in: 'query', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'status', in: 'query', description: 'Admin only', schema: new OA\Schema(type: 'string', enum: ['draft', 'published', 'cancelled', 'finished'])),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', maximum: 50, minimum: 1)),
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Paginated events', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Event')),
                new OA\Property(property: 'links', type: 'object'),
                new OA\Property(property: 'meta', type: 'object'),
            ])),
            new OA\Response(ref: '#/components/responses/ValidationFailed', response: 422),
        ],
    )]
    public function index(): void
    {
    }

    #[OA\Post(
        path: '/events',
        summary: 'Create an event in draft status (admin)',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            ref: '#/components/schemas/EventInput',
            required: ['name', 'description', 'event_date', 'ticket_price', 'total_tickets'],
        )),
        tags: ['Events'],
        responses: [
            new OA\Response(response: 201, description: 'Event created', content: new OA\JsonContent(
                properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Event')],
            )),
            new OA\Response(ref: '#/components/responses/Unauthorized', response: 401),
            new OA\Response(ref: '#/components/responses/Forbidden', response: 403),
            new OA\Response(ref: '#/components/responses/ValidationFailed', response: 422),
        ],
    )]
    public function store(): void
    {
    }

    #[OA\Get(
        path: '/events/{event}',
        summary: 'Get event detail',
        tags: ['Events'],
        parameters: [new OA\Parameter(name: 'event', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Event detail', content: new OA\JsonContent(
                properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Event')],
            )),
            new OA\Response(ref: '#/components/responses/NotFound', response: 404),
        ],
    )]
    public function show(): void
    {
    }

    #[OA\Put(
        path: '/events/{event}',
        summary: 'Update an event (admin). Changing total_tickets keeps sold/reserved tickets',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/EventInput')),
        tags: ['Events'],
        parameters: [new OA\Parameter(name: 'event', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Event updated', content: new OA\JsonContent(
                properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Event')],
            )),
            new OA\Response(ref: '#/components/responses/Unauthorized', response: 401),
            new OA\Response(ref: '#/components/responses/Forbidden', response: 403),
            new OA\Response(ref: '#/components/responses/NotFound', response: 404),
            new OA\Response(ref: '#/components/responses/Conflict', response: 409),
            new OA\Response(ref: '#/components/responses/ValidationFailed', response: 422),
        ],
    )]
    public function update(): void
    {
    }

    #[OA\Patch(
        path: '/events/{event}/status',
        summary: 'Change event status (admin). Allowed: draft→published|cancelled, published→cancelled|finished',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['status'],
            properties: [new OA\Property(property: 'status', type: 'string', enum: ['draft', 'published', 'cancelled', 'finished'], example: 'published')],
        )),
        tags: ['Events'],
        parameters: [new OA\Parameter(name: 'event', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Status changed', content: new OA\JsonContent(
                properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Event')],
            )),
            new OA\Response(ref: '#/components/responses/Unauthorized', response: 401),
            new OA\Response(ref: '#/components/responses/Forbidden', response: 403),
            new OA\Response(ref: '#/components/responses/NotFound', response: 404),
            new OA\Response(ref: '#/components/responses/Conflict', response: 409),
            new OA\Response(ref: '#/components/responses/ValidationFailed', response: 422),
        ],
    )]
    public function changeStatus(): void
    {
    }

    #[OA\Get(
        path: '/events/{event}/availability',
        summary: 'Get ticket availability',
        tags: ['Events'],
        parameters: [new OA\Parameter(name: 'event', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Availability', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'event_id', type: 'integer', example: 1),
                new OA\Property(property: 'total_tickets', type: 'integer', example: 500),
                new OA\Property(property: 'available_tickets', type: 'integer', example: 120),
                new OA\Property(property: 'is_sold_out', type: 'boolean', example: false),
            ])),
            new OA\Response(ref: '#/components/responses/NotFound', response: 404),
        ],
    )]
    public function availability(): void
    {
    }
}
