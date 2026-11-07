# Marketing School — tədris platforması (`/platform`)

Laravel 13 + PostgreSQL (RLS) + Nginx + Docker Compose. Arxitektura: [`../docs/architecture.md`](../docs/architecture.md).
Tam quraşdırma, deploy, backup və admin istifadəsi kök `README.md`-dədir.

## Lokal inkişaf
```bash
# PostgreSQL 16+ lazımdır. Rolları və DB-ni yarat (superuser ilə):
cd ../deploy/postgres && sudo -u postgres DB_NAME=ms_dev OWNER_PASSWORD=owner APP_PASSWORD=app WORKER_PASSWORD=worker ./init-roles.sh
cd ../../platform
composer install && npm install && npm run vendor
cp .env.example .env && php artisan key:generate   # .env-də DB_* və PROXY_REQUIRE_SIGNATURE=false, SESSION_SECURE_COOKIE=false
php artisan migrate --database=pgsql_owner
php artisan platform:user:create admin@example.com "Admin" --role=admin
php artisan serve        # http://localhost:8000/platform
php artisan platform:work   # ayrı terminalda: outbox + e-mail növbəsi
```

## Testlər
```bash
# test DB: ms_test (eyni init-roles.sh ilə). .env.testing hazırdır.
php artisan test
vendor/bin/pint --test
```
Testlər real PostgreSQL üzərində işləyir; veb tətbiqi `ms_app` (RLS-ə tabe, sxem dəyişə bilməyən) rolu ilə qoşulur.

## Qovluqlar
| Yol | Nə üçün |
|---|---|
| `app/Domain/*` | Biznes məntiqi (Auth, Audit, Notifications, ...) |
| `app/Http/Middleware` | Netlify imza yoxlaması, təhlükəsizlik başlıqları, RLS konteksti, sessiya, 2FA |
| `app/Support/Rls.php`, `DbContext.php`, `System.php` | PostgreSQL RLS və DB rolları |
| `database/migrations` | Sxem + RLS policy-ləri (ms_owner ilə işləyir) |
| `lang/{az,en,ru}` | UI və e-mail şablonları |
| `public/platform/assets` | CSS (`tokens.css` landing-dən generasiya olunur), JS |
