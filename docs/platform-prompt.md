# Prompt: Marketing School — tədris platforması (demo və kurs hissəsi)

> Bu mətni AI kod köməkçisinə (Claude Code və s.) olduğu kimi verin. Repo: `hesenildirimov/marketing-school`.

---

Sən Marketing School üçün **onlayn tədris platformasını** quracaqsan. Bu, istifadəçinin pulsuz demo aldıqdan sonra daxil olduğu hissədir: videodərslər, ev tapşırıqları, canlı görüşlər, 1:1 mentorluq və diplom. Landing sayt artıq hazırdır. Sən onun davamını yazırsan və bitirəndə tam **security audit** edirsən.

## 0. İşə başlamazdan əvvəl
1. `README.md`-ni və bütün `src/` qovluğunu oxu. Landing-in necə qurulduğunu, dizayn sistemini və məzmun fayllarını başa düş.
2. **Bölmə 3-dəki hosting suallarını mənə ver və cavabı gözlə**, sonra mənə bunları təqdim et:
   - **Arxitektura təklifi**: stack (bölmə 3-dəki qaydaya görə sən seç və əsaslandır), video yanaşması, verilənlər bazası sxemi, auth axını;
   - **Mərhələli plan**.
   Bölmə 1b-dəki biznes qərarları **artıq qəbul olunub**, yenidən soruşma.
3. Arxitektura təklifi təsdiqlənməmiş böyük kod yazma.
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
- Videoların ümumi uzunluğu və saatı hələ məlum deyil. Rəqəm uydurma, yer tutucu qoy. Qiymət və ödəniş qaydası bölmə 1b-də qəbul edilib.

## 1b. Qəbul olunmuş biznes qərarları (dəyişdirmə)
- **Qiymət:** aylıq abunə **99 AZN**. Hissə-hissə ödəniş yoxdur. Kurs 3 aylıqdır, tələbə hər ay yenidən ödəyir. Giriş yalnız ödənişi aktiv olan müddət ərzində açıqdır (`paid_until`).
- **Canlı sual-cavab:** hər ayın **2-ci və 4-cü həftəsinin 6-cı günü (şənbə), saat 11:00 (Asia/Baku)**. Cədvəl sistemdə qaydadan avtomatik yaradılsın, admin hər birini ayrıca dəyişə və ya ləğv edə bilsin. "2-ci və 4-cü həftənin şənbəsi"ni ayın 2-ci və 4-cü şənbəsi kimi qəbul et və bunu mənə yazılı təsdiqlət.
- **1:1 mentorluq** yalnız tələbə **bütün modulları bitirəndə** açılır (hər modulun bütün dərsləri tamamlanıb).
- **Diplom:** ev tapşırıqlarının **ballarının cəmi mümkün ümumi balın ≥ 75%-i** olanda. Tapşırığı etməyən 0 bal alır. Diplom üçün ən azı bir tapşırığın göndərilməsi yetərli deyil, yalnız 75% şərti keçərlidir. Bal təlimçi tərəfindən verilir (hər tapşırıq üçün maksimum bal təlimçi təyin edir).
- **Demo istifadəçi ev tapşırığını heç görmür** (tab da yoxdur, API də 403 qaytarır).
- **SMS təsdiqi (OTP) yoxdur.** Telefon yalnız toplanır, təsdiqlənmir.
- **Ödəniş:** Epoint və ya Payriff (bax: 2.10).
- **Videolar 1080p** olacaq.
- **Texnologiya seçimi sənindir** (bölmə 3). Layihəni tamamilə AI ilə quracağıq, ona görə seçimi də sən ən təhlükəsiz və ən az xəta ehtimalı olan yola görə et.

## 2. Qurulacaq funksionallıq

### 2.1 Rollar və giriş
- Rollar:
  - `demo`: 24 saatlıq;
  - `student`: kursu alıb, hər kurs üçün ayrıca `enrollment`;
  - `instructor`: yalnız öz kursu;
  - `admin`.
- Qeydiyyat və giriş: e-mail təsdiqi məcburidir. Telefon toplanır (landing-də olduğu kimi məcburi). İstəyə görə SMS OTP.
- Admin və təlimçilər üçün **2FA məcburidir**.
- Hər `enrollment`-in statusu: `active`, `past_due` (ödəniş gecikib, qısa güzəşt müddəti, məs. 3 gün, təlimçi və admin dəyişə bilər), `expired`, `canceled`. **Yalnız `active` və güzəşt müddətində olan `past_due` giriş verir.** Bitəndə tələbənin tapşırıqları, balları və tamamlanma qeydləri silinmir, ödəniş bərpa olunanda qaldığı yerdən davam edir.
- Bütün icazə yoxlamaları **serverdə** aparılsın. UI-da gizlətmək təhlükəsizlik sayılmır.

