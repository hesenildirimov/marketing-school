# Security audit — Marketing School platforması (M9)

> Əsas: OWASP Top 10 (2021) və OWASP ASVS 4.0 Level 2. Tarix: 2026-11-02. Əhatə: `platform/` (Laravel 13, PostgreSQL 17),
> `src/` landing (Eleventy, Netlify), `deploy/` (Docker Compose, Nginx, backup).
> Yoxlama üsulları: kod icmalı, avtomatik testlər (PHPUnit 190+, landing node:test 14, Playwright e2e 3), real PostgreSQL üzərində RLS
> testləri, real ClamAV (EICAR), paralel proses (race) testləri, real backup → restore.

## 1. Xülasə

| Səviyyə | Tapılıb | Düzəldilib | Qəbul edilib (qalıq risk) |
|---|---|---|---|
| Kritik | 0 | — | — |
| Yüksək | 1 | 1 | 0 |
| Orta | 6 | 6 | 0 |
| Aşağı | 5 | 4 | 1 (build-time asılılıq) |

**Kritik və yüksək riskli açıq tapıntı qalmayıb.** Qalıq risklər bölmə 5-dədir.

## 2. Tapıntılar (risk, təsvir, düzəliş, test)

| # | Risk | Tapıntı | Necə düzəldildi | Test |
|---|---|---|---|---|
| F1 | **Yüksək** | RLS konteksti (`app.user_id`) route-model binding-dən **sonra** qurulurdu. Production-da (PHP-FPM, hər sorğu yeni bağlantı) RLS cədvəllərinə bağlı route-lar (diplom, fayl, rezerv) 404 verə, nəzəri olaraq isə əvvəlki konteksti istifadə edə bilərdi. Testlər paylaşılan bağlantıda əvvəlki sorğunun kontekstini daşıdığı üçün bunu görmürdü. | `SetDbContext` middleware-i `SubstituteBindings`-dən əvvələ köçürüldü (`bootstrap/app.php`). Test infrastrukturu dəyişdi: hər HTTP sorğusu boş DB konteksti ilə başlayır, production kimi (`tests/TestCase.php::call`). | Bütün feature testləri (190+) yeni rejimdə keçir; `DiplomaTest`, `AssignmentFileTest` (binding-li route-lar) |
| F2 | Orta | Təsdiq və parol bərpası linkləri (xam token) outbox-da (`notifications.payload`) açıq saxlanılırdı. DB sızsa, 24 saat ərzində istifadə olunmamış tokenlər açılardı. Bu, "DB-də yalnız hash" tələbini pozurdu. | Tokenli URL-lər `APP_KEY` ilə şifrələnir (`url_enc`). Açar DB-də deyil. Göndərmə zamanı açılır (`Notifier`, `PlatformMail`). | `AuthTest::test_registration_creates_unverified_user...` (xam token heç bir DB cədvəlində yoxdur) |
| F3 | Orta | Backup cədvəl sahibi (`ms_owner`) ilə mümkün deyildi: `FORCE ROW LEVEL SECURITY` səbəbindən `pg_dump` RLS-li cədvəlləri oxumurdu. Sahibdən RLS-i söndürmək zəif həll olardı. | Ayrıca `ms_backup` rolu: yalnız oxuma (`pg_read_all_data`) + `BYPASSRLS`, yazma yoxdur. Parol yalnız host cron-dadır, tətbiq konteynerlərində yoxdur (`deploy/postgres/init-roles.sh`). | Real backup → restore testi (bölmə 4.3, `docs/backup-restore.md`) |
| F4 | Orta | 1:1 boş slot siyahısı başqa tələbələrin rezervlərini oxumağa əsaslanırdı. RLS onları gizlətdiyi üçün tutulmuş slotlar boş görünürdü. RLS olmasaydı, başqasının rezerv məlumatı sızardı. | `mentor_slots.is_booked` bayrağı rezerv/ləğv ilə eyni tranzaksiyada yenilənir. Atomiklik `FOR UPDATE` və partial UNIQUE ilə qorunur. | `MentoringTest::test_two_students_racing...` (paralel proseslər), `test_cancel_rule...` |
| F5 | Orta | Decap CMS skripti `/admin`-də SRI olmadan CDN-dən yüklənirdi. `/platform` eyni origin-də olduğu üçün kompromis olmuş CDN skripti platformaya eyni origin hüququ ilə sorğu göndərə bilərdi. | `integrity="sha384-…"` + `crossorigin` (hash npm paketindən hesablanıb). Landing-ə ciddi CSP (`script-src 'self'`), inline skript `site.js`-ə köçürüldü. `/admin` üçün ayrıca CSP. | `tests/landing.test.mjs` → "Decap CMS SRI ilə…, inline skript yoxdur" |
| F6 | Orta | Veb konteyneri ortaq `.env`-dən worker və owner DB parollarını da alırdı (ən az səlahiyyət prinsipi pozulurdu). | `deploy/docker-compose.yml`: `app` üçün `DB_WORKER_PASSWORD=""` və `DB_OWNER_PASSWORD=""`, worker və scheduler üçün `DB_OWNER_PASSWORD=""`. Miqrasiya yalnız deploy addımında. | Konfiqurasiya icmalı; `RlsTest::test_app_role_cannot_change_schema` |
| F7 | Orta | Landing-də təhlükəsizlik başlıqları yox idi. Netlify proxy cavablarına `netlify.toml` başlıqları tətbiq olunmur. | Landing: CSP, HSTS, nosniff, XFO, Referrer-Policy, Permissions-Policy, COOP (`netlify.toml`). Platforma: `SecurityHeaders` middleware (origin-də). | `HttpSecurityTest::test_security_headers_present`, landing testi, e2e |
| F8 | Aşağı | Ödəniş redirect URL-i provayder cavabından yoxlanılmadan istifadə olunurdu (cavab saxtalaşdırılsa açıq yönləndirmə). | Yalnız `https://` və icazəli host siyahısı (`services.payment.form_action_hosts`). | `BillingTest` (fake host icazəlidir), kod icmalı |
| F9 | Aşağı | Yarımçıq chunk yükləmələri diskdə qalırdı (diski doldurma). Köhnə token, sessiya və video jurnalları silinmirdi. | `platform:cleanup` (gündəlik): tmp > 2 saat, tokenlər > 30 gün, ləğv olunmuş sessiyalar və video jurnalı > 90 gün. | Planlayıcıda; kod icmalı |
| F10 | Aşağı | Dərs/tapşırıq revizyasında köhnə Eloquent relation keşi ardıcıl redaktədə başlığı silə bilərdi (məlumat bütövlüyü). | Əvvəlki revizya həmişə DB-dən oxunur. | `ProgressTest::test_lesson_revision_keeps_history` (regressiya) |
| F11 | Aşağı | Açıq diplom yoxlaması `SECURITY DEFINER` funksiyası ilə planlaşdırılmışdı; FORCE RLS altında ya işləməz, ya da RLS-i yan keçən geniş funksiya olardı. | Ayrıca `certificate_verifications` cədvəli: yalnız ad, kurs, tarix (bilərəkdən açıq). Hesab silinəndə də qalır. | `DiplomaTest::test_public_verification...` |
| F12 | Aşağı (qəbul) | Landing dev-asılılığı: `@11ty/eleventy` → `@11ty/recursive-copy` → `minimatch` (ReDoS, moderate). Eleventy 3.1.7-yə yeniləndi, problem qalır. | Yalnız build zamanı işləyir və yalnız bizim glob-larımızı emal edir (istifadəçi girişi yoxdur). `npm audit --omit=dev` təmizdir. Dependabot izləyir. | CI: `npm audit --omit=dev` |

