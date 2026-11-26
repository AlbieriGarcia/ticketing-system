#!/bin/sh
# Crea en Seq las alertas del sistema si aún no existen. Es idempotente: corre en cada `docker compose up`
set -eu

SEQ_URL="${SEQ_URL:-http://seq}"

until curl -fs "$SEQ_URL/api" > /dev/null; do
    echo "Waiting for Seq..."
    sleep 2
done

existing=$(curl -fs "$SEQ_URL/api/alerts/?shared=true")

# Sin canal de notificación: el estado y el historial se ven en Seq > Alerts. En producción se añadiría email o Slack
create_alert() {
    title="$1"
    description="$2"
    where="$3"

    if echo "$existing" | grep -q "\"Title\":\"$title\""; then
        echo "Alert already exists: $title"
        return
    fi

    curl -fs -X POST "$SEQ_URL/api/alerts/" -H 'Content-Type: application/json' -o /dev/null -d "{
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
