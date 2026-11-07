#!/usr/bin/env bash
# Lokal ölçmə serveri (Lighthouse / yük testi): e2e mühiti ilə eyni konfiqurasiya, məlumat sıfırlanmır.
cd "$(dirname "$0")/../platform"
export APP_ENV=local APP_DEBUG=false APP_KEY="${APP_KEY:-base64:ZTJlZTJlZTJlZTJlZTJlZTJlZTJlZTJlZTJlZTJlZTI=}" APP_URL=http://127.0.0.1:8001
export DB_CONNECTION=pgsql DB_HOST="${DB_HOST:-127.0.0.1}" DB_DATABASE=ms_e2e DB_USERNAME=ms_app DB_PASSWORD=app DB_OWNER_USERNAME=ms_owner DB_OWNER_PASSWORD=owner DB_WORKER_USERNAME=ms_worker DB_WORKER_PASSWORD=worker DB_SSLMODE=disable
export SESSION_DRIVER=database SESSION_SECURE_COOKIE=false SESSION_COOKIE=ms_session CACHE_STORE=database MAIL_MAILER=log PROXY_REQUIRE_SIGNATURE=false
export VIDEO_PROVIDER=fake BUNNY_CDN_HOST=vz-e2e.b-cdn.net BUNNY_TOKEN_KEY=k PAYMENT_PROVIDER=fake LANDING_URL=http://127.0.0.1:8001 ARGON_MEMORY=4096 ARGON_TIME=1
exec env PHP_CLI_SERVER_WORKERS="${WORKERS:-4}" php artisan serve --host=127.0.0.1 --port=8001
