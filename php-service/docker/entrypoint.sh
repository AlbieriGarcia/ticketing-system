#!/bin/sh
set -e

# Primer arranque en un clon limpio: crea el .env y la clave de la aplicación
if [ ! -f .env ]; then
    cp .env.example .env
fi

if ! grep -q '^APP_KEY=base64:' .env; then
    php artisan key:generate --force
fi

# Solo el contenedor principal prepara la base y los assets, para evitar carreras entre contenedores
if [ "${RUN_SETUP:-false}" = "true" ]; then
    php artisan migrate --force
    php artisan db:seed --force
    php artisan filament:assets
    php artisan l5-swagger:generate
fi

exec "$@"
