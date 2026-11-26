<?php

namespace App\Observability;

use Illuminate\Support\Arr;
use Livewire\Component;

use function Livewire\on;

// Traduce cada llamada de Livewire a un nombre legible (p. ej. "ListReservations::confirm") para el span de la request
final class LivewireSpanName
{
    public const ATTRIBUTE = 'livewire_action';

    public static function register(): void
    {
        on('call', function (Component $component, string $method, array $params): void {
            request()->attributes->set(self::ATTRIBUTE, class_basename($component).'::'.self::actionName($component, $method, $params));
        });
    }

    // Los botones de Filament no llaman a un método propio: montan la acción por nombre y luego la ejecutan
    private static function actionName(Component $component, string $method, array $params): string
    {
        return match ($method) {
            'mountAction' => $params[0] ?? $method,
            'callMountedAction' => Arr::last(data_get($component, 'mountedActions') ?? [])['name'] ?? $method,
            default => $method,
        };
    }
}