## 3. Bölmə 6 yoxlama siyahısı

### 3.1 Access control / IDOR

| Yoxlama | Nəticə | Test |
|---|---|---|
| Demo 3-cü və sonrakı modulların dərsini, tokenini, materialını və tapşırığını heç bir yolla ala bilmir | ✅ | `DemoTest::test_demo_gives_access_only_to_first_two_modules…`, `LessonAccessTest::test_locked_lesson_page_does_not_leak_content`, e2e demo |
| Müddəti bitmiş demo bloklanır, davam edən token yeniləməsi rədd olunur | ✅ | `DemoTest::test_demo_token_ttl_capped…`, `test_24h_counts_from_registration…` |
| Demo başqa kursun demosunu ala bilmir | ✅ | `DemoTest::test_cannot_get_second_demo_by_switching_course` |
| Kursu almayan 1:1, canlı link, yazılar, diplom endpoint-lərinə daxil ola bilmir | ✅ | `LiveTest::test_only_paid_students…`, `MentoringTest::test_hidden_and_403…`, `AssignmentFileTest::test_demo_and_unpaid_users_get_403…` |
| Bir kursun ödənişi digərinə giriş vermir | ✅ | `BillingTest::test_price_comes_from_server…`, `LessonAccessTest::test_payment_for_one_course…`, e2e |
| Tələbə başqasının tapşırığını, faylını, qeydlərini, diplomunu görmür | ✅ | `OwnershipMatrixTest`, `AssignmentFileTest::test_download_requires_signature_and_ownership`, `DiplomaTest::test_other_student_cannot_download…`, `LessonAccessTest::test_notes_are_private…` |
| Təlimçi yalnız öz kursunu görür | ✅ | `OwnershipMatrixTest::test_instructor_isolation_across_courses`, `LessonAccessTest::test_instructor_sees_only_own_course` |
| Hər həssas endpoint üçün ownership testi (API + Playwright) | ✅ | `OwnershipMatrixTest` (qiymətləndirmə, rezerv, ödəniş, abunə, qeydlər, ixrac), `e2e/platform.e2e.mjs` |

