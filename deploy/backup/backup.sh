#!/usr/bin/env bash
# Gündəlik şifrələnmiş PostgreSQL backup-ı (bölmə 6, 9).
# - pg_dump custom format → age ilə AÇIQ AÇARA şifrələnir (serverdə yalnız public key var: server sındırılsa belə köhnə backup-lar oxunmur)
# - SHA-256 yoxlama cəmi, saxlama müddəti (default 14 gün lokal), ayrıca yerə köçürmə (BACKUP_REMOTE, rsync/ssh)
# - private fayllar (tapşırıq faylları) ayrıca tar arxivi kimi eyni qaydada
# İstifadə (host cron, 03:30 Bakı): 30 23 * * * /opt/marketingschool/deploy/backup/backup.sh >> /var/log/ms-backup.log 2>&1
set -euo pipefail
: "${BACKUP_DIR:=/var/backups/marketingschool}"
: "${BACKUP_AGE_RECIPIENT:?age public key (age1...) lazımdır}"
: "${PGHOST:=127.0.0.1}" "${PGPORT:=5432}" "${PGUSER:=ms_backup}" "${PGDATABASE:=marketingschool}"   # ms_backup: oxuma + BYPASSRLS
: "${RETENTION_DAYS:=14}"
FILES_DIR="${FILES_DIR:-}"   # məs. docker volume mountpoint (private-files)
export PGHOST PGPORT PGUSER PGDATABASE

ts="$(date -u +%Y%m%dT%H%M%SZ)"
mkdir -p "$BACKUP_DIR" && chmod 700 "$BACKUP_DIR"
db_out="$BACKUP_DIR/db-${PGDATABASE}-${ts}.dump.age"
pg_dump --format=custom --no-owner --no-privileges --compress=6 | age --encrypt --recipient "$BACKUP_AGE_RECIPIENT" --output "$db_out"
sha256sum "$db_out" > "$db_out.sha256"
if [[ -n "$FILES_DIR" && -d "$FILES_DIR" ]]; then
  files_out="$BACKUP_DIR/files-${ts}.tar.age"
  tar -C "$FILES_DIR" -cf - . | age --encrypt --recipient "$BACKUP_AGE_RECIPIENT" --output "$files_out"
  sha256sum "$files_out" > "$files_out.sha256"
fi
# Ayrıca yerdə saxlama (1c-11 qərarına görə: provayder backup storage, ikinci server və ya Bunny Storage)
if [[ -n "${BACKUP_REMOTE:-}" ]]; then
  rsync -a --chmod=F600 "$BACKUP_DIR"/*-"$ts"* "$BACKUP_REMOTE"/
fi
find "$BACKUP_DIR" -type f -mtime "+$RETENTION_DAYS" -delete
echo "backup ok: $db_out ($(du -h "$db_out" | cut -f1))"