### 2.2 Demo axını (1 gün)
- Landing CTA → platformada demo qeydiyyatı. Landing formasını ya platforma API-sinə yönləndir, ya da platformanın qeydiyyat səhifəsinə link et. Dizayn eyni qalsın.
- Demo aktivləşəndə serverdə `demo_expires_at = now + 24h` yazılır. Bitəndə giriş avtomatik bağlanır və "Kursu al" ekranı göstərilir.
- **Demoda yalnız ilk 2 modul açıqdır.** Digər modullar siyahıda görünür (ad, qısa təsvir, kilid ikonu, "Kursu alanda açılır"), amma:
  - kilidli dərslərin video tokeni, materialı, tapşırığı **serverdə heç verilmir**;
  - birbaşa URL ilə və ya API sorğusunu dəyişməklə (məs. `lessonId`-ni 3-cü modula dəyişmək) açmaq mümkün olmamalıdır.
- Demo istifadəçi üçün bunlar **heç görünmür**: **ev tapşırığı (mətn də, tab da)**, canlı görüş linkləri, 1:1 bölməsi, diplom. Bu hissələr menyuda və UI-da yoxdur, API-lar 403 qaytarır.
- Sui-istifadə qarşısı:
  - bir e-mail və bir telefon üçün bir demo;
  - birdəfəlik (disposable) e-mailləri blokla;
  - qeydiyyata rate limit;
  - vaxt yalnız server saatı ilə hesablanır, müştəri saatına etibar edilmir.

### 2.3 Video izləmə (rahat və yüklənməz)
- Video yanaşması bölmə 3-dəki **Variant A/B** qaydasına görə seçilir. Hər iki halda yalnız şifrələnmiş və tokenlə qorunan HLS axını işlənir.
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

#### 2.3b 1080p optimallaşdırması (player + kodlaşdırma)
Məqsəd: video **tez başlasın, donmasın, yavaş internetdə də işləsin**, 1080p isə yalnız bant genişliyi imkan verəndə göstərilsin.
- **Kodlaşdırma (ffmpeg və ya provayderin preseti):** H.264 High, AAC 128 kbps, 2 saniyəlik keyframe, 4 saniyəlik seqment. Adaptiv keyfiyyət pillələri (təxmini):
  - 1080p ~4,5 Mbps;
  - 720p ~2,5 Mbps;
  - 480p ~1,2 Mbps;
  - 360p ~0,6 Mbps.
  Pillələri real kursun videosunda ölç və qərarı əsaslandır. Çıxış tələbi: görünüşün keyfiyyəti vizual itki olmadan, orta faylın ölçüsü isə mümkün qədər kiçik olsun.
- **Başlanğıc:** player ilk açılışda 720p ilə başlayır və bant genişliyi yaxşıdırsa 1080p-yə qalxır. `Save-Data` başlığı və ya mobil şəbəkədə 1080p avtomatik qadağan edilir, istifadəçi əl ilə seçə bilər.
- **Player:** yüngül açıq mənbəli player (məsələn Vidstack və ya hls.js + sadə UI). Ağır, yüklənməsi uzun player istifadə etmə. Player kodu yalnız dərs səhifəsində yüklənsin, landing-də yox.
- **Sürət:**
  - `preconnect`/`dns-prefetch` video domeninə;
  - poster şəkli (WebP, kiçik) ilə dərhal görünən səhifə, video isə klik və ya ekrana girəndə yüklənir (lazy);
  - növbəti dərsin ilk seqmentlərini yalnız cari dərsin sonuna yaxın əvvəlcədən yüklə;
  - buffer limiti (məs. 30–60 saniyə), mobil yaddaşı şişirtməsin;
  - kəsilmədən davam etmə (bərpa), şəbəkə düşəndə dostluq mesajı və avtomatik yenidən cəhd.
- **Ölçmə:** player metrikləri (ilk kadrın gəlmə vaxtı, buffer sayı və müddəti, orta keyfiyyət) toplansın, admin paneldə görünsün. Şəxsi məlumat yazılmasın.
- **Hədəflər:** ilk kadr < 2,5 san (orta bağlantıda), dərs səhifəsində Lighthouse performans ≥ 90, video ilə birlikdə Cumulative Layout Shift ≈ 0.

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
- Statuslar: `göndərilməyib → göndərildi → yoxlanılır → qiymətləndirildi` (və ya `düzəliş lazımdır`).
- **Hər tapşırığın maksimum balı var.** Təlimçi bal və rəy yazır. Tələbənin kursda topladığı bal və faiz (diplom üçün 75% hədd) həmişə dərs səhifəsində və profildə görünür.
- Təlimçi rəyi yazır, tələbə bildiriş alır (e-mail və platformada).
- Tapşırıqlar canlı görüşlərdə müzakirə olunur. Təlimçi görüş üçün seçilmiş tapşırıqları işarələyə bilsin.