### 3.2 Verilənlər bazası və authorization (PostgreSQL seçilib)
- **RLS 17 həssas cədvəldə** `ENABLE` + `FORCE`: `enrollments`, `lesson_progress`, `personal_notes`, `submissions`, `grades`, `uploaded_files`, `certificates`, `demo_claims`, `live_questions`, `mentor_bookings`, `notifications`, `user_sessions`, `video_token_log`, `payment_orders`, `payment_transactions`, `subscriptions`, `access_periods`.
- **Rollar.** `ms_app` (veb) və `ms_worker` (fon işləri) `NOBYPASSRLS`-dir, sxem sahibi deyil, DDL edə bilmir. `ms_owner` yalnız miqrasiya üçündür. `ms_backup` oxuma + BYPASSRLS, yalnız host cron-da.
- **Admin və təlimçi statusu** `app.user_id`-dən deyil, `user_roles`/`course_instructors` cədvəllərindən SECURITY DEFINER funksiyaları ilə oxunur (saxta `app.role` heç nə vermir).
- **RLS tətbiq qatını əvəz etmir.** Hər iki qat ayrıca test olunur: Policy qatı feature testləri ilə, RLS isə tətbiqi yan keçən birbaşa SQL ilə (`RlsTest`, `LessonAccessTest::test_rls_*`, `OwnershipMatrixTest`).
- **Parametrləşdirilmiş sorğular.** Bütün sorğular binding istifadə edir. Dəyişən interpolyasiyası yalnız miqrasiyalarda, validasiya olunmuş identifikatorlarla (`Rls::ident`). CI-da Semgrep qaydası var (`.semgrep.yml`).
- **Dəyişməzlik.** `audit_logs` (UPDATE/DELETE trigger + REVOKE, owner üçün də), `grades` (bal və snapshot dəyişmir), `certificates` (snapshot dəyişmir).

