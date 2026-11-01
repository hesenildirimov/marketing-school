# Prompt: Marketing School — tədris platforması (demo və kurs hissəsi)

> Bu mətni AI kod köməkçisinə (Claude Code və s.) olduğu kimi verin. Repo: `hesenildirimov/marketing-school`.

---

Sən Marketing School üçün **onlayn tədris platformasını** quracaqsan. Bu, istifadəçinin pulsuz demo aldıqdan sonra daxil olduğu hissədir: videodərslər, ev tapşırıqları, canlı görüşlər, 1:1 mentorluq və diplom. Landing sayt artıq hazırdır. Sən onun davamını yazırsan və bitirəndə tam **security audit** edirsən.

## 0. İşə başlamazdan əvvəl
1. `README.md`-ni və bütün `src/` qovluğunu oxu. Landing-in necə qurulduğunu, dizayn sistemini və məzmun fayllarını başa düş.
2. Mənə əvvəlcə bunları təqdim et:
   - **Arxitektura təklifi**: stack, hosting, video provayderi, verilənlər bazası sxemi, auth axını;
   - **Mərhələli plan**;
   - **Aşağıdakı "Açıq qərarlar" bölməsindəki suallar**.
3. Qərarlar təsdiqlənməmiş böyük kod yazma.
4. Hər mərhələni ayrıca branch və PR ilə təqdim et. `main`-ə birbaşa push etmə.
5. Heç vaxt sirr (API key, token, parol) commit etmə. Hamısı `.env`-də olsun və `.env.example` ilə sənədləşsin.

## 1. Mövcud layihə (bilməli olduqların)
- **Landing**: Eleventy 3 statik sayt, Netlify-da, Decap CMS (`/admin`). Dil: **Azərbaycan dili**. Hədəf domen: `marketingschool.az`.
- **Məzmun mənbəyi**:
  - `src/_data/kurslar/1-marketinq.json` və `2-reqemsal.json`. Burada kurs adı, slug, modullar (`modules[].title/text`) və təlimçi var.
  - `src/_data/site.json`: əlaqə, forma mətnləri.
  - `src/_data/faq.json`.
- **İki kurs**:
  - **Marketinq Kursu** (`/marketinq-kursu/`): 9 modul, təlimçi **Həsənalı İldırımov**.
  - **Rəqəmsal Marketinq Kursu** (`/digital-marketinq-kursu/`): 10 modul, təlimçi **Zamir Şabanov**.
- **Kurs formatı** (saytda yazılan vədlər, platforma bunlara tam uyğun olmalıdır):
  - müddət 3 ay, tam onlayn;
  - qeydə alınmış videodərslər, istənilən vaxt, öz tempində baxılır (Udemy kimi);
  - hər modulda **real keyslərə əsaslanan ev tapşırığı**;
  - **2 həftədən bir canlı onlayn sual-cavab** görüşü;
  - kurs sonunda **hər tələbə ilə fərdi (1:1) mentorluq sessiyası**;
  - kursu bitirənlərə **diplom (sertifikat)**.
- **Pulsuz demo**: landing-də "Pulsuz demo dərsə bax" CTA-sı var. Forma (`src/_includes/form.njk`) indi Netlify Forms-a ad, e-mail, kurs seçimi və **məcburi telefon** göndərir. Vəd belədir: **platformaya 1 günlük pulsuz giriş**.
- **Dizayn sistemi** (`src/assets/style.css`). Platforma landing ilə eyni görünməlidir:
  - əsas rəng sarı, amma **yumşaq**: açıq krem-sarı fonlar (`#FFF6D6`, `#FFFBE8`), dolğun sarı (`#FFC400`) yalnız hover və vurğuda;
  - mətn tünd (`#0E1324`);
  - göy, yaşıl, bənövşəyi kimi əlavə vurğu rəngi yoxdur;
  - tək şrift ailəsi (sistem şrifti), xarici şrift yoxdur;
  - yığcam düzülüş, çox boşluq yoxdur;
  - yüngül animasiyalar, `prefers-reduced-motion`-a hörmət edilir.
  - CSS dəyişənlərini (`--brand`, `--brand-soft`, `--ink`, `--line`, `--r` və s.) təkrar istifadə et.
