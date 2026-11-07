# Əməliyyatlar: deploy, rollback, go-live

Arxitektura: [`architecture.md`](architecture.md). Təhlükəsizlik: [`security-audit.md`](security-audit.md).
Backup: [`backup-restore.md`](backup-restore.md). Performans: [`performance.md`](performance.md).

## 1. Komponentlər

```
Brauzer → marketingschool.az (Netlify: landing statik + /platform/* imzalı proxy, x-nf-sign)
        → origin.marketingschool.az (VPS: nginx → PHP-FPM app; worker; scheduler; PostgreSQL 17; ClamAV)
Video   → Bunny Stream CDN (birbaşa brauzerə, VPS-dən keçmir)
Ödəniş  → Epoint (redirect + webhook /platform/api/payments/webhook/epoint)
E-mail  → SMTP (worker göndərir, MAIL_MAX_PER_MINUTE limiti ilə)
```

## 2. İlk quraşdırma (VPS, Ubuntu 24.04, min. 2 vCPU / 4 GB / 60 GB)

```bash
apt install docker.io docker-compose-v2 age postgresql-client rsync ufw
ufw allow 22,80,443/tcp && ufw enable          # 5432 açılmır (compose yalnız 127.0.0.1-ə bağlayır)
git clone <repo> /opt/marketingschool && cd /opt/marketingschool
cp platform/.env.example platform/.env && cp deploy/.env.example deploy/.env   # doldurun (bölmə 5)
openssl rand -base64 32 > deploy/secrets/pg_superuser.txt && chmod 600 deploy/secrets/pg_superuser.txt
docker compose -f deploy/docker-compose.yml run --rm app php artisan key:generate --show   # → APP_KEY
# TLS (origin sertifikatı): əvvəl 80-də nginx, sonra certbot
docker compose -f deploy/docker-compose.yml --profile certs run --rm certbot certonly --webroot -w /var/www/certbot -d origin.marketingschool.az
IMAGE_TAG=$(git rev-parse --short HEAD) ./deploy/deploy.sh
docker compose -f deploy/docker-compose.yml --profile certs up -d certbot     # avtomatik yeniləmə
docker compose -f deploy/docker-compose.yml exec app php artisan platform:courses:import
docker compose -f deploy/docker-compose.yml exec app php artisan platform:disposable:import database/data/disposable_domains.txt   # disposable e-mail siyahısı
docker compose -f deploy/docker-compose.yml exec app php artisan platform:user:create admin@marketingschool.az "Admin"   # sonra 2FA qurulur
```
Backup cron-u: [`backup-restore.md`](backup-restore.md) bölmə 2.

**Staging** (eyni VPS, ayrı layihə, ayrı DB və açarlar): `deploy/staging.env` (`STACK=staging`, `APP_ENV_FILE=../platform/.env.staging`,
`HTTP_PORT=8080`, `HTTPS_PORT=8443`, `PG_HOST_PORT=5433`), Netlify-da ayrıca staging saytı (`/platform/*` →
`https://origin.marketingschool.az:8443/platform/:splat`, öz `PLATFORM_PROXY_SECRET`-i), `PAYMENT_PROVIDER=fake` və ya Epoint sandbox.
Deploy: `ENV_FILE=staging.env STACK=staging IMAGE_TAG=... ./deploy/deploy.sh`.

## 3. Deploy (hər buraxılış)

```bash
cd /opt/marketingschool && git fetch && git checkout <tag>
IMAGE_TAG=$(git rev-parse --short HEAD) ./deploy/deploy.sh
```
`deploy.sh` ardıcıllığı: imic → **backup (məcburi)** → miqrasiya (`ms_owner`, yeganə DDL hüquqlu rol) → yeni konteynerlər →
health-check (`/platform/up`, 60 s) → `platform:audit:verify`. Health-check uğursuz olarsa əvvəlki imic tag-ına avtomatik qayıdır.
Landing: `main`-ə merge → Netlify avtomatik build (`npm run build`), Deploy Preview-lar PR-lar üçün.

## 4. Rollback planı