### 3.3 Auth və sessiyalar

| Tələb | Nəticə | Test |
|---|---|---|
| Argon2id (m=64 MiB, t=3), login-də rehash | ✅ | `AuthTest::test_password_hash_is_argon2id` |
| Cookie: HttpOnly, Secure, SameSite=Lax, `__Secure-` prefiksi, Path=/platform | ✅ | `AuthTest::test_session_cookie_flags`, e2e |
| Session rotation girişdə və 2FA-dan sonra | ✅ | `AuthTest::test_login_rotates_session…`, `TwoFactorController` |
| Logout: server-side ləğv | ✅ | `AuthTest::test_logout_revokes_server_session`, `test_revoked_session_is_rejected` |
| E-mail enumeration qoruması (giriş, qeydiyyat, parol bərpası, demo); sabit vaxtlı dummy hash | ✅ | `AuthTest::test_login_errors_do_not_reveal…`, `test_duplicate_registration…`, `test_password_reset_is_enumeration_safe…`, `DemoTest::test_existing_account…` |
| Parol bərpası tokeni: 256 bit, hash, 1 saat, birdəfəlik, istifadədən sonra bütün sessiyalar ləğv | ✅ | `AuthTest::test_password_reset_is_enumeration_safe_single_use_and_revokes_sessions` |
| TOTP 2FA (admin və təlimçi üçün məcburi), replay qoruması, recovery kodları (HMAC, birdəfəlik) | ✅ | `TwoFactorTest` (5 test), e2e admin |
| E-mail və demo linki: hash, birdəfəlik, expiry, resend limit, köhnə link etibarsızlaşır | ✅ | `AuthTest`, `DemoTest::test_resend_does_not_create_new_demo…` |
| Eyni vaxtda sessiya limiti (2 cihaz, konfiqurasiya olunur) | ✅ | `AuthTest::test_concurrent_session_limit_revokes_oldest` |
| Staff sessiyası 2 saat hərəkətsizlikdən sonra bitir; həssas əməliyyatlarda təkrar parol | ✅ | `TwoFactorTest::test_staff_session_expires_after_idle`, `AdminContentTest::test_publish_requires_recent_password_confirmation` |

### 3.4 Rate limit (hamısı test olunur)
Giriş (`AuthTest`), parol bərpası, video token, progress, fayl yükləmə, webhook, 2FA (`RateLimitTest`), demo qeydiyyatı (`DemoTest::test_registration_rate_limited_per_ip`), açıq diplom yoxlaması (`DiplomaTest::test_public_verification…`).

### 3.5 Demo limitini keçmə cəhdləri
Eyni e-mail, `+tag`, Gmail nöqtələri, `googlemail.com`, böyük/kiçik hərf, telefon format variantları, kursu dəyişmək, paralel qeydiyyat (eyni e-mail və variantlarla), birdəfəlik domenlər (subdomen daxil). Hamısı bloklanır, unikallıq DB səviyyəsindədir (`DemoTest`, 21 test). **Qalıq risk:** telefon SMS ilə təsdiqlənmir (bölmə 5).

### 3.6 Video
- Token TTL ≤ 10 dəqiqədir; demo üçün `demo_expires_at`-ı, ödənişli üçün `grace_until`-i keçmir. Hər yeniləmədə icazə yenidən yoxlanılır (`LessonAccessTest`, `DemoTest`).
- Token videoya və vaxta bağlıdır (Bunny path token). Domen və referer məhdudiyyəti Bunny Pull Zone-da qurulur (deploy checklist).
- Birbaşa MP4 linki yoxdur, yalnız HLS playlist (`test_signed_url_format_and_no_direct_mp4`).
- Watermark (e-mail + qısa kod, telefon yox) tam ekranda da qalır. Sessiya limiti var. Qeyri-adi həcmdə token jurnalı və audit xəbərdarlığı yazılır.
- **100% yükləmə qoruması mümkün deyil** (bölmə 5).