- Qiymət, ödəniş üsulu və videoların uzunluğu hələ məlum deyil. Rəqəm uydurma, yer tutucu qoy.

## 2. Qurulacaq funksionallıq

### 2.1 Rollar və giriş
- Rollar:
  - `demo`: 24 saatlıq;
  - `student`: kursu alıb, hər kurs üçün ayrıca `enrollment`;
  - `instructor`: yalnız öz kursu;
  - `admin`.
- Qeydiyyat və giriş: e-mail təsdiqi məcburidir. Telefon toplanır (landing-də olduğu kimi məcburi). İstəyə görə SMS OTP.
- Admin və təlimçilər üçün **2FA məcburidir**.
- Bütün icazə yoxlamaları **serverdə** aparılsın. UI-da gizlətmək təhlükəsizlik sayılmır.

### 2.2 Demo axını (1 gün)
- Landing CTA → platformada demo qeydiyyatı. Landing formasını ya platforma API-sinə yönləndir, ya da platformanın qeydiyyat səhifəsinə link et. Dizayn eyni qalsın.
- Demo aktivləşəndə serverdə `demo_expires_at = now + 24h` yazılır. Bitəndə giriş avtomatik bağlanır və "Kursu al" ekranı göstərilir.
- **Demoda yalnız ilk 2 modul açıqdır.** Digər modullar siyahıda görünür (ad, qısa təsvir, kilid ikonu, "Kursu alanda açılır"), amma:
  - kilidli dərslərin video tokeni, materialı, tapşırığı **serverdə heç verilmir**;
  - birbaşa URL ilə və ya API sorğusunu dəyişməklə (məs. `lessonId`-ni 3-cü modula dəyişmək) açmaq mümkün olmamalıdır.
- Demo istifadəçi üçün bunlar **görünmür**: canlı görüş linkləri, 1:1 bölməsi, diplom və tapşırıq göndərmə. Tapşırıq mətnini oxuya bilər; göndərib-göndərməməsi açıq qərardır.
- Sui-istifadə qarşısı:
  - bir e-mail və bir telefon üçün bir demo;
  - birdəfəlik (disposable) e-mailləri blokla;
  - qeydiyyata rate limit;
  - vaxt yalnız server saatı ilə hesablanır, müştəri saatına etibar edilmir.

### 2.3 Video izləmə (rahat və yüklənməz)
- Video provayderi: signed URL və tokenli HLS dəstəkləyən idarə olunan servis, məsələn Mux, Cloudflare Stream və ya Bunny Stream. Seçimi əsaslandır.
- **Yükləmənin qarşısını alma**. Tam qarşısını almaq texniki olaraq mümkün deyil (ekran yazısı). Ona görə qatlarla çətinləşdir və bunu mənə açıq yaz:
  - birbaşa MP4 linki yoxdur, yalnız **şifrələnmiş HLS/DASH**;
  - **qısa ömürlü imzalı tokenlər**: 5–15 dəqiqə, istifadəçiyə və dərsə bağlı, serverdə yalnız icazə yoxlandıqdan sonra verilir;
  - domen və referer məhdudiyyəti (playback yalnız platforma domenində);
  - **dinamik watermark**: tələbənin e-maili və ya telefonu yarı-şəffaf, mövqeyi dəyişən;
  - player-də download düyməsi yoxdur, sağ klik və `controlsList="nodownload"` (bunlar kosmetikdir, əsas qoruma deyil);
  - eyni hesabdan **eyni vaxtda sessiya limiti** (hesab paylaşmanın qarşısı);
  - qeyri-adi izləmə həcminə görə xəbərdarlıq və log;
  - büdcə imkan verərsə DRM (Widevine/FairPlay) variantını qiymətləndir.
