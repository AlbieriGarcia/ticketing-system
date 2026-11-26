<?php

namespace App\Http\Middleware;

use App\Observability\TraceContext;
use Closure;
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

        $startedAt = hrtime(true);
        $response = $next($request);

        $response->headers->set('X-Trace-Id', $trace->traceId);

        // El healthcheck llega cada pocos segundos y solo añadiría ruido
        if (! $request->is('up')) {
            Log::info('HTTP request handled', [
                'method' => $request->method(),
                'path' => '/'.ltrim($request->path(), '/'),
                'status' => $response->getStatusCode(),
                'duration_ms' => round((hrtime(true) - $startedAt) / 1e6, 1),
            ]);
        }

        return $response;
    }
}
