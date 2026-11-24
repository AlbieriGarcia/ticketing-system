<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

class AuthEndpoints
{
    #[OA\Post(
        path: '/auth/register',
        summary: 'Register a new customer',
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['name', 'email', 'password', 'password_confirmation'],
            properties: [
                new OA\Property(property: 'name', type: 'string', example: 'Ana Pérez'),
                new OA\Property(property: 'email', type: 'string', format: 'email', example: 'ana@example.com'),
                new OA\Property(property: 'password', type: 'string', example: 'secret123'),
                new OA\Property(property: 'password_confirmation', type: 'string', example: 'secret123'),
            ],
        )),
        tags: ['Auth'],
        responses: [
            new OA\Response(response: 201, description: 'User created', content: new OA\JsonContent(
                properties: [new OA\Property(property: 'data', ref: '#/components/schemas/User')],
            )),
            new OA\Response(ref: '#/components/responses/ValidationFailed', response: 422),
        ],
    )]
    public function register(): void
    {
    }

    #[OA\Post(
        path: '/auth/login',
        summary: 'Log in and obtain a Bearer token (max 5 attempts per minute)',
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['email', 'password'],
            properties: [
                new OA\Property(property: 'email', type: 'string', format: 'email', example: 'admin@tickets.test'),
                new OA\Property(property: 'password', type: 'string', example: 'admin1234'),
            ],
        )),
        tags: ['Auth'],
        responses: [
            new OA\Response(response: 200, description: 'Authenticated', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'token', type: 'string', example: '1|abc123...'),
                new OA\Property(property: 'token_type', type: 'string', example: 'Bearer'),
                new OA\Property(property: 'expires_in_minutes', type: 'integer', example: 1440),
            ])),
            new OA\Response(ref: '#/components/responses/Unauthorized', response: 401),
            new OA\Response(ref: '#/components/responses/ValidationFailed', response: 422),
            new OA\Response(response: 429, description: 'Too many login attempts'),
        ],
    )]
    public function login(): void
    {
    }

    #[OA\Get(
        path: '/auth/me',
        summary: 'Get the authenticated user',
        security: [['bearerAuth' => []]],
        tags: ['Auth'],
        responses: [
            new OA\Response(response: 200, description: 'Current user', content: new OA\JsonContent(
                properties: [new OA\Property(property: 'data', ref: '#/components/schemas/User')],
            )),
            new OA\Response(ref: '#/components/responses/Unauthorized', response: 401),
        ],
    )]
    public function me(): void
    {
    }

    #[OA\Post(
        path: '/auth/logout',
        summary: 'Revoke the current token',
        security: [['bearerAuth' => []]],
        tags: ['Auth'],
        responses: [
            new OA\Response(response: 204, description: 'Token revoked'),
            new OA\Response(ref: '#/components/responses/Unauthorized', response: 401),
        ],
    )]
    public function logout(): void
    {
    }
}
