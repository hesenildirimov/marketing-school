# Backup və bərpa

## 1. Nə, nə vaxt, harada

| Nə | Necə | Tezlik | Saxlama |
|---|---|---|---|
| PostgreSQL | `pg_dump -Fc` → `age` ilə **açıq açara** şifrələnir + `.sha256` | gündəlik 03:30 (Bakı), hər deploy-dan əvvəl | lokal 14 gün (`RETENTION_DAYS`) + `BACKUP_REMOTE` |
| Private fayllar (tapşırıq faylları) | `tar` → `age` | eyni | eyni |
| Videolar | Bunny Stream-də saxlanılır (backup-a daxil deyil; orijinallar müəllimdə qalır) | — | — |
| Landing məzmunu | Git (GitHub) | hər commit | — |

- Backup `ms_backup` rolu ilə alınır: yalnız oxuma (`pg_read_all_data`) + `BYPASSRLS` (əks halda FORCE RLS dump-ı boş verərdi).
  Bu rol yalnız host cron-dan istifadə olunur; tətbiq konteynerlərində onun parolu yoxdur.
- Serverdə yalnız **public key** (`BACKUP_AGE_RECIPIENT=age1...`) var. Private key admin tərəfindən oflayn saxlanılır
  (parol menecerində + çap olunmuş nüsxə). Server sındırılsa belə köhnə backup-lar oxunmur.
- `BACKUP_REMOTE` (rsync hədəfi): VPS provayderinin backup storage-i, ikinci server və ya Bunny Storage (SFTP). Default: boş —
  **go-live-dan əvvəl doldurulmalıdır** (`docs/operations.md`, yoxlama siyahısı).

## 2. Quraşdırma (VPS, bir dəfə)

```bash
apt install age postgresql-client-17 rsync
age-keygen -o ms-backup.key          # BU FAYLI SERVERDƏ SAXLAMAYIN — oflayn köçürün, sonra silin
grep public ms-backup.key            # → age1...
```
`/etc/ms-backup.env` (chmod 600, root):
```
BACKUP_AGE_RECIPIENT=age1...
PGHOST=127.0.0.1
PGPASSWORD=<DB_BACKUP_PASSWORD>
PGDATABASE=marketingschool
FILES_DIR=/var/lib/docker/volumes/ms-prod_private-files/_data
BACKUP_REMOTE=backup@backup-host:/srv/ms
```
Cron (`crontab -e`, root; server saatı UTC):
```
30 23 * * * set -a; . /etc/ms-backup.env; /opt/marketingschool/deploy/backup/backup.sh >> /var/log/ms-backup.log 2>&1
```
Postgres konteyneri yalnız `127.0.0.1:5432`-yə bağlanır (compose, `PG_HOST_PORT`) — internetdən əlçatan deyil, firewall-da 5432 bağlı qalır.

## 3. Bərpa

Bərpa **həmişə əvvəlcə ayrı DB-yə** edilir, yoxlanılır, sonra keçid edilir.
```bash
AGE_IDENTITY=~/ms-backup.key TARGET_DB=ms_restore ./deploy/backup/restore.sh /var/backups/marketingschool/db-marketingschool-<ts>.dump.age
sudo -u postgres SOURCE_DB=marketingschool TARGET_DB=ms_restore ./deploy/backup/verify-restore.sh
```
`restore.sh`: SHA-256 yoxlayır → DB-ni `ms_owner` sahibliyi ilə yaradır → `pg_restore --role=ms_owner` → hüquqları `init-roles.sh`
modelinə uyğun yenidən verir (audit_logs üçün UPDATE/DELETE yox).
`verify-restore.sh`: cədvəl üzrə sətir sayları, RLS policy sayı, FORCE RLS, dəyişməzlik trigger-ləri, `ms_app` hüquqları.
Sonra bərpa olunmuş DB-də `php artisan platform:audit:verify` (hash zənciri).

Fəlakət halında keçid: `.env`-də `DB_DATABASE=ms_restore` → `docker compose up -d app worker scheduler`
(və ya köhnə DB-ni silib bərpanı `marketingschool` adı ilə təkrarlamaq).

## 4. Real bərpa testi (M9, 2026-11-02)

Lokal PostgreSQL 16, `platform:dev-seed` + testlərdən sonrakı DB → `backup.sh` → `restore.sh` → `verify-restore.sh`:

| Yoxlama | Nəticə |
|---|---|
| Sətir sayları (bütün cədvəllər) | ✓ 50 cədvəl, 562 sətir — mənbə ilə eyni |
| RLS policy sayı | ✓ 68 = 68 |
| FORCE RLS cədvəlləri | ✓ 17 |
| Dəyişməzlik trigger-ləri (`audit_logs`, `grades`, `certificates`) | ✓ yerindədir |
| `ms_app` audit_logs-u dəyişə bilmir | ✓ |
| `ms_app` BYPASSRLS | ✓ false |
| Audit hash zənciri (`platform:audit:verify`) bərpa olunmuş DB-də | ✓ pozulmayıb |

Test zamanı tapılıb düzəldilən problemlər: `ms_owner` ilə dump FORCE RLS səbəbindən sətirləri görmürdü (→ `ms_backup` rolu);
`COMMENT ON EXTENSION` superuser tələb edirdi (→ `--no-comments`); `.sha256` faylında mütləq yol (→ nisbi yoxlama).

**Tövsiyə:** ayda bir dəfə bərpa testini staging serverində təkrarlayın və nəticəni (tarix, backup faylı, `verify-restore.sh` çıxışı)
admin jurnalında qeyd edin. RPO: ≤ 24 saat (gündəlik backup), RTO: ≈ 30 dəq (bərpa + yoxlama + keçid).