- **İzləmə rahatlığı**:
  - kaldığı yerdən davam (progress serverdə saxlanır);
  - sürət 0.75–2x, keyfiyyət seçimi, tam ekran;
  - klaviatura qısayolları (space, ←/→ 10 san, f);
  - fəsillər (chapters), istəyə görə subtitr;
  - "Növbəti dərs" düyməsi, dərsi "tamamlandı" işarələmək;
  - mobil uyğun, yavaş internetdə adaptiv bitrate.

### 2.4 Dərs səhifəsinin düzülüşü
- Masaüstü:
  - **solda** modul və dərs siyahısı: progress, kilidlər, tamamlanma işarələri;
  - **ortada** video;
  - **videonun altında, ortada** tablar: **Ev tapşırığı**, Materiallar, Qeydlər (şəxsi), Suallar (növbəti canlı görüşə sual göndərmək).
- Mobil: video yuxarıda, tablar altında, modul siyahısı açılan panel kimi.
- Yuxarıda kurs üzrə ümumi progress (%).

### 2.5 Ev tapşırıqları
- Hər modulda real keysə əsaslanan tapşırıq. Təlimçi admin paneldən yazır: mətn, keys faylı, son tarix (istəyə görə).
- Tələbə cavab göndərir: mətn redaktoru və fayl.
  - Fayl tipləri: PDF, DOCX, XLSX, PPTX, PNG, JPG.
  - Ölçü limiti var; tip yoxlaması MIME və məzmunla aparılır, yalnız uzantı ilə yox.
  - Antivirus yoxlaması aparılır.
  - Fayllar **private storage**-da saxlanır və yalnız sahibi ilə təlimçi qısa ömürlü link ilə açır.
- Statuslar: `göndərilməyib → göndərildi → yoxlanılır → qəbul edildi / düzəliş lazımdır`.
- Təlimçi rəyi yazır, tələbə bildiriş alır (e-mail və platformada).
- Tapşırıqlar canlı görüşlərdə müzakirə olunur. Təlimçi görüş üçün seçilmiş tapşırıqları işarələyə bilsin.

### 2.6 Canlı sual-cavab (2 həftədən bir)
- Təlimçi görüşləri planlayır: tarix və saat **Asia/Baku**, Zoom/Meet linki.
- Yalnız **ödənişli tələbələr**:
  - görüş linkini görür (link serverdə qorunur);
  - əvvəlcədən sual göndərə bilir;
  - görüşün yazısını sonradan izləyə bilir. Yazılar da 2.3-dəki kimi qorunur.
- Takvimə əlavə et (`.ics`), görüşdən 24 saat və 1 saat əvvəl xatırlatma.

### 2.7 1:1 mentorluq — təqvimlə
- **Kursu almayanlar bu bölməni ümumiyyətlə görməsin**: menyuda yoxdur, API 403 qaytarır. Demo istifadəçilər də görmür.
- Təlimçi admin paneldə boş vaxtlarını (slotları) təqvimdə təyin edir.
- Tələbə **bir sessiya** rezerv edir. Bölmə kursun sonunda açılır; unlock şərti açıq qərardır (aşağıya bax).
- Rezerv: təsdiq e-maili, `.ics`, görüş linki, xatırlatmalar, ləğv və vaxt dəyişmə qaydası (məs. 24 saat əvvələdək).
- Eyni slotu iki nəfər tuta bilməz: verilənlər bazasında unikal məhdudiyyət və tranzaksiya. Race condition testi yaz.
- İstəyə görə Cal.com embed və ya öz slot cədvəlin. Seçimi əsaslandır.

### 2.8 Diplom
- Şərt: bütün modullar tamamlanıb və bütün tapşırıqlar "qəbul edildi"; dəqiq şərti təsdiqlət.
- Avtomatik PDF diplom: ad, kurs, tarix, təlimçi və **unikal yoxlama ID-si**.
- Açıq yoxlama səhifəsi `/diplom/{id}`: yalnız ad, kurs və tarix göstərir, şəxsi məlumat yoxdur. LinkedIn-ə əlavə linki.

