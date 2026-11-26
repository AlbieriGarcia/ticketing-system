<?php

use App\Exceptions\DomainException;
use App\Http\Middleware\PropagateTraceContext;
use App\Observability\TraceContext;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Primero de la cadena: todo lo que se registre en la request ya lleva el trace_id
        $middleware->prepend(PropagateTraceContext::class);

        // Detrás de Caddy (HTTPS) las URLs deben generarse con https; php-api solo es accesible a través del proxy
        $middleware->trustProxies(at: '*');

        // En la API un invitado recibe 401, no una redirección a una pantalla de login
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('api/*') ? null : '/login');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Toda ruta /api responde JSON, aunque el cliente no envíe "Accept: application/json"
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson()
        );

        // Evita exponer el nombre interno del modelo en el mensaje de 404
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'Resource not found.'], 404);
            }
        });

        // Un rechazo de negocio (sin stock, transición inválida...) es esperado: se registra, pero no dispara la alerta de errores
        $exceptions->level(DomainException::class, LogLevel::WARNING);

        $exceptions->render(function (DomainException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], $e->statusCode());
            }
        });

        // Con el trace_id que trae el error se encuentran en los logs todos los pasos de esa petición, en cualquier servicio
        $exceptions->respond(function (Response $response) {
            if ($response instanceof JsonResponse) {
                $response->setData([...$response->getData(true), 'trace_id' => app(TraceContext::class)->traceId]);
            }

            return $response;
        });
    })->create();
