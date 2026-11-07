#!/usr/bin/env bash
# Backup-dan bərpa (AYRI mühitdə test və ya fəlakətdən sonra).
# İstifadə: AGE_IDENTITY=~/ms-backup.key TARGET_DB=ms_restore ./restore.sh db-....dump.age
# Private key serverdə SAXLANMIR — admin tərəfindən oflayn saxlanılır və yalnız bərpa zamanı istifadə olunur.
set -euo pipefail
file="${1:?backup faylı}"
: "${AGE_IDENTITY:?age private key faylı}" "${TARGET_DB:?hədəf DB}"
: "${PGHOST:=/var/run/postgresql}" "${PGPORT:=5432}" "${PGUSER:=postgres}"   # superuser, lokal socket
export PGHOST PGPORT PGUSER
if [[ -f "$file.sha256" ]]; then (cd "$(dirname "$file")" && sed "s#  .*/#  #" "$(basename "$file").sha256" | sha256sum -c -); fi
psql -v ON_ERROR_STOP=1 -d postgres -c "DROP DATABASE IF EXISTS \"$TARGET_DB\"" -c "CREATE DATABASE \"$TARGET_DB\" OWNER ms_owner"
psql -v ON_ERROR_STOP=1 -d "$TARGET_DB" -c "CREATE EXTENSION IF NOT EXISTS btree_gist; CREATE EXTENSION IF NOT EXISTS pgcrypto; GRANT USAGE ON SCHEMA public TO ms_app, ms_worker;"
age --decrypt --identity "$AGE_IDENTITY" "$file" | pg_restore --dbname="$TARGET_DB" --role=ms_owner --no-owner --no-comments --exit-on-error
# Hüquqlar (dump --no-privileges ilə alınıb): init-roles.sh ilə eyni model
psql -v ON_ERROR_STOP=1 -d "$TARGET_DB" <<SQL
GRANT USAGE ON SCHEMA app TO ms_app, ms_worker;
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO ms_app, ms_worker;
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO ms_app, ms_worker;
REVOKE UPDATE, DELETE, TRUNCATE ON audit_logs FROM ms_app, ms_worker;
REVOKE ALL ON migrations FROM ms_app, ms_worker;
SQL
echo "restore ok → $TARGET_DB"