### 2.9 Admin / təlimçi paneli
- Kurs, modul və dərs idarəsi: video yükləmə, sıralama, demo üçün açıq modulların seçimi (default: ilk 2 modul).
- Tələbələr:
  - axtarış;
  - enrollment aktivləşdirmə: əvvəl əl ilə, sonra ödənişlə;
  - demo istifadəçiləri və konversiya;
  - progress.
- Tapşırıqların yoxlanması növbəsi, canlı görüşlərin və 1:1 slotlarının idarəsi.
- Audit log: kim, nə vaxt, nəyi dəyişdi.

### 2.10 Ödəniş (mərhələli)
- **Mərhələ 1**: admin enrollment-i əl ilə aktivləşdirir.
- **Mərhələ 2**: Azərbaycan ödəniş provayderi ilə inteqrasiya. Variantları araşdır, mənə təklif et.
  - Webhook imzası yoxlanılmalı, idempotent olmalı, məbləğ serverdə hesablanmalıdır.

### 2.11 Landing ilə əlaqə
- Landing statik qalır. Platforma ayrıca app olsun, məsələn `app.marketingschool.az` və ya `/platform`.
- Kurs və modul adları üçün tək həqiqət mənbəyi seç: ya `src/_data/kurslar/*.json`, ya platforma DB-si. İkisinin fərqli olmaması üçün sinxronizasiya və ya import skripti yaz.
- Platforma `noindex` olsun və `robots`-da bağlansın. SEO, schema və `llms.txt` yalnız landing-dədir.
- Landing-dəki "Pulsuz demo" CTA-sı və təşəkkür səhifəsi yeni axına uyğunlaşdırılsın.

### 2.12 Hüquqi və məxfilik
- Məxfilik siyasəti və istifadə şərtləri səhifələri (yer tutucu mətnlə; hüquqi mətni sahibi təsdiqləyəcək).
- Azərbaycanın "Fərdi məlumatlar haqqında" Qanununa uyğun minimal məlumat toplama, açıq razılıq, hesabın silinməsi və məlumatların ixracı imkanı.

## 3. Açıq qərarlar — başlamazdan əvvəl məndən soruş
1. Stack: məsələn Next.js + Supabase (Postgres + RLS + Auth + Storage) və ya alternativ. Hosting, aylıq xərc təxmini.
2. Video provayderi və DRM lazımdırmı (büdcə).
3. Kursa giriş müddəti: 3 ay, 1 il, ömürlük?
4. 1:1 bölməsi nə vaxt açılır: son ayda, müəyyən progress faizindən sonra, yoxsa bütün tapşırıqlar qəbul olunanda?
5. Demo istifadəçi ev tapşırığını görsün, yoxsa göndərə də bilsin?
6. Telefon üçün SMS OTP istifadə olunsunmu (xərc)?
7. Ödəniş provayderi və hissə-hissə ödəniş.
8. Diplomun dəqiq şərti.

## 4. Bitəndə: Security audit (məcburi)
Bütün funksionallıq bitəndən sonra tam audit et, tapılanları düzəlt və hesabat yaz (`docs/security-audit.md`). OWASP Top 10 və OWASP ASVS Level 2 əsas götürülsün. Minimum yoxlanacaqlar:

- **Access control / IDOR**:
  - demo istifadəçi 3-cü və sonrakı modulların dərsini, tokenini, materialını və tapşırığını heç bir yolla ala bilmir;
  - müddəti bitmiş demo bloklanır;
  - kursu almayan 1:1, canlı görüş linki, yazılar və diplom endpoint-lərinə daxil ola bilmir;
  - tələbə başqasının tapşırığını, faylını və diplomunu görə bilmir;
  - təlimçi yalnız öz kursunu görür.
  - Bütün bunlar üçün **avtomatik testlər** olsun: API səviyyəsində və Playwright e2e.
