#!/usr/bin/env bash
# Deploy (VPS, Docker Compose). Addımlar: imic → backup → miqrasiya (ms_owner) → yeni konteynerlər → health-check.
# Uğursuzluqda avtomatik rollback (əvvəlki imic tag-ı). İstifadə: IMAGE_TAG=$(git rev-parse --short HEAD) ./deploy/deploy.sh
set -euo pipefail
cd "$(dirname "$0")"
: "${IMAGE_TAG:?}"; STACK="${STACK:-prod}"
PREV_TAG="$(cat ".last-tag-$STACK" 2>/dev/null || echo "")"
export STACK IMAGE_TAG
ENV_FILE="${ENV_FILE:-.env}"   # staging: ENV_FILE=staging.env STACK=staging
dc() { docker compose --env-file "$ENV_FILE" "$@"; }
dc build app
echo "→ backup (miqrasiyadan əvvəl məcburi)"; [[ -f "/etc/ms-backup-$STACK.env" ]] && { set -a; . "/etc/ms-backup-$STACK.env"; set +a; }; ./backup/backup.sh
echo "→ miqrasiya"; dc run --rm -e DB_CONNECTION=pgsql_owner app php artisan migrate --database=pgsql_owner --force
dc up -d --remove-orphans app worker scheduler nginx
for i in $(seq 1 30); do
  if dc exec -T nginx wget -qO- --no-check-certificate https://localhost/platform/up -H 'Host: origin.marketingschool.az' >/dev/null 2>&1; then
    echo "$IMAGE_TAG" > ".last-tag-$STACK"; dc exec -T app php artisan platform:audit:verify; echo "✓ deploy ok ($IMAGE_TAG)"; exit 0
  fi; sleep 2
done
echo "✗ health-check uğursuz — rollback → ${PREV_TAG:-yoxdur}"
if [[ -n "$PREV_TAG" ]]; then IMAGE_TAG="$PREV_TAG" dc up -d app worker scheduler nginx; fi
exit 1
