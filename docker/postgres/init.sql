CREATE DATABASE tickets_test;

-- Usuario de solo lectura para el servicio de estadísticas (.NET): no puede modificar datos
CREATE ROLE stats_reader LOGIN PASSWORD 'stats_secret';
GRANT CONNECT ON DATABASE tickets TO stats_reader;
\connect tickets
GRANT USAGE ON SCHEMA public TO stats_reader;
GRANT SELECT ON ALL TABLES IN SCHEMA public TO stats_reader;
ALTER DEFAULT PRIVILEGES FOR ROLE tickets IN SCHEMA public GRANT SELECT ON TABLES TO stats_reader;
