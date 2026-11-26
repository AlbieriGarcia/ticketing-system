<?php

namespace App\Observability;

use Monolog\Formatter\NormalizerFormatter;
use Monolog\LogRecord;
use Throwable;

// Formato nativo de Seq (CLEF): un JSON por línea con campos reservados @t, @m, @l, @x y los de traza @tr, @sp, @ps, @st
final class ClefFormatter extends NormalizerFormatter
{
    private const DATE_FORMAT = 'Y-m-d\TH:i:s.uP';

    private const LEVELS = [
        'DEBUG' => 'Debug',
        'INFO' => 'Information',
        'NOTICE' => 'Information',
        'WARNING' => 'Warning',
        'ERROR' => 'Error',
        'CRITICAL' => 'Fatal',
        'ALERT' => 'Fatal',
        'EMERGENCY' => 'Fatal',
    ];

    // Claves del contexto que pasan a campos reservados en vez de quedar como propiedades
    private const TRACE_FIELDS = [
        'trace_id' => '@tr',
        'span_id' => '@sp',
        'parent_span_id' => '@ps',
        'span_start' => '@st',
    ];

    public function __construct(private readonly string $application)
    {
        parent::__construct(self::DATE_FORMAT);
    }

    public function format(LogRecord $record): string
    {
        $context = $record->context;
        $exception = $context['exception'] ?? null;
        unset($context['exception']);

        $event = [
            '@t' => $record->datetime->format(self::DATE_FORMAT),
            '@m' => $record->message,
            '@l' => self::LEVELS[$record->level->getName()],
        ];

        if ($exception instanceof Throwable) {
            $event['@x'] = (string) $exception;
        }

        // Con @st el evento es un span: Seq calcula la duración hasta @t y lo cuelga de @ps dentro de la traza @tr
        foreach (self::TRACE_FIELDS as $key => $field) {
            if (isset($context[$key])) {
                $event[$field] = $context[$key];
            }

            unset($context[$key]);
        }

        $event['Application'] = $this->application;

        return $this->toJson([...$event, ...$this->normalize([...$record->extra, ...$context])])."\n";
    }

    public function formatBatch(array $records): string
    {
        return implode('', array_map($this->format(...), $records));
    }
}