### 3.7 Fayl yükləmə
Tip yoxlaması magic bytes ilə, ölçü, storage kvotası, fayl sayı; makro formatları və `vbaProject.bin`; ZIP bomb (açılış ölçüsü, nisbət, fayl sayı); path traversal; random adlar (`.bin`, icra olunmur); private storage; 5 dəqiqəlik imzalı linklər; `attachment` + `nosniff` + `sandbox` CSP. **Real ClamAV EICAR testi:** karantin, sahibinə bildiriş, heç kimə endirilmir. Skan olunmamış və ya ClamAV əlçatmaz olanda fayl endirilmir (fail-closed). Testlər: `FileValidatorTest`, `AssignmentFileTest`.

### 3.8 XSS, injection, CSRF, CORS, SSRF, açıq redirect
- **XSS.** Blade avtomatik escape edir. Tapşırıq cavabları HTMLPurifier ilə ciddi ağ siyahıdan keçir (`SanitizerTest` — 10 vektor). Qeydlər, suallar və rəylər düz mətndir. CSP-də `unsafe-inline` yoxdur, skriptlər nonce və ya `'self'` ilədir.
- **CSRF.** Laravel 13 `PreventRequestForgery`: token və ya `Sec-Fetch-Site: same-origin`. Demo endpoint-i token əvəzinə `Sec-Fetch-Site`/`Origin` yoxlaması aparır. Webhook imza ilə qorunur (`HttpSecurityTest::test_csrf_blocks…`, `DemoTest::test_cross_site_submission_rejected`).
- **CORS.** Açılmır: `/platform` landing ilə eyni origin-dir (`OwnershipMatrixTest::test_no_cors_headers…`).
- **SSRF.** Xarici HTTP yalnız sabit hostlara gedir (Bunny, Epoint). İstifadəçi URL-ləri (material linkləri) yalnız saxlanılır, server onları çağırmır. `allow_url_fopen=Off`.
- **Açıq redirect.** Giriş sonrası yalnız sessiyadakı daxili URL istifadə olunur. Ödəniş üçün host ağ siyahısı var (F8).

### 3.9 HTTP başlıqları
Platforma: CSP (nonce, `frame-ancestors 'none'`, `object-src 'none'`, `base-uri 'none'`), HSTS, nosniff, XFO DENY, Referrer-Policy (link səhifələrində `no-referrer`), Permissions-Policy, COOP, `X-Robots-Tag: noindex`, `Cache-Control: private, no-store`. Landing: `netlify.toml` (F7).

### 3.10 Ödəniş
- **Webhook imzası** sabit vaxtlı müqayisə ilə yoxlanılır.
- **Replay və dublikat.** `UNIQUE(provider, provider_event_id)`, txn və `access_periods.transaction_id` üzərində UNIQUE.
- **Race.** 4 paralel proses, bir tətbiq.
- **Server-to-server təsdiq.** Webhook və redirect iddiasına təkbaşına etibar edilmir.
- **Məbləğ** serverdə hesablanır. Uyğunsuzluqda tətbiq edilmir və audit-ə yazılır.
- **Kart məlumatı saxlanmır:** yalnız şifrələnmiş token və son 4 rəqəm.
- **Status yalnız irəli gedir**, ardıcıllıqdan kənar hadisə statusu geri salmır.
- **Diplom** ödəniş bitəndən sonra da qalır.
- **Testlər:** `BillingTest` (19), `EpointProviderTest`.

### 3.11 1:1 rezerv və diplom
- **Rezerv.** EXCLUDE (slot üst-üstə düşmür); partial UNIQUE (bir slot bir dəfə, bir hüquq); `FOR UPDATE`. 24 saat qaydası serverdədir. Paralel testlər var.
- **Diplom.** Ownership; 128-bit ID; rate limit; snapshot dəyişməzdir (DB trigger); 74,9/75% sərhəd testləri var.

