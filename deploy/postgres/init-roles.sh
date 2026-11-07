#!/usr/bin/env bash
# PostgreSQL rolları və verilənlər bazası (superuser ilə bir dəfə işə salınır).
# İstifadə: PGUSER=postgres DB_NAME=marketingschool OWNER_PASSWORD=... APP_PASSWORD=... WORKER_PASSWORD=... ./init-roles.sh
# Docker-də: /docker-entrypoint-initdb.d/ qovluğuna qoşulur.
set -euo pipefail
DB_NAME="${DB_NAME:-marketingschool}"
OWNER="${DB_OWNER_ROLE:-ms_owner}"; APP="${DB_APP_ROLE:-ms_app}"; WORKER="${DB_WORKER_ROLE:-ms_worker}"; BACKUP="${DB_BACKUP_ROLE:-ms_backup}"
: "${OWNER_PASSWORD:?}" "${APP_PASSWORD:?}" "${WORKER_PASSWORD:?}"
psql -v ON_ERROR_STOP=1 --dbname=postgres <<SQL
DO \$\$ BEGIN
  IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = '${OWNER}') THEN
    CREATE ROLE ${OWNER} LOGIN PASSWORD '${OWNER_PASSWORD}' NOSUPERUSER NOCREATEDB NOCREATEROLE NOBYPASSRLS; END IF;
  IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = '${APP}') THEN
    CREATE ROLE ${APP} LOGIN PASSWORD '${APP_PASSWORD}' NOSUPERUSER NOCREATEDB NOCREATEROLE NOBYPASSRLS; END IF;
  IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = '${WORKER}') THEN
    CREATE ROLE ${WORKER} LOGIN PASSWORD '${WORKER_PASSWORD}' NOSUPERUSER NOCREATEDB NOCREATEROLE NOBYPASSRLS; END IF;
  -- Yalnız backup üçün: oxuma + BYPASSRLS (pg_dump RLS-li cədvəlləri tam oxumalıdır). Parol yalnız host cron-dadır,
  -- tətbiq konteynerlərində YOXDUR. Yazma hüququ yoxdur.
  IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = '${BACKUP}') THEN
    CREATE ROLE ${BACKUP} LOGIN PASSWORD '${BACKUP_PASSWORD:-change-me}' NOSUPERUSER NOCREATEDB NOCREATEROLE BYPASSRLS; END IF;
END \$\$;
SQL
psql -tc "SELECT 1 FROM pg_database WHERE datname='${DB_NAME}'" --dbname=postgres | grep -q 1 || psql -c "CREATE DATABASE ${DB_NAME} OWNER ${OWNER}" --dbname=postgres
psql -v ON_ERROR_STOP=1 --dbname="${DB_NAME}" <<SQL
CREATE EXTENSION IF NOT EXISTS btree_gist;
CREATE EXTENSION IF NOT EXISTS pgcrypto;
REVOKE ALL ON SCHEMA public FROM PUBLIC;
GRANT USAGE, CREATE ON SCHEMA public TO ${OWNER};
GRANT USAGE ON SCHEMA public TO ${APP}, ${WORKER};
REVOKE CREATE ON SCHEMA public FROM ${APP}, ${WORKER};
GRANT CONNECT ON DATABASE ${DB_NAME} TO ${APP}, ${WORKER}, ${BACKUP};
GRANT pg_read_all_data TO ${BACKUP};
SQL
echo "roles ok: ${OWNER} (DDL), ${APP} (web, RLS), ${WORKER} (worker, RLS)"
