# Marketing School — marketingschool.az

Onlayn marketinq və rəqəmsal marketinq kursları. Repo iki hissədən ibarətdir:

| Hissə | Nədir | Harada işləyir |
|---|---|---|
| **Landing** (`src/`) | Statik sayt ([Eleventy](https://www.11ty.dev/)) + [Decap CMS](https://decapcms.org/), AZ/EN/RU | Netlify |
| **Platforma** (`platform/`) | Laravel 13 + PostgreSQL (RLS): demo, video dərslər (Bunny Stream), tapşırıqlar, canlı dərslər, mentorluq, diplom, ödəniş (Epoint), admin panel | VPS (Docker Compose), `marketingschool.az/platform` (Netlify imzalı proxy) |

Sənədlər: [arxitektura](docs/architecture.md) · [əməliyyatlar / deploy / go-live](docs/operations.md) ·
[backup və bərpa](docs/backup-restore.md) · [təhlükəsizlik auditi](docs/security-audit.md) · [performans](docs/performance.md) ·
[ödəniş provayderləri](docs/payment-providers.md) · [ilkin tapşırıq](docs/platform-prompt.md)

## Platforma — qısa başlanğıc
```bash
# PostgreSQL 16+ (lokal). Rollar: ms_owner (miqrasiya), ms_app (veb, RLS-ə tabe), ms_worker, ms_backup
sudo -u postgres DB_NAME=ms_dev OWNER_PASSWORD=owner APP_PASSWORD=app WORKER_PASSWORD=worker BACKUP_PASSWORD=backup deploy/postgres/init-roles.sh
cd platform && composer install && npm install && npm run vendor
cp .env.example .env && php artisan key:generate     # DB_*; lokalda PROXY_REQUIRE_SIGNATURE=false, SESSION_SECURE_COOKIE=false
php artisan migrate --database=pgsql_owner
php artisan platform:dev-seed                        # nümunə kurslar + admin/teacher/student/demo@example.test (parol Secret12345)
php artisan serve                                    # http://localhost:8000/platform
php artisan platform:work                            # ayrı terminalda: outbox + e-mail növbəsi
```
Bütün ayarlar `platform/.env.example`-da (tətbiq) və `deploy/.env.example`-da (Docker Compose) sənədləşdirilib. Real sirlər yalnız serverdə.

**Deploy (VPS):** `IMAGE_TAG=$(git rev-parse --short HEAD) ./deploy/deploy.sh` — backup → miqrasiya → konteynerlər → health-check, uğursuzluqda
avtomatik rollback. İlk quraşdırma, staging, rollback planı və go-live yoxlama siyahısı: [`docs/operations.md`](docs/operations.md).

**Admin:** `/platform/admin` (admin və təlimçilər üçün 2FA məcburi). Hesab: `php artisan platform:user:create <email> "<ad>" --role=admin|instructor`.

**Backup:** gündəlik şifrələnmiş (`age`) `pg_dump` + fayllar, `deploy/backup/{backup,restore,verify-restore}.sh` — [`docs/backup-restore.md`](docs/backup-restore.md).

## Testlər
```bash
cd platform && php artisan test && vendor/bin/pint --test   # ~185 test, real PostgreSQL (ms_test), RLS daxil
npm test                                                     # landing: build + i18n yoxlaması + 14 test
e2e/run.sh                                                   # Playwright: demo axını, admin 2FA, tələbə izolyasiyası (ms_e2e DB)
```
CI: `.github/workflows/ci.yml` (platform, landing, e2e, təhlükəsizlik skanları).

---

# Landing

Framework yoxdur, əsas səhifələrdə JS yoxdur (yalnız kiçik scroll-animasiya və forma skripti `src/assets/site.js`).
Dillər: AZ (kök), EN (`/en/`), RU (`/ru/`) — tərcümələr `src/_data/i18n/{en,ru}/`, marşrutlar `src/_includes/routes-map.js`.

## Başlamaq
```bash
npm install
npm start          # http://localhost:8080
npm run build      # _site/ (istehsal)
npm run demo       # _demo/ — düz .html fayllar + admin test rejimi (CMS demosu)
```

## Qovluq quruluşu
| Yol | Nə üçün |
|---|---|
| `src/_data/site.json` | Sayt ayarları: ad, təsvir, əlaqə, sosial şəbəkələr, **müraciət forması mətni**, təşəkkür mətni |
| `src/_data/home.json` | Ana səhifə mətnləri (hero, "Niyə Marketing School?", təlimçilər başlığı, FAQ başlığı) |
| `src/_data/kurslar/*.json` | **Hər kurs bir fayl**: SEO başlıq/təsvir, qısa cavab, faktlar cədvəli, modullar, **təlimçi** (foto, vəzifə, LinkedIn), qiymət |
| `src/_data/faq.json` | FAQ: qruplar, suallar, hansı səhifədə göstərilməsi (`show_on`) |
| `src/_data/blog.json` | Bloq səhifəsinin başlığı/təsviri |
| `src/bloq/*.md` | Bloq yazıları (frontmatter + Markdown) |
| `src/_data/cms.js` | CMS konfiqurasiyası (hansı sahə paneldə necə görünür) — `/admin/config.yml` buradan generasiya olunur |
| `src/_includes/` | `base.njk` (layout, nav, footer, JSON-LD), `form.njk`, `faq-list.njk`, `schema.js` (bütün schema.org qurucuları) |
| `src/kurs.njk` | Kurs səhifəsi şablonu (hər `kurslar/*.json` üçün bir səhifə) |
| `src/index.njk`, `faq.njk`, `bloq.njk` | Ana səhifə, FAQ, bloq siyahısı |
| `src/llms*.njk`, `sitemap.njk`, `rss.njk`, `robots.njk` | AI/SEO faylları — avtomatik generasiya olunur |
| `src/assets/` | `style.css`, loqo, `og.png`, `team/` (təlimçi şəkilləri) |
| `src/demo-index.njk` | Yalnız `npm run demo` zamanı çıxan icmal səhifəsi (istehsala düşmür) |

**Məzmun dəyişmək üçün koda toxunmaq lazım deyil** — yuxarıdakı JSON/Markdown fayllarını (və ya CMS-i) redaktə etmək kifayətdir. Sitemap, RSS, `llms.txt` və bütün schema avtomatik yenilənir.

## CMS (`/admin`)
- Lokal: `npm run cms` (ayrı terminalda) + `npm start` → http://localhost:8080/admin/
- Canlı: GitHub backend (`src/_data/cms.js` → `repo: "hesenildirimov/marketing-school"`). Netlify üzərində işləməsi üçün GitHub OAuth lazımdır:
  1. GitHub → Settings → Developer settings → OAuth Apps → yeni app, callback: `https://api.netlify.com/auth/done`
  2. Netlify → Site configuration → Access & security → OAuth → GitHub: Client ID / Secret
  3. Redaktorlara repo üzərində "Write" icazəsi verin.
- Bloq yazısında kursa link: `[Rəqəmsal Marketinq Kursu](kurs:digital-marketinq-kursu)`.

## Yayına çıxarma
`netlify.toml` hazırdır (build: `npm run build`, publish: `_site`, www→apex və http→https yönləndirmələri). Netlify-da repo-nu qoşmaq kifayətdir. Demo forması platformaya (`/platform/demo`) göndərilir; `/platform/*` Netlify-da imzalı proxy ilə VPS-ə yönləndirilir (`PLATFORM_PROXY_SECRET`, bax `docs/operations.md`).

## Hələ edilməli işlər
- [ ] **Telefon**, **ünvan**, **e-mail** — `site.json`-da doldurulanda schema və səhifələrdə avtomatik görünür. Qiymət: `kurslar/*.json` (`price`/`currency`, hazırda 99 AZN) — platformadakı qiymət admin paneldən idarə olunur, `platform:courses:import` sinxron saxlayır.
- [ ] Kurs modullarının və videodərslərin saatı/sayı dəqiqləşəndə `kurslar/*.json`-a əlavə edilməlidir (uydurma rəqəm yoxdur).
- [ ] Təlimçi mətnləri (`instructor.highlights`) LinkedIn profillərindən götürülüb — dərc etməzdən əvvəl təlimçilər təsdiq etməlidir.
- [ ] EN/RU tərcümələri dərcdən əvvəl dil daşıyıcısı tərəfindən redaktə olunmalıdır; boş sahələr CMS-də `/admin/tercume/` səhifəsində görünür.
- [ ] `og.png` (paylaşım şəkli) sadə hazırlanıb — dizayner variantı ilə əvəz oluna bilər (1200×630).
- [ ] Platformanın go-live yoxlama siyahısı (Bunny, Epoint, SMTP, DNS, proxy): [`docs/operations.md`](docs/operations.md) bölmə 7.

## Dizayn qaydaları
- Əsas rəng sarı (`--brand` və s., `style.css`-in yuxarısı və sonundakı "Sarı mono palitra" bölməsi). Vurğular sarı/tünd; yeni rəng əlavə etməyin.
- Tək şrift ailəsi (sistem şrifti), xarici şrift yoxdur.
- Animasiya: yalnız yüngül scroll reveal; `prefers-reduced-motion`-a hörmət edilir.

## SEO / GEO prinsipləri
- Hər kursda **"Qısa cavab"** (`summary`) və faktlar cədvəli var — AI cavablarının sitat gətirdiyi hissədir. Rəqəmləri (müddət, format, modul sayı) bütün yerlərdə eyni saxlayın.
- FAQ sualları insanların Google/ChatGPT-yə yazdığı kimi yazılmalıdır; cavabın ilk cümləsi birbaşa cavab olmalıdır.
- Başlıqlarda "rəqəmsal", axtarış üçün title/FAQ/schema-da "digital" də işlənir.
- Kurs `slug`-unu dəyişməyin; dəyişsə `netlify.toml`-a 301 əlavə edin.
