#!/bin/sh
# Fija la contraseña de admin de Seq y crea las alertas del sistema si aún no existen. Es idempotente: corre en cada `docker compose up`
set -eu

SEQ_URL="${SEQ_URL:-http://seq}"
COOKIES=/tmp/seq-session

until curl -fs "$SEQ_URL/api" > /dev/null; do
    echo "Waiting for Seq..."
    sleep 2
done

login() {
    curl -fs -c "$COOKIES" -o /dev/null -X POST "$SEQ_URL/api/users/login" -H 'Content-Type: application/json' -d "$1"
}

# Primer arranque: se entra con la contraseña temporal y se fija la definitiva; en los siguientes ya entra directo
if login "{\"Username\": \"admin\", \"Password\": \"$SEQ_ADMIN_PASSWORD\"}"; then
    echo "Logged in to Seq"
elif login "{\"Username\": \"admin\", \"Password\": \"bootstrap-$SEQ_ADMIN_PASSWORD\", \"NewPassword\": \"$SEQ_ADMIN_PASSWORD\"}"; then
    echo "Seq admin password set"
else
    echo "Could not log in to Seq as admin" >&2
    exit 1
fi

existing=$(curl -fs -b "$COOKIES" "$SEQ_URL/api/alerts/?shared=true")

# Sin canal de notificación: el estado y el historial se ven en Seq > Alerts. En producción se añadiría email o Slack
create_alert() {
    title="$1"
    description="$2"
    where="$3"

    if echo "$existing" | grep -q "\"Title\":\"$title\""; then
        echo "Alert already exists: $title"
        return
    fi

    curl -fs -b "$COOKIES" -X POST "$SEQ_URL/api/alerts/" -H 'Content-Type: application/json' -o /dev/null -d "{
        \"Title\": \"$title\",
        \"Description\": \"$description\",
        \"OwnerId\": null,
        \"Where\": \"$where\",
        \"GroupBy\": [],
        \"TimeGrouping\": \"00:01:00\",
        \"Select\": [{\"Value\": \"count(*)\", \"Label\": \"count\"}],
        \"Having\": \"count > 0\",
        \"NotificationLevel\": \"Error\",
        \"NotificationProperties\": [],
        \"NotificationChannels\": [],
        \"SuppressionTime\": \"00:05:00\"
    }"

    echo "Alert created: $title"
}

# ci: PHP envía el nivel como "Error" y OpenTelemetry como "ERROR"
create_alert "Errors in any service" \
    "Error or fatal logs from PHP or .NET in the last minute" \
    "@Level in ['error', 'fatal', 'critical'] ci"

create_alert "Dead-letter queue not empty" \
    "The stats service could not apply some RabbitMQ messages; inspect stats-service.ticketing.dlq" \
    "DeadLetterCount > 0"

create_alert "Outbox backlog" \
    "Messages have been waiting more than 2 minutes to be published; check the php-outbox container" \
    "outbox_pending > 0"
