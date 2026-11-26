# Despliegue en un servidor

Guía para publicar el sistema completo en un VPS con Docker. Es el mismo `docker-compose.yml` del entorno local, con `docker-compose.prod.yml` encima, que añade:

- **Caddy** como único punto de entrada (puertos 80/443), con HTTPS automático de Let's Encrypt;
- el resto de puertos enlazados a `127.0.0.1`: PostgreSQL, RabbitMQ y los servicios no son accesibles desde internet;
- `APP_ENV=production`, `APP_DEBUG=false` y todas las contraseñas leídas de un `.env` que no se commitea;
- `restart: unless-stopped`, para que todo vuelva a arrancar tras un reinicio del servidor.

```
https://<DOMAIN>         → php-api   (tienda, admin, API REST y Swagger de PHP)
https://stats.<DOMAIN>   → stats-api (API y Swagger de .NET)
https://seq.<DOMAIN>     → seq       (logs, trazas y alertas)
```

## Requisitos

- Un VPS con **Ubuntu 24.04**, **4 GB de RAM** como mínimo (recomendado 8 GB) y 2 vCPU o más.
- La IP pública del servidor y acceso SSH como `root`.
- Un dominio que apunte a esa IP. Si no hay, sirve [sslip.io](https://sslip.io): `203-0-113-10.sslip.io` resuelve a `203.0.113.10` sin configurar nada, y también sus subdominios (`stats.…`, `seq.…`).

## 1. Preparar el servidor

```bash
ssh root@<IP>

apt update && apt upgrade -y

# Firewall: solo SSH y web. Los puertos internos ya están enlazados a 127.0.0.1 en el compose,
# porque Docker publica sus puertos saltándose ufw
ufw allow OpenSSH
ufw allow 80/tcp
ufw allow 443/tcp
ufw --force enable

# Docker Engine y Docker Compose (script oficial de Docker)
curl -fsSL https://get.docker.com | sh
```

## 2. Descargar el proyecto y configurarlo

```bash
git clone https://github.com/AlbieriGarcia/ticketing-system.git
cd ticketing-system

cp .env.prod.example .env
nano .env
```

En el `.env`:

- `DOMAIN`: el dominio, o la IP con guiones y `.sslip.io`.
- `DB_PASSWORD`, `STATS_READER_PASSWORD`, `RABBITMQ_PASSWORD`, `STATS_API_KEY`: valores aleatorios. Se generan con `openssl rand -hex 16`.
- `ADMIN_PASSWORD` y `SEQ_ADMIN_PASSWORD`: los accesos que se comparten para la demo.

Las contraseñas de PostgreSQL y Seq solo se aplican al crear sus volúmenes, es decir, en el primer arranque.

## 3. Arrancar

```bash
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build
```

El primer arranque tarda varios minutos: construye las imágenes, instala dependencias, ejecuta migraciones y seeders, y Caddy obtiene los certificados. Para seguirlo:

```bash
docker compose -f docker-compose.yml -f docker-compose.prod.yml ps
docker compose -f docker-compose.yml -f docker-compose.prod.yml logs -f caddy php-api
```

Para no repetir los `-f`, se puede crear un alias en el servidor:

```bash
echo "alias dcp='docker compose -f docker-compose.yml -f docker-compose.prod.yml'" >> ~/.bashrc && source ~/.bashrc
dcp ps
```

## 4. Comprobar

| Qué | URL |
|---|---|
| Tienda | `https://<DOMAIN>` |
| Panel de administración | `https://<DOMAIN>/admin` |
| Swagger de PHP | `https://<DOMAIN>/api/documentation` |
| Swagger de .NET | `https://stats.<DOMAIN>/swagger` |
| Seq | `https://seq.<DOMAIN>` (usuario `admin`) |

```bash
curl -s https://<DOMAIN>/api/events -H "Accept: application/json" | head -c 200
curl -s "https://stats.<DOMAIN>/api/stats/summary?top=3" -H "X-Api-Key: <STATS_API_KEY>"
```

## Operación

```bash
# Actualizar a la última versión del repositorio
git pull
dcp up -d --build          # añadir -V si cambiaron dependencias de Composer

# Restablecer los datos de demo
dcp exec php-api php artisan migrate:fresh --seed

# RabbitMQ Management no está expuesto: se abre con un túnel SSH desde tu equipo
ssh -L 15672:127.0.0.1:15672 root@<IP>     # y luego http://localhost:15672
```

## Problemas frecuentes

- **El navegador avisa de un certificado no válido:** Caddy aún lo está pidiendo o no puede validarlo. Revisar `dcp logs caddy` y que los puertos 80 y 443 estén abiertos en el firewall del servidor y, si existe, en el del proveedor.
- **Un contenedor reinicia en bucle:** `dcp logs <servicio>`. Lo más habitual es un valor vacío en el `.env`; el compose se niega a arrancar si falta alguna variable obligatoria.
- **Se cambió una contraseña de PostgreSQL o Seq después del primer arranque:** no se aplica sobre volúmenes ya creados. En un entorno de demo lo más simple es recrearlos: `dcp down -v` (borra todos los datos) y volver a arrancar.