| Hal | Addım |
|---|---|
| Yeni kod xətalıdır, miqrasiya yoxdur | `IMAGE_TAG=<əvvəlki> docker compose -f deploy/docker-compose.yml up -d app worker scheduler nginx` (`deploy/.last-tag-prod` sonuncu uğurlu tag-dır) |
| Miqrasiya var, **geriyə uyğundur** (yalnız yeni cədvəl/sütun — qayda budur) | Yuxarıdakı kimi; köhnə kod yeni sütunları sadəcə istifadə etmir. `migrate:rollback` **etməyin** |
| Miqrasiya məlumatı pozub | Texniki iş rejimi (`php artisan down --secret=...`) → deploy-dan əvvəlki backup-ı ayrı DB-yə bərpa → yoxlama → keçid ([`backup-restore.md`](backup-restore.md) bölmə 3). Ödəniş webhook-ları provayder tərəfindən təkrarlanır, `platform:billing reconcile` statusları yenidən yoxlayır |
| Landing xətalıdır | Netlify → Deploys → əvvəlki deploy → "Publish deploy" (bir klik) |
| Netlify proxy işləmir | Netlify-da `netlify.toml` əvvəlki deploy; VPS-ə toxunmaq lazım deyil. Proxy olmadan platforma əlçatan deyil (origin imzasız sorğuları 403 qaytarır — qəsdən) |

Miqrasiya qaydası (genişlət → köçür → daralt): sütunun silinməsi/adının dəyişməsi ən azı iki buraxılışa bölünür, belə ki, hər addımda bir
əvvəlki kod versiyası yeni sxemlə işləyə bilsin.

## 5. `.env` (sirlər yalnız serverdə)

Tam siyahı: `platform/.env.example` (tətbiq) və `deploy/.env.example` (compose). Vacib olanlar:

| Dəyişən | Qeyd |
|---|---|
| `APP_KEY` | `key:generate`. Dəyişsə: sessiyalar, şifrələnmiş TOTP sirləri və bildiriş linkləri oxunmaz — **dəyişməyin**, backup ilə birlikdə oflayn saxlayın |
| `PLATFORM_PROXY_SECRET`, `PROXY_REQUIRE_SIGNATURE=true` | Netlify env (scope: Runtime) ilə eyni. Rotasiya: əvvəl serverdə, sonra Netlify-da (bir neçə saniyə 403) |
| `DB_PASSWORD` / `DB_WORKER_PASSWORD` / `DB_OWNER_PASSWORD` | Compose veb konteynerinə worker/owner parollarını ötürmür |
| `BUNNY_*`, `VIDEO_PROVIDER=bunny` | Library ID, API key, CDN host, **token authentication key** (Bunny → Stream → Security) |
| `PAYMENT_PROVIDER=epoint`, `EPOINT_*` | Bax [`payment-providers.md`](payment-providers.md). Müqavilə olana qədər `manual` (admin əl ilə ödəniş qeyd edir) |
| `MAIL_*` | SMTP; SPF, DKIM, DMARC DNS qeydləri olmadan e-mail spam-a düşür |
| `SESSION_SECURE_COOKIE=true`, `APP_DEBUG=false`, `APP_ENV=production` | Go-live yoxlamasında təsdiqlənir |

## 6. Gündəlik əməliyyat və admin istifadəsi

- Admin panel: `/platform/admin` (2FA məcburi). Kurslar/versiyalar, dərslər və video yükləmə (Bunny TUS), tapşırıqlar, qiymətləndirmə,
  canlı dərslər, mentorluq slotları, ödənişlər (əl ilə aktivləşdirmə, geri qaytarma, qiymət və güzəşt qaydaları), tələbələr
  (sessiyaların ləğvi, girişin ləğvi), demo-lar, diplomlar (ləğv), bildirişlər (təkrar göndərmə), analitika, audit jurnalı.
  Həssas əməliyyatlar (dərc, geri qaytarma, diplom ləğvi) parolun yenidən təsdiqini tələb edir.
- Admin/təlimçi hesabı və rol: `php artisan platform:user:create <email> "<ad>" --role=admin|instructor [--course=<slug>]`.
- Kurs məzmununun landing-dən sinxronu: `php artisan platform:courses:import` (idempotent); geri ixrac: `platform:courses:export`.
- Fon işləri (scheduler konteyneri, `routes/console.php`): outbox/e-mail hər dəqiqə, canlı dərs generasiyası 03:10, xatırlatmalar 5 dəq,
  billing (statuslar 10 dəq, reconcile 5 dəq, webhook təkrarı 5 dəq, yeniləmə saatlıq), təmizlik 02:40.