### 2.6 Canlı sual-cavab (2 həftədən bir)
- Görüşlər bölmə 1b-dəki qayda ilə avtomatik yaranır (ayın 2-ci və 4-cü həftəsinin şənbəsi, 11:00 Asia/Baku). Təlimçi hər görüşə Zoom/Meet linki əlavə edir, tarixi dəyişə və ya ləğv edə bilər.
- Yalnız **ödənişli tələbələr**:
  - görüş linkini görür (link serverdə qorunur);
  - əvvəlcədən sual göndərə bilir;
  - görüşün yazısını sonradan izləyə bilir. Yazılar da 2.3-dəki kimi qorunur.
- Takvimə əlavə et (`.ics`), görüşdən 24 saat və 1 saat əvvəl xatırlatma.

### 2.7 1:1 mentorluq — təqvimlə
- **Kursu almayanlar bu bölməni ümumiyyətlə görməsin**: menyuda yoxdur, API 403 qaytarır. Demo istifadəçilər də görmür.
- Təlimçi admin paneldə boş vaxtlarını (slotları) təqvimdə təyin edir.
- Tələbə **bir sessiya** rezerv edir. Bölmə yalnız **bütün modullar bitəndə** açılır (hər modulun bütün dərsləri tamamlanıb).
- Rezerv: təsdiq e-maili, `.ics`, görüş linki, xatırlatmalar, ləğv və vaxt dəyişmə qaydası (məs. 24 saat əvvələdək).
- Eyni slotu iki nəfər tuta bilməz: verilənlər bazasında unikal məhdudiyyət və tranzaksiya. Race condition testi yaz.
- İstəyə görə Cal.com embed və ya öz slot cədvəlin. Seçimi əsaslandır.

### 2.8 Diplom
- Şərt: tapşırıqların bal cəmi ≥ 75% (bölmə 1b). Tapşırığı etməyən 0 bal alır. Şərt ödənilən kimi diplom avtomatik yaranır və tələbə yükləyə bilir.
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

### 2.10 Ödəniş (aylıq 99 AZN)
- Provayder: **Epoint** və ya **Payriff**. İlk addım: hər ikisinin rəsmi sənədlərini oxu və mənə cədvəllə təqdim et:
  - təkrarlanan (avtomatik aylıq) ödəniş və ya kart tokenizasiyası dəstəyi;
  - webhook və imza yoxlaması;
  - komissiya;
  - geri qaytarma və sınaq mühiti.
  Avtomatik aylıq çıxarış dəstəklənmirsə, ehtiyat axın qur: hər ay ödəniş tarixindən 5 gün əvvəl e-mail ilə ödəniş linki göndərilir.
- Tələbə ödəyəndə `paid_until` bir ay uzanır. Ödəniş alınmayanda status `past_due`, güzəşt müddəti bitəndə `expired` olur. Bütün keçidlər serverdə və webhook ilə aparılır.
- Təhlükəsizlik: webhook imzası yoxlanılmalı, idempotent olmalı, məbləğ serverdə hesablanmalı, enrollment yalnız təsdiqlənmiş ödənişdən sonra açılmalıdır.
- **Mərhələ 1:** admin enrollment-i əl ilə aktivləşdirir. **Mərhələ 2:** onlayn ödəniş.

### 2.11 Landing ilə əlaqə
- Landing statik qalır. Platforma ayrıca app olsun, məsələn `app.marketingschool.az` və ya `/platform`.
- Kurs və modul adları üçün tək həqiqət mənbəyi seç: ya `src/_data/kurslar/*.json`, ya platforma DB-si. İkisinin fərqli olmaması üçün sinxronizasiya və ya import skripti yaz.
- Platforma `noindex` olsun və `robots`-da bağlansın. SEO, schema və `llms.txt` yalnız landing-dədir.
- Landing-dəki "Pulsuz demo" CTA-sı və təşəkkür səhifəsi yeni axına uyğunlaşdırılsın.

### 2.12 Hüquqi və məxfilik
- Məxfilik siyasəti və istifadə şərtləri səhifələri (yer tutucu mətnlə; hüquqi mətni sahibi təsdiqləyəcək).
- Azərbaycanın "Fərdi məlumatlar haqqında" Qanununa uyğun minimal məlumat toplama, açıq razılıq, hesabın silinməsi və məlumatların ixracı imkanı.

