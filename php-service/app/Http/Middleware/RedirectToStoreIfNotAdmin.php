<?php

namespace App\Http\Middleware;

use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Filament\Models\Contracts\FilamentUser;
use Filament\Notifications\Notification;
use Illuminate\Http\Exceptions\HttpResponseException;

class RedirectToStoreIfNotAdmin extends Authenticate
{
    protected function authenticate($request, array $guards): void
    {
        $user = Filament::auth()->user();

        // Un cliente logueado que llega al admin (URL guardada, enlace viejo, escrita a mano) vuelve a la tienda en vez de ver un 403
        if ($user instanceof FilamentUser && ! $user->canAccessPanel(Filament::getCurrentOrDefaultPanel())) {
            Notification::make()
                ->warning()
                ->title('You do not have access to the admin area')
                ->send();

            throw new HttpResponseException(redirect(url(Filament::getPanel('store')->getPath())));
        }

        parent::authenticate($request, $guards);
    }
}