- Monitorinq: `docker compose logs -f app worker`, `storage/logs`, `/platform/up` (xarici uptime monitoru: UptimeRobot və s. pulsuz plan),
  `php artisan platform:audit:verify` (həftəlik cron tövsiyə olunur).
- Admin telefonunu və recovery kodlarını itiribsə: şəxsiyyəti yoxladıqdan sonra serverdə
  `php artisan platform:user:reset-2fa <email> --reason="..."` (sessiyalar ləğv olunur, audit-ə yazılır, növbəti girişdə 2FA yenidən qurulur).

## 7. Go-live yoxlama siyahısı

**Infrastruktur**
- [ ] VPS: firewall (22/80/443), avtomatik təhlükəsizlik yeniləmələri (`unattended-upgrades`), SSH yalnız açarla
- [ ] DNS: `marketingschool.az` → Netlify, `origin.marketingschool.az` → VPS IP (A), TLS sertifikatı aktiv, certbot konteyneri işləyir
- [ ] `deploy/secrets/pg_superuser.txt`, `platform/.env`, `deploy/.env` — chmod 600, repoda yoxdur (`git status` təmiz)
- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, `PROXY_REQUIRE_SIGNATURE=true`

**Netlify proxy**
- [ ] `PLATFORM_PROXY_SECRET` Netlify-da və serverdə eynidir; `curl https://origin.marketingschool.az/platform/login` → 403 (imzasız)
- [ ] `https://marketingschool.az/platform/login` açılır, giriş işləyir, cookie `Path=/platform; Secure; HttpOnly; SameSite=Lax`
- [ ] Böyük fayl yükləmə (chunk-lar) proxy-dən keçir; 26 s limitinə yaxın sorğu yoxdur (loqlar)

**Bunny Stream**
- [ ] Token authentication aktiv, `BUNNY_TOKEN_KEY` doludur; tokensiz HLS URL → 403
- [ ] Allowed referrers: `marketingschool.az`; test videosu yüklənir, transcode olur, dərsdə oynayır (mobil + desktop)
- [ ] Watermark görünür, heartbeat işləyir (admin → analitika)

**Epoint**
- [ ] Sandbox-da tam axın: ödəniş → redirect → webhook → giriş açılır; uğursuz ödəniş; geri qaytarma
- [ ] Webhook URL (`https://marketingschool.az/platform/api/payments/webhook/epoint`) Epoint kabinetində qeyd olunub; imza yoxlaması (səhv imza → 400, audit-də)
- [ ] Production açarları ilə 1 AZN-lik real ödəniş və geri qaytarma

**SMTP / e-mail**
- [ ] SPF, DKIM, DMARC; test: qeydiyyat, demo linki, parol bərpası, canlı dərs dəvəti (.ics) Gmail/Outlook-da spam-a düşmür
- [ ] AZ/EN/RU şablonları yoxlanılıb

**Məlumat və təhlükəsizlik**
- [ ] İlk backup alınıb, `BACKUP_REMOTE`-ə köçüb, **bərpa testi** keçib (`verify-restore.sh`)
- [ ] Admin hesabları 2FA ilə, recovery kodları oflayn saxlanılıb
- [ ] `security-audit.md` bölmə 6 yoxlama siyahısı; `composer audit` / `npm audit` təmiz (qəbul olunmuş risklər sənəddə)
- [ ] Məxfilik siyasəti və şərtlər səhifələri dərc olunub (hüquqi mətn sifarişçidən)

**Funksional (staging-də, sonra prod-da bir dəfə)**
- [ ] CI-da e2e yaşıl (`e2e/run.sh`: demo axını, admin 2FA, tələbə izolyasiyası); staging-də eyni axınlar əl ilə (real e-mail ilə)
- [ ] k6 yük testi (100 VU) threshold-ları keçir ([`performance.md`](performance.md))
- [ ] Diplom: test tələbəsi üçün 75% sərhədi, PDF, `/platform/diplom/{id}` ictimai yoxlama