## 3. Texnologiya və hosting (əvvəl soruş, sonra seç)
Sistem **hostinq alınıb orada** qurulacaq. Bu, seçimi birbaşa təyin edir, ona görə işə başlamazdan əvvəl **mənə bu sualları ver və cavabı gözlə**:
1. Hostinqin növü: paylaşılan (cPanel), VPS, yoxsa ayrıca server? Provayder və plan.
2. Əməliyyat sistemi, Node.js / PHP / Python versiyaları, **SSH girişi**, cron, Docker və `ffmpeg` mövcudluğu.
3. CPU, RAM, disk, aylıq bant genişliyi limiti, eyni vaxtda neçə bağlantı.
4. Verilənlər bazası: MySQL, PostgreSQL və ya SQLite? Avtomatik backup var?
5. Domen və SSL: `app.marketingschool.az` və ya `/platform`.

**Seçim qaydası (sən qərar ver, əsaslandır):**
- **Paylaşılan hostinq (cPanel, yalnız PHP + MySQL):** Laravel. Node/Next.js bura düşmür.
- **VPS və ya ayrıca server (SSH var):** Next.js (və ya Laravel) + PostgreSQL, Nginx, **Docker Compose** ilə.
- Verilənlər bazası və auth səviyyəsində demo məhdudiyyətinin ("yalnız 2 modul") pozulmaması vacibdir. Çərçivə nə olursa olsun, icazələr mərkəzi **policy/gate** qatında yazılsın və testlə yoxlansın.
- Çox mürəkkəb, qeyri-adi və az dəstəklənən texnologiyadan qaç. Layihə tamamilə AI köməyi ilə aparılacaq, buna görə geniş yayılmış, sənədləşməsi yaxşı və təhlükəsiz standartları olan stack seç.

**Video yanaşması (hostinqə görə seç):**
- **Variant A (tövsiyə olunur): tətbiq hostinqdədir, videolar xüsusi video xidmətində.** Məsələn Bunny Stream (ucuzdur, tokenli HLS, əsas DRM). Səbəb: adi hostinqdə 1080p video yayımlamaq bant genişliyini və CPU-nu tez tükədir, eyni vaxtda çox tələbə olanda sayt çökə bilər, və yükləmədən qorumaq çətindir.
- **Variant B: videolar da öz serverindədir.** Yalnız güclü VPS və ya ayrıca serverdə mümkündür:
  - videoları ffmpeg ilə HLS-ə çevir (2.3b);
  - seqmentləri **şifrələ** (AES-128) və açarı yalnız icazə yoxlanılandan sonra qısa ömürlü token ilə ver;
  - Nginx `secure_link` və ya eyni məntiqlə imzalı URL-lər;
  - **CDN** (məsələn Bunny CDN) önündə olsun ki, server yüklənməsin;
  - bant genişliyi və maliyyə hesabı çıxar.
  Bu variantı seçirsənsə, risk və xərci mənə açıq yaz.
- Hostinq paylaşılan olarsa, yalnız Variant A mümkündür.

## 3b. Hələ açıq qalan məsələlər
- Ödəniş provayderinin son seçimi (Epoint və ya Payriff, 2.10-un nəticəsinə görə).
- `past_due` güzəşt müddəti (təklif: 3 gün) və ödəniş dayandıqda diplom hüququnun saxlanması (təklif: qazanılmış diplom həmişə qalır).

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
- [ ] Kursu almayanlar 1:1 bölməsini görmür və API 403 qaytarır. 1:1 yalnız bütün modullar bitəndə açılır.
- [ ] Demo istifadəçi ev tapşırığını heç görmür (UI və API).
- [ ] Diplom yalnız bal cəmi ≥ 75% olanda verilir. Sərhəd halları (74,9% / 75%) testlə yoxlanılıb.
- [ ] Ödəniş: aylıq 99 AZN, `paid_until` məntiqi, `past_due` və `expired` keçidləri və webhook testləri.
- [ ] 1080p video: adaptiv pillələr, 720p ilə başlanğıc, ilk kadr < 2,5 san, mobil məlumat qənaəti rejimi.
- [ ] Videolar birbaşa yüklənmir: imzalı tokenlər, watermark, sessiya limiti var.
- [ ] Dərs səhifəsi: solda modullar, ortada video və altında tapşırıq paneli. Mobil uyğundur.
- [ ] Admin paneldən kurs, dərs, tapşırıq, görüş, slot və tələbə idarə olunur.
- [ ] Bütün UI Azərbaycan dilindədir və landing-in dizayn sisteminə uyğundur.
- [ ] Security audit hesabatı hazırdır, kritik və yüksək riskli tapıntı qalmayıb.
- [ ] README yenilənib: quraşdırma, `.env.example`, deploy, admin istifadəsi, backup və bərpa.
- [ ] Lighthouse: performans ≥ 90, əlçatanlıq ≥ 95. Əsas axınlar üçün e2e testlər CI-da işləyir.