### 3.12 Sirlər, SAST, asılılıqlar, mühitlər
- **Sirlər.** `.env` gitignore-dadır, `.env.example` sənədləşib. `.env.testing` yalnız saxta test açarlarını saxlayır (gitleaks allowlist). CI-da gitleaks bütün tarixçəni skan edir. Bu sessiyada gitleaks binary-si əlçatmaz idi; əvəzində regex yoxlaması aparıldı və real sirr tapılmadı.
- **Açarların rotasiyası.**
  - `APP_KEY`: Laravel `APP_PREVIOUS_KEYS`. Şifrələnmiş sahələr: 2FA secret, görüş linkləri, kart tokeni, outbox linkləri.
  - DB parolları: `ALTER ROLE` + `.env`.
  - `PLATFORM_PROXY_SECRET`: Netlify və `.env` eyni anda dəyişir.
  - Bunny və Epoint: provayder panelindən.
- **SAST.** CI-da Semgrep (`p/php`, `p/javascript`, `p/secrets`) və layihənin öz qaydaları işləyir. Bu sessiyada Semgrep registry-si əlçatmaz idi; eyni qaydalar grep ilə yoxlandı (bölmə 4.1).
- **Asılılıqlar.** `composer audit` təmizdir, platformada `npm audit` təmizdir, landing-də `npm audit --omit=dev` təmizdir (F12). Lockfile-lar commit olunub. Dependabot (composer, npm, actions, docker) aktivdir.
- **Mühitlər.** Staging ayrı Compose layihəsidir: ayrı DB, ayrı açarlar, ödəniş sandbox-u, Basic Auth. Real istifadəçi məlumatı staging-ə kopyalanmır; `platform:dev-seed` production-da işləmir.

### 3.13 Decap CMS, spam, log/monitorinq, backup
- **Decap CMS.** OAuth app minimal scope (`public_repo` və ya fine-grained token yalnız bu repoya). Redaktorlar repoya "Write" hüququ alır, `/admin` noindex-dir. SRI və CSP var (F5).
- **Spam.** Honeypot, minimum doldurma vaxtı, rate limit (landing və platforma formaları). CAPTCHA yoxdur (1c-9 default).
- **Log və monitorinq.**
  - Laravel daily log yazılır. Şübhəli fəaliyyət audit-ə düşür: 2FA uğursuzluğu, webhook imza xətası, video anomaliyası, məbləğ uyğunsuzluğu.
  - IP-lər HMAC ilə yazılır. Tokenlər, parollar və kart məlumatları log-a və audit-ə yazılmır (`Audit::scrub`).
  - Audit log dəyişməzdir: trigger + hash zənciri, `platform:audit:verify` və admin paneli ilə yoxlanılır.
- **Backup.** Gündəlik `age` ilə public key-ə şifrələnir və ayrıca yerə köçürülür. Real restore testi aparılıb (`docs/backup-restore.md`). Rollback planı `docs/operations.md`-dədir.

## 4. Bu sessiyada aparılan yoxlamalar

### 4.1 Avtomatik
| Yoxlama | Nəticə |
|---|---|
| PHPUnit (real PostgreSQL 16, real ClamAV, pcntl paralel testlər) | bütün testlər keçir (README-də cari say) |
| Landing `npm test` (build + i18n --check + 14 test) | keçir |
| Playwright e2e (3 axın, Chromium) | keçir |
| `composer audit` | 0 advisory |
| `npm audit` (platform) / `npm audit --omit=dev` (landing) | 0 |
| Grep SAST: `{!! !!}` (4, hamısı təhlükəsiz), SQL interpolyasiyası (yalnız validasiya olunmuş identifikatorla miqrasiya köməkçisi), `dd/var_dump`, `env()` config xaricində, `eval/exec` | təmiz |
| Real backup → restore (ayrı DB), sətir sayı, RLS policy-ləri, trigger-lər, audit zənciri | keçir |