- **Verilənlər bazası**: RLS siyasətləri hər cədvəldə aktivdir və testlə yoxlanılıb. Service key heç vaxt client-ə getmir.
- **Auth**:
  - parol hashing;
  - sessiyaların ləğvi və rotasiyası;
  - brute-force qoruması və rate limit;
  - e-mail təsdiqi;
  - admin 2FA;
  - parol bərpası axınının təhlükəsizliyi.
- **Video**:
  - token TTL;
  - tokenin başqa istifadəçi və ya domendə işləməməsi;
  - HLS açarlarının qorunması;
  - birbaşa fayl linklərinin olmaması;
  - watermark;
  - eyni vaxtda sessiya limiti.
  - Qeyd: 100% yükləmə qorunması mümkün deyil, qalan riski hesabatda açıq yaz.
- **Fayl yükləmə**:
  - tip və ölçü yoxlaması;
  - antivirus;
  - private bucket və imzalı linklər;
  - fayl adlarının sanitizasiyası;
  - yüklənmiş faylın icra olunmaması.
- **XSS və injection**:
  - tapşırıq cavabları, rəylər və sualların render-i sanitizasiya olunur;
  - parametrləşdirilmiş sorğular.
- **CSRF**, **CORS** (yalnız öz domenlər), **SSRF**, açıq redirect.
- **HTTP başlıqları**: CSP, HSTS, `frame-ancestors`, Referrer-Policy, Permissions-Policy, X-Content-Type-Options. Landing-in `netlify.toml`-u da yoxlanılsın.
- **Ödəniş**: webhook imza yoxlaması, idempotentlik, məbləğin serverdə hesablanması, enrollment-in yalnız təsdiqlənmiş ödənişdən sonra açılması.
- **1:1 rezerv**: race condition (eyni slot iki nəfərə), ləğv qaydalarının serverdə tətbiqi.
- **Sirlər**: repo tarixçəsində sirr yoxdur (`gitleaks` və ya oxşarı ilə yoxla), `.env` gitignore-dadır, açarlar rotasiya oluna bilir.
- **Asılılıqlar**: `npm audit`, Dependabot və ya Renovate aktiv, lockfile commit olunub.
- **Decap CMS**: OAuth app minimal scope, redaktor icazələri, `/admin` noindex.
- **Spam**: landing və demo formalarında spam qoruması (honeypot artıq var, lazım olsa Turnstile və ya hCaptcha).
- **Log, monitorinq, backup**:
  - xəta monitorinqi;
  - şübhəli fəaliyyət logları (şəxsi məlumatı log-a yazmadan);
  - gündəlik DB backup və bərpa testi.

Hesabatda hər tapıntı üçün bunlar yazılsın: risk səviyyəsi, təsvir, necə düzəldildi və hansı test onu yoxlayır.

## 5. Hazırdır demək üçün şərtlər (Definition of Done)
- [ ] Demo: qeydiyyat → 24 saat → yalnız 2 modul açıq → vaxt bitəndə bağlanır. Hamısı testlə yoxlanılıb.
- [ ] Ödənişli tələbə bütün modulları, tapşırıqları, canlı görüşləri, 1:1 rezervi və diplomu istifadə edə bilir.
- [ ] Kursu almayanlar 1:1 bölməsini görmür və API 403 qaytarır.
- [ ] Videolar birbaşa yüklənmir: imzalı tokenlər, watermark, sessiya limiti var.
- [ ] Dərs səhifəsi: solda modullar, ortada video və altında tapşırıq paneli. Mobil uyğundur.
- [ ] Admin paneldən kurs, dərs, tapşırıq, görüş, slot və tələbə idarə olunur.
- [ ] Bütün UI Azərbaycan dilindədir və landing-in dizayn sisteminə uyğundur.
- [ ] Security audit hesabatı hazırdır, kritik və yüksək riskli tapıntı qalmayıb.
- [ ] README yenilənib: quraşdırma, `.env.example`, deploy, admin istifadəsi, backup və bərpa.
- [ ] Lighthouse: performans ≥ 90, əlçatanlıq ≥ 95. Əsas axınlar üçün e2e testlər CI-da işləyir.
