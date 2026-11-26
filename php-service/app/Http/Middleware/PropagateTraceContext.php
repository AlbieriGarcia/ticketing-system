<?php

namespace App\Http\Middleware;

use App\Observability\TraceContext;
use Closure;
use DateTimeImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class PropagateTraceContext
{
    public function handle(Request $request, Closure $next): Response
    {
        // Si quien llama ya trae una traza (otro servicio), esta request es un salto más de ella
        $trace = TraceContext::parse($request->header('traceparent'))?->child() ?? TraceContext::generate();

        app()->instance(TraceContext::class, $trace);
        Log::shareContext($trace->logContext());

        $startedAt = new DateTimeImmutable;
        $response = $next($request);

        $response->headers->set('X-Trace-Id', $trace->traceId);

        // El healthcheck llega cada pocos segundos y solo añadiría ruido
        if (! $request->is('up')) {
            // span_start convierte esta línea en el span de la request (Seq calcula la duración hasta el momento del log)
            Log::info('HTTP {method} {path}', [
                'method' => $request->method(),
                'path' => '/'.ltrim($request->path(), '/'),
                'status' => $response->getStatusCode(),
                'duration_ms' => round((microtime(true) - (float) $startedAt->format('U.u')) * 1000, 1),
                'span_start' => $startedAt->format('Y-m-d\TH:i:s.uP'),
            ]);
        }

        return $response;
    }
}