### 4.2 Bu sessiyada mümkün olmayanlar (staging-də edilməlidir)
- Real Netlify imzalı proxy: `Set-Cookie` ötürülməsi, 26 saniyə limiti, `x-nf-sign` formatı.
- Real Bunny token formatı, MediaCage və referer məhdudiyyəti.
- Epoint sandbox (endpoint adları SDK-lardan götürülüb, `docs/payment-providers.md`).
- Real SMTP, SPF/DKIM/DMARC.
- gitleaks və Semgrep registry qaydaları (CI-da işləyir).
- Production performans ölçmələri (`docs/performance.md`).

## 5. Qalıq risklər

1. **Telefon əsaslı demo identifikasiyası.** Telefon SMS OTP ilə təsdiqlənmir (1b qərarı), ona görə başqa nömrə və başqa (birdəfəlik olmayan) e-maillə ikinci demo almaq mümkündür. Azaldan tədbirlər:
   - normallaşdırma;
   - birdəfəlik domen siyahısı;
   - IP və e-mail üzrə rate limit;
   - demo yalnız 24 saat və 2 modul verir;
   - admin analitikasında demo qeydiyyatları görünür.
2. **Video ekran yazısı.** Ekran yazısının qarşısını almaq texniki olaraq mümkün deyil. Qatlar:
   - qısa ömürlü imzalı tokenlər və yeniləmədə icazə yoxlaması;
   - referer məhdudiyyəti;
   - dinamik watermark (sızan nüsxənin mənbəyini göstərir);
   - sessiya limiti, anomaliya jurnalı.

   Seqment şifrələməsi (MediaCage Basic) öz player-imizlə işləyirsə aktivləşdiriləcək — staging-də yoxlanmalıdır. Widevine/FairPlay (Enterprise DRM) əlavə xərcdir.
3. **Eyni origin.** Landing və platforma eyni origin-dədir (`/platform` seçimi). Landing-dəki XSS və ya CMS kompromisi platformaya eyni origin hüququ verir. Azaldan tədbirlər: CSP, SRI, CMS-də yalnız etibarlı redaktorlar, həssas əməliyyatlarda təkrar parol.
4. **Hostinq.**
   - Tək VPS-dir; staging resursları paylaşır.
   - ClamAV üçün ≥ 4 GB RAM lazımdır.
   - SMTP limiti və provayderi hələ məlum deyil.
   - Netlify proxy-dən asılılıq: 26 saniyə limiti, Netlify-ın əlçatanlığı.
   - Origin imza sirri sızarsa, VPS-ə birbaşa sorğu mümkün olar (tətbiq auth-u yenə işləyir). Sirri rotasiya edin.
5. **Ödəniş provayderi.** Epoint callback imzası SHA-1 əsaslıdır (provayderin sxemi). Bu, server-to-server status sorğusu ilə kompensasiya olunur. API endpoint-ləri sandbox-da təsdiqlənməlidir.
6. **RLS konteksti tətbiq tərəfindən yazılır.** RLS avtorizasiya səhvlərinə qarşı ikinci qatdır. Tam tətbiq kompromisinə (məsələn, SQL injection ilə `set_config`) qarşı müdafiə deyil. Əsas müdafiə parametrləşdirilmiş sorğular və Policy qatıdır.
7. **Audit log.** Hash zənciri dəyişikliyi aşkarlayır. PostgreSQL superuser-i bütün zənciri yenidən yaza bilər. Offsite şifrələnmiş backup-lar müqayisə üçün istinad nöqtəsidir.
8. **Hesabın silinməsi.** Demo identifikatorları HMAC kimi saxlanılır, demo sui-istifadəsinin qarşısı üçün. Açıq diplom qeydi (ad, kurs, tarix) qalır (1c-14 default). Hər ikisi məxfilik siyasətində yazılıb və sahibin təsdiqini gözləyir.
9. **Performans hədəfləri real mühitdə ölçülməyib** (`docs/performance.md`).
