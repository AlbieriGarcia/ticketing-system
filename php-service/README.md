# Servicio PHP (Laravel 12)

Servicio principal del sistema de venta de entradas: API REST, tienda y panel de administración (Filament) y publicación de eventos de dominio en RabbitMQ.

La instalación, arquitectura, comunicación con el servicio .NET, ejemplos de uso y variables de entorno están documentados en el [README principal](../README.md).

Comandos habituales (desde la raíz del repositorio):

```bash
docker compose exec php-api php artisan test                    # tests
docker compose exec php-api php artisan migrate:fresh --seed    # restablecer datos de demo
docker compose logs -f php-outbox                               # publicación de mensajes
```
