#!/bin/sh
# Primer arranque de PostgreSQL: base de tests y usuario de solo lectura del servicio .NET (no puede modificar datos)
set -e

psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" <<EOSQL
CREATE DATABASE tickets_test;

CREATE ROLE stats_reader LOGIN PASSWORD '${STATS_READER_PASSWORD:-stats_secret}';
GRANT CONNECT ON DATABASE $POSTGRES_DB TO stats_reader;
\connect $POSTGRES_DB
GRANT USAGE ON SCHEMA public TO stats_reader;
GRANT SELECT ON ALL TABLES IN SCHEMA public TO stats_reader;
ALTER DEFAULT PRIVILEGES FOR ROLE $POSTGRES_USER IN SCHEMA public GRANT SELECT ON TABLES TO stats_reader;
EOSQL
