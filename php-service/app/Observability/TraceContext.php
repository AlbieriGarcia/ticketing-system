<?php

namespace App\Observability;

// Contexto de traza W3C (traceparent): el mismo trace_id recorre todos los servicios y cada salto tiene su propio span_id
final class TraceContext
{
    private const TRACEPARENT_PATTERN = '/^00-([0-9a-f]{32})-([0-9a-f]{16})-[0-9a-f]{2}$/';

    private function __construct(
        public readonly string $traceId,
        public readonly string $spanId,
        public readonly ?string $parentSpanId = null,
    ) {}

    public static function generate(): self
    {
        return new self(bin2hex(random_bytes(16)), self::newSpanId());
    }

    // Devuelve null si el header falta o no cumple el formato; los ids todo ceros son inválidos según la especificación
    public static function parse(?string $traceparent): ?self
    {
        if ($traceparent === null || ! preg_match(self::TRACEPARENT_PATTERN, strtolower(trim($traceparent)), $matches)) {
            return null;
        }

        [, $traceId, $spanId] = $matches;

        if ($traceId === str_repeat('0', 32) || $spanId === str_repeat('0', 16)) {
            return null;
        }

        return new self($traceId, $spanId);
    }

    public function child(): self
    {
        return new self($this->traceId, self::newSpanId(), $this->spanId);
    }

    public function traceparent(): string
    {
        return "00-{$this->traceId}-{$this->spanId}-01";
    }

    public function logContext(): array
    {
        return array_filter([
            'trace_id' => $this->traceId,
            'span_id' => $this->spanId,
            'parent_span_id' => $this->parentSpanId,
        ]);
    }

    private static function newSpanId(): string
    {
        return bin2hex(random_bytes(8));
    }
}
