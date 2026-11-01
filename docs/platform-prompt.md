# Prompt: Marketing School — tədris platforması (demo və kurs hissəsi)

> Bu mətni AI kod köməkçisinə (Claude Code və s.) olduğu kimi verin. Repo: `hesenildirimov/marketing-school`.
> Sənəd versiyası: 2. Bölmələr arası istinadlar nömrə ilə verilib (məs. "bölmə 3.4").

---

Sən Marketing School üçün **onlayn tədris platformasını** quracaqsan. Bu, istifadəçinin pulsuz demo aldıqdan sonra daxil olduğu hissədir: videodərslər, ev tapşırıqları, canlı görüşlər, 1:1 mentorluq və diplom. **Bu, demo və ödənişli tələbələr üçün eyni, tək platformadır.** Demo istifadəçi yalnız məhdud görünüş alır (seçdiyi kursun ilk 2 modulu, 24 saat). Kimsə ödəniş edəndə **eyni hesabla (eyni `user_id`), yeni qeydiyyat və ya ayrıca sayt olmadan, birbaşa həmin kursun tam girişinə keçir**: kilidli modullar, ev tapşırıqları, canlı görüşlər, 1:1 və diplom açılır, demo zamanı izlədiyi və apardığı hər şey (progress, qeydlər) qorunur. Landing sayt artıq hazırdır. Sən onun davamını yazırsan və bitirəndə tam **security audit** edirsən.

## 0. İşə başlamazdan əvvəl
1. `README.md`-ni və bütün `src/` qovluğunu oxu. Landing-in necə qurulduğunu, dizayn sistemini və məzmun fayllarını başa düş.
2. **Bölmə 4.1-dəki hostinq suallarını mənə ver və cavabları gözlə.** Cavablar gəlmədən konkret texnologiyanı təsdiqləmə və böyük kodlaşdırma işinə başlama.
3. Cavablar gəldikdən sonra mənə **arxitektura təklifi** təqdim et və təsdiqini gözlə:
   - stack və seçimin əsaslandırması (bölmə 4.2);
   - ERD diaqramı (bölmə 3);
   - auth və billing axınları, video yanaşması (adapter qatı);
   - bölmə 5-dəki **mərhələ planının** hostinq reallığına uyğunlaşdırılmış variantı;
   - hostinq məhdudiyyətlərinə görə dəyişdirilməli olan tələblərin siyahısı (məs. queue worker, antivirus).
4. Bölmə 1b-dəki qərarlar **qətidir**. Onları yenidən soruşma və dəyişdirmə. Yalnız bölmə 1c-dəki açıq qalan məsələlər üzrə qərar tələb et.
5. Hər mərhələni ayrıca branch və PR ilə təqdim et. `main`-ə birbaşa push etmə.
6. Heç vaxt sirr (API key, token, parol) commit etmə. Hamısı `.env`-də olsun və `.env.example` ilə sənədləşsin.
7. Yeni biznes qərarı uydurma. Sənədin boş qoyduğu biznes məsələsi ilə qarşılaşsan, **konfiqurasiya olunan qayda** kimi yaz (default dəyəri qeyd et) və bölmə 1c-yə əlavə etmək üçün mənə bildir.

## 1. Mövcud layihə (bilməli olduqların)
- **Landing**: Eleventy 3 statik sayt, Netlify-da, Decap CMS (`/admin`). Hədəf domen: `marketingschool.az`. Landing hazırda yalnız Azərbaycan dilindədir (bölmə 2.13 üç dilə çevirməni təyin edir).
- **Platforma**: landing-dən ayrı tətbiqdir və **sənin alacağın hostinqdə** qurulur (bölmə 4). Landing Netlify-da qalır.
- **Məzmun mənbəyi (hazırda)**:
  - `src/_data/kurslar/1-marketinq.json` və `2-reqemsal.json`. Burada kurs adı, slug, modullar (`modules[].title/text`), təlimçi və qiymət sahələri var.
  - `src/_data/site.json`: əlaqə, forma mətnləri.
  - `src/_data/faq.json`.
  - Platformaya keçəndən sonra məzmunun tək mənbəyi bölmə 3.3-də təyin olunur.
- **İki kurs**:
  - **Marketinq Kursu** (`/marketinq-kursu/`): 9 modul, təlimçi **Həsənalı İldırımov**.
  - **Rəqəmsal Marketinq Kursu** (`/digital-marketinq-kursu/`): 10 modul, təlimçi **Zamir Şabanov**.
- **Kurs formatı** (saytda yazılan vədlər, platforma bunlara tam uyğun olmalıdır):
  - standart tədris müddəti 3 ay, tam onlayn (bölmə 1b-də 3 ayın sərt son tarix olmadığı göstərilib);
  - qeydə alınmış videodərslər, istənilən vaxt, öz tempində baxılır (Udemy kimi), videolar **Azərbaycan dilindədir**;
  - hər modulda **real keyslərə əsaslanan ev tapşırığı**;
  - **canlı onlayn sual-cavab** görüşü (cədvəl bölmə 1b-də);
  - **hər tələbə ilə fərdi (1:1) mentorluq sessiyası**;
  - tələb ödənildikdə **diplom (sertifikat)**.
- **Pulsuz demo (landing tərəfi)**: landing-də "Pulsuz demo dərsə bax" CTA-sı var. Forma (`src/_includes/form.njk`) indi Netlify Forms-a ad, e-mail, kurs seçimi və **məcburi telefon** göndərir. Vəd: **platformaya 1 günlük pulsuz giriş**. Formun platformaya qoşulması bölmə 2.2 və 2.12-də təyin olunur.
- **Dizayn sistemi** (`src/assets/style.css`). Platforma landing ilə eyni görünməlidir:
  - əsas rəng sarı, amma **yumşaq**: açıq krem-sarı fonlar (`#FFF6D6`, `#FFFBE8`), dolğun sarı (`#FFC400`) yalnız hover və vurğuda;
  - mətn tünd (`#0E1324`);
  - göy, yaşıl, bənövşəyi kimi əlavə vurğu rəngi yoxdur;
  - tək şrift ailəsi (sistem şrifti), xarici şrift yoxdur;
  - yığcam düzülüş, çox boşluq yoxdur;
  - yüngül animasiyalar, `prefers-reduced-motion`-a hörmət edilir;
  - CSS dəyişənlərini (`--brand`, `--brand-soft`, `--ink`, `--line`, `--r` və s.) təkrar istifadə et.
- Videoların ümumi uzunluğu və saatı hələ məlum deyil. Rəqəm uydurma, yer tutucu qoy.

## 1b. Qəbul olunmuş biznes qərarları (qətidir, dəyişdirmə)

### Qiymət və billing
- Hər kursun qiyməti **ayrıca 99 AZN/aydır**. Bir kursun ödənişi digər kursa giriş vermir.
- Hissə-hissə ödəniş yoxdur. Hər əlavə ay ayrıca 99 AZN hesablanır.
- Kursun **standart** tədris müddəti 3 aydır, amma **3 aylıq sərt son tarix yoxdur**. 3 ay ərzində bitirə bilməyən tələbə əlavə aylar üçün də aylıq 99 AZN ödəməklə davam edə bilər.
- Hər kurs üçün ayrıca `enrollment`, ödəniş tarixçəsi və `paid_until` saxlanılır.
- **Ödəniş aktivliyi (billing)** ilə **akademik tamamlanma** ayrı anlayışlardır və ayrı modelləşdirilir. Ödəniş müddəti bitəndə tələbənin akademik məlumatları (progress, tapşırıqlar, ballar, qeydlər, diplom) silinmir.
- Billing çevik olmalıdır: minimum ödəniş öhdəliyi və "erkən bitirəndə abunəni dayandırma" qaydaları hələ təsdiqlənməyib (bölmə 1c). Onları **konfiqurasiya** kimi nəzərdə tut, koda sərt yazma.

### Güzəşt müddəti (grace period)
- `grace_period_days = 3` (admin dəyişə bilir, hər dəyişiklik audit log-a yazılır).
- `grace_until = paid_until + grace_period_days`.
- `paid_until` bitdikdə və ödəniş yenilənmədikdə status `past_due` olur. `grace_until` ərzində giriş saxlanır. `grace_until` bitəndə enrollment `expired` olur və giriş bağlanır.
- Bütün vaxt hesablamaları serverdə aparılır.

### Avtomatik ödənişin dayandırılması
- Tələbə avtomatik aylıq ödənişi dayandırdıqda gələcək təkrarlanan ödənişlər dayandırılır, amma **artıq ödənilmiş müddət ləğv edilmir**: tələbə `paid_until`-ə qədər tam, sonra 3 günlük güzəşt ərzində giriş saxlayır, sonra giriş bağlanır.
- Abunəliyin ləğvi dərhal enrollment-in `canceled` olması demək deyil. Ayrıca `cancel_at_period_end` mexanizmi var.
- Yenidən ödəniş olarsa tələbə qaldığı yerdən davam edir.

### Demo (bütün platformada yalnız bir dəfə)
- Bir istifadəçi **bütün Marketing School platformasında yalnız bir dəfə** demo ala bilər. Demo hər kurs üçün ayrıca verilmir.
- İstifadəçi bir kursun demosunu istifadə edibsə, digər kursun demosunu ala bilməz.
- Demo istifadəçisi **seçdiyi bir kursun ilk 2 moduluna 24 saatlıq** giriş əldə edir.
- Demo hüququnun bitməsi yeni demo hüququ yaratmır.
- Eyni e-mail və ya eyni telefonla yeni qeydiyyat açıb demo limitini keçmək bloklanır. E-mail və telefon müqayisədən əvvəl normallaşdırılır (bölmə 2.2).
- Birdəfəlik (disposable) e-mail ünvanları bloklanır.
- Telefon SMS OTP ilə təsdiqlənmədiyi üçün telefon əsasında identifikasiyanın məhdudiyyəti security audit hesabatında yazılır (bölmə 6).
- **Demo istifadəçi ev tapşırığını heç görmür** (tab da yoxdur, mətn də verilmir, API 403 qaytarır).
- E-mail təsdiqi məcburidir. **SMS OTP yoxdur.**

### Dərsin və kursun tamamlanması
- Dərs avtomatik tamamlanmış sayılmır. Tələbə özü **"Dərsi tamamladım"** düyməsinə basmalıdır.
- Modul yalnız bütün dərsləri tələbə tərəfindən tamamlandı işarələnəndə tamamlanmış sayılır.
- **1:1 mentorluq** yalnız tələbə **bütün modulların bütün dərslərini** tamamlayanda açılır.

### Diplom
- Şərt: `total_earned_points / total_possible_points * 100 >= 75`. Məxrəc bütün **məcburi** tapşırıqların maksimum ballarının cəmidir. Göndərilməyən tapşırıq 0 bal alır.
- Diplom bir dəfə qanuni qaydada qazanılandan sonra **daimidir**: sonrakı ödəniş statusundan, kursa giriş hüququndan və sonradan tapşırıq əlavə olunmasından asılı olmayaraq qüvvədə qalır, yenidən yüklənə bilir. Yalnız qanuni və ya inzibati düzəliş zərurəti olduqda, ayrıca audit izi ilə ləğv edilə bilər.

### Canlı görüşlər
- **Hər ayın ikinci şənbəsi və dördüncü şənbəsi, saat 11:00, `Asia/Baku`.** Həftə nömrəsi ilə yox, "ayın 2-ci və 4-cü şənbəsi" kimi hesablanır.
- Görüşlər hər ay avtomatik yaranır, admin hər görüşün tarixini və saatını dəyişə və ləğv edə bilir.

### Dillər
- Landing **və** platforma üç dildə işləyir: **Azərbaycan (AZ, əsas), İngilis (EN), Rus (RU)**. İngilis dilinə xüsusi diqqət (bölmə 2.13). Rus dili üçün də təbii, peşəkar tərcümə tələb olunur.
- Kurs videolarının Azərbaycan dilində olduğu bütün dillərdə aydın yazılır.

### Ödəniş provayderi və digər
- Epoint və ya Payriff (son seçim bölmə 2.10-un araşdırmasından sonra).
- Videolar **1080p**, ayrıca video xidmətindədir (şərti olaraq **Bunny Stream**, bölmə 4.3).
- Texnologiya seçimi sənindir, amma hostinq cavabları alınandan sonra (bölmə 4). Əlavə pullu xidmətə məcbur etmə (bölmə 4.4).

## 1c. Açıq qalan qərarlar (konfiqurasiya kimi nəzərdə tut, qərarı tələb et)
Bunlar **hələ təsdiqlənməyib**. Arxitekturanı bloklamır, ona görə konfiqurasiya olunan qayda kimi qur və default-u qeyd et. Mən qərar verənə qədər default **müvəqqətidir**.
1. **Minimum ödəniş öhdəliyi:** ilk 3 ay üçün minimum 297 AZN ödəniş öhdəliyi olacaqmı, yoxsa tələbə kursu tez bitirəndə növbəti ayın ödənişini dayandıra biləcək? *Default: öhdəlik yoxdur (`min_commitment_months = null`).*
2. **Kursun akademik bitməsi:** kurs bütün modullar tamamlananda, yoxsa diplom şərti də ödənəndə bitmiş sayılır? *Default: iki ayrı hadisə (`modules_completed_at`, `diploma_eligible_at`), "bitmiş" etiketi yalnız UI-da göstərilmir.*
3. **Avtomatik ödənişin dayandırılması:** kursun tamamlanması ilə əlaqələndirilsin, yoxsa tələbə özü dayandırsın? *Default: yalnız tələbə özü (və admin).*
4. **Maksimum balın dəyişdirilməsi:** təlimçi tapşırığın maksimum balını sonradan hansı şərtlərdə dəyişə bilər? *Default: tapşırığa ən azı bir bal verildikdən sonra dəyişmək admin təsdiqi və məcburi səbəb tələb edir, verilmiş diplomlara təsir etmir.*
5. **Hostinqdə antivirus və daimi worker mümkün deyilsə**, hansı alternativ qəbul olunur? *Default: istehsala çıxış bu tələbi ödəmədən qadağandır (bölmə 2.5 və 4.5).*
6. **Geri qaytarma (refund) və chargeback zamanı giriş:** dərhal bağlansın, yoxsa `paid_until`-ə qədər qalsın? *Default: refund olunan ödənişin əhatə etdiyi müddət ləğv edilir, qalan tələbə məlumatları saxlanılır.*
7. **Güzəşt müddətində edilən ödənişin hesablanması:** yeni `paid_until` köhnə `paid_until`-dən, yoxsa ödəniş anından hesablansın? *Default: `grace_until` daxilində ödənsə köhnə `paid_until`-dən bir ay əlavə olunur (tələbə günlərini itirmir), `expired` olandan sonra ödənsə ödəniş anından.*
8. **Hər iki kursun canlı görüşləri eyni gün və saatda (fərqli təlimçilər) keçirilirmi?** *Default: hər kurs üçün ayrıca seriya, eyni qayda ilə.*
9. **Spam qoruması üçün xarici CAPTCHA** (Turnstile/hCaptcha) icazəli olsun? *Default: yoxdur, yalnız honeypot, minimum doldurma vaxtı və rate limit.*

## 2. Qurulacaq funksionallıq

### 2.1 Rollar, giriş və enrollment statusları
- **Qlobal rollar** (hesab səviyyəsi): `student` (hər qeydiyyatdan keçmiş istifadəçi), `instructor` (yalnız öz kursu), `admin`. **"Demo" rol deyil**, platforma səviyyəli `demo_claim` hüququdur (bölmə 2.2). Bir istifadəçinin kurs üzrə hüquqları **kurs üzrə `enrollment` və `demo_claim`** ilə təyin olunur, qlobal rolla yox.
- Bir istifadəçi eyni vaxtda fərqli kurslarda fərqli statusda ola bilər (məs. Marketinq: `active`, Rəqəmsal: enrollment yoxdur). Bir kursdakı status digər kursa təsir etmir.
- Qeydiyyat və giriş: e-mail təsdiqi məcburidir. Telefon toplanır (landing-də olduğu kimi məcburi), **SMS OTP yoxdur**. Təsdiqdən sonra istifadəçi parol təyin edir (məcburi); sonrakı girişlər e-mail + parol ilədir.
- Admin və təlimçilər üçün **TOTP əsaslı 2FA məcburidir**, recovery code-lar verilir.
- **Enrollment giriş statusu** (billing-dən törənir, bölmə 1b): `active` (`now <= paid_until`), `past_due` (`paid_until < now <= grace_until`), `expired` (`now > grace_until`), `canceled` (admin tərəfindən və ya refund səbəbi ilə). **Yalnız `active` və `past_due` giriş verir.**
- **Subscription (billing) statusu enrollment giriş statusundan ayrıdır.** Abunəliyin ləğvi (`cancel_at_period_end = true`) dərhal giriş hüququnu dəyişmir (bölmə 2.10).
- Bütün icazə yoxlamaları **serverdə** aparılır. UI-da gizlətmək təhlükəsizlik sayılmır.
- Eyni hesabdan **eyni vaxtda sessiya limiti** (default 2 cihaz, konfiqurasiya olunan). Bu, hesab paylaşmanın qarşısını alır. Sessiyalar serverdə saxlanılır və ləğv edilə bilir.

### 2.2 Demo axını (platforma üzrə bir dəfə, 24 saat)

**Qaydalar (hansı problemi həll edir):**
- `demo_claims` platforma səviyyəli cədvəldir. Bir `user_id` üçün ən çox bir sətir. Eyni e-mail və ya telefon ilə demo təkrarlanmasının qarşısını alır.
- Sütunlar (təklif): `user_id` (unique), `course_id` (seçilmiş kurs), `email_normalized` (unique), `phone_e164` (unique), `started_at`, `expires_at`, `email_confirmed_at`, `converted_at`.
- **Normallaşdırma:**
  - e-mail: kiçik hərf, boşluqların silinməsi, `+tag` hissəsinin silinməsi, Gmail üçün nöqtələrin silinməsi. Orijinal e-mail da saxlanılır;
  - telefon: E.164 (`+994XXXXXXXXX`), yerli formatlar (`050...`, `0 50...`, `+994 50...`) eyni nəticəyə gəlməlidir.
- Birdəfəlik (disposable) e-mail domen siyahısı bloklanır (yenilənə bilən siyahı).
- Unikal məhdudiyyətlər verilənlər bazası səviyyəsindədir. Yarış vəziyyətində (iki paralel qeydiyyat) bir sorğu uğurlu, digəri anlaşılan xəta ilə rədd olunmalıdır. **Paralel qeydiyyat testi məcburidir.**

**Axın:**
1. İstifadəçi landing-də pulsuz demo formasını doldurur: ad, e-mail, telefon (məcburi), seçilmiş kurs. Forma platformanın `POST /api/demo/register` endpoint-inə gedir (və ya platformanın qeydiyyat səhifəsinə yönləndirir). Dil parametri də ötürülür.
2. Server demo limitini yoxlayır (normallaşdırılmış e-mail və telefon üzrə).
3. Uğurlu olarsa **bir tranzaksiyada**: istifadəçi hesabı, `demo_claim` və göndəriləcək e-mailin `outbox` qeydi yaradılır. `demo_started_at = now`, `demo_expires_at = demo_started_at + 24 hours`.
4. **24 saatlıq müddət qeydiyyat tamamlanan anda başlayır**, e-mailə klik edildiyi anda yox.
5. İstifadəçinin e-mailinə dərhal təsdiq/giriş linki göndərilir.
6. İstifadəçi linkə klikləyir, e-mailini təsdiqləyir, parol təyin edir və normal autentifikasiya sessiyası alır.
7. Linkin yenidən göndərilməsi, hesabdan çıxılması və ya linkin gec açılması 24 saatlıq müddəti **uzatmır** və **yeni demo yaratmır**.

**E-mail linki (hansı problemi həll edir):**
- kriptoqrafik təsadüfi token (ən azı 32 bayt), verilənlər bazasında **yalnız hash** saxlanılır (bazanın sızması tokeni vermir);
- birdəfəlik istifadə, server-side expiry (24 saat), təkrar istifadə bloklanır;
- yeni link istənəndə köhnələr etibarsızlaşır; resend üçün rate limit (məs. 3/saat/hesab);
- link daimi giriş imkanı vermir, yalnız e-mail təsdiqi və ilk parol təyini üçündür;
- demo bitdikdən sonra link açılsa e-mail təsdiqlənir, amma demo müddəti bitib, istifadəçi "Kursu al" ekranını görür.

**E-mail göndərilməməsi halı:** hesabın yaradılması ilə e-mailin göndərilməsi arasında təhlükəsiz retry mexanizmi qurulur: **outbox pattern** (e-mail qeydi tranzaksiya ilə yazılır, ayrıca worker və ya cron göndərir, eksponensial gecikmə ilə yenidən cəhd). Admin paneldən e-mailin yenidən göndərilməsi mümkün olmalıdır (audit log-a yazılır).

**Giriş məhdudiyyəti:**
- **Demoda yalnız seçilmiş kursun ilk 2 modulu açıqdır.** Digər modullar siyahıda görünür (ad, qısa təsvir, kilid ikonu, "Kursu alanda açılır"), amma:
  - kilidli dərslərin video tokeni, materialı və tapşırığı **serverdə heç verilmir**;
  - `lessonId`-ni dəyişməklə və ya birbaşa URL ilə açmaq mümkün deyil.
- `demo_expires_at`-dan sonra video və material tokeni verilmir, giriş bağlanır və "Kursu al" ekranı göstərilir. Davam edən videonun token yenilənməsi də rədd olunur (bölmə 2.3).
- Demo istifadəçi üçün **heç görünməyənlər**: ev tapşırığı (mətn də, tab da), canlı görüş linkləri, 1:1 bölməsi, diplom. Menyuda və UI-da yoxdur, API-lar 403 qaytarır.
- Vaxt yalnız server saatı ilə hesablanır, müştəri saatına etibar edilmir.
- Demo digər kursa **keçirilmir**. İstifadəçi digər kursu almaq istəsə ödəniş edir (bölmə 2.10).

**Demo → ödəniş keçidi:** ödəniş təsdiqlənəndə (webhook) eyni `user_id` üçün həmin kursun `enrollment`-i aktivləşir, `demo_claims.converted_at` yazılır. Demo zamanı yaranan `lesson_progress` və `personal_notes` saxlanılır. Giriş tam açılır (kilidli modullar, tapşırıqlar, canlı görüşlər). Demo hüququ başqa kurs üçün bərpa olunmur.

**Sui-istifadə qarşısı:** qeydiyyat endpoint-inə rate limit (IP və normallaşdırılmış e-mail üzrə), honeypot sahəsi, minimum doldurma vaxtı. Hesabat: telefon təsdiqlənmədiyi üçün başqa nömrə ilə demo təkrarlamaq mümkündür, bu qalıq risk security audit hesabatında yazılır.

### 2.3 Video izləmə (rahat və yüklənməz)
- Video **ayrıca video xidmətindədir** (bölmə 4.3). Yalnız şifrələnmiş və tokenlə qorunan HLS axını işlənir.
- **Yükləmənin qarşısını alma.** Tam qarşısını almaq texniki olaraq mümkün deyil (ekran yazısı). Ona görə qatlarla çətinləşdir və bunu mənə açıq yaz:
  - birbaşa MP4 linki yoxdur, yalnız **şifrələnmiş HLS**;
  - **qısa ömürlü imzalı tokenlər** (5–15 dəqiqə), istifadəçiyə və dərsə bağlı, **serverdə yalnız icazə yoxlandıqdan sonra** verilir. Token müddəti demo istifadəçisi üçün `demo_expires_at`-dan, ödənişli üçün `grace_until`-dən uzun ola bilməz;
  - uzun video zamanı player token yenilənməsi üçün serverə müraciət edir və server giriş hüququnu **yenidən** yoxlayır (demo bitəndə və ya enrollment `expired` olanda izləmə kəsilir);
  - domen və referer məhdudiyyəti (playback yalnız platforma domenində);
  - **dinamik watermark:** tələbənin e-maili və qısa istifadəçi kodu, yarı-şəffaf, mövqeyi dəyişən (telefon göstərilmir, şəxsi məlumatı azaltmaq üçün);
  - player-də download düyməsi yoxdur, sağ klik və `controlsList="nodownload"` (bunlar kosmetikdir, əsas qoruma deyil);
  - eyni vaxtda sessiya limiti (bölmə 2.1);
  - qeyri-adi izləmə həcminə görə xəbərdarlıq və log;
  - provayderin DRM imkanlarını (Widevine/FairPlay) **rəsmi sənəddən yoxla**, mənə açıq yaz və büdcə imkan verərsə qiymətləndir.
- **İzləmə rahatlığı:**
  - kaldığı yerdən davam;
  - sürət 0.75–2x, keyfiyyət seçimi, tam ekran;
  - klaviatura qısayolları (space, ←/→ 10 san, f);
  - fəsillər (chapters), istəyə görə subtitr;
  - "Növbəti dərs" düyməsi;
  - mobil uyğun, yavaş internetdə adaptiv bitrate.
- **Progress və dərsin tamamlanması (hansı problemi həll edir):**
  - video mövqeyi serverə müntəzəm yazılır (məs. hər 10–15 saniyədən bir, həmçinin pauza, tab bağlanması və `visibilitychange` zamanı `sendBeacon` ilə). Bu, internet itəndə də son mövqeyi saxlayır;
  - hər dərs üçün `position_seconds`, `max_watched_seconds` (yalnız artır), `watch_percent` ayrıca saxlanılır;
  - yazma əməliyyatı **idempotent upsert**-dir (`user_id + lesson_id` unique), paralel sorğularda ikiqat qeyd yaranmır;
  - **izləmə faizi dərsi özü tamamlamır.** Tamamlanma yalnız "Dərsi tamamladım" düyməsi ilə olur, `completed_at` serverdə yazılır, təkrar sorğu ikiqat progress yaratmır;
  - tamamlanmış dərs yenidən açılıb izlənə bilər;
  - tamamlama sorğusunda həmin kurs üzrə giriş hüququ serverdə yoxlanılır.

#### 2.3b 1080p optimallaşdırması (player + kodlaşdırma)
Məqsəd: video **tez başlasın, donmasın, yavaş internetdə də işləsin**, 1080p isə yalnız bant genişliyi imkan verəndə göstərilsin.
- **Kodlaşdırma (provayderin preseti və ya ffmpeg):** H.264 High, AAC 128 kbps, 2 saniyəlik keyframe, 4 saniyəlik seqment. Adaptiv keyfiyyət pillələri (başlanğıc təxmini):
  - 1080p ~4,5 Mbps;
  - 720p ~2,5 Mbps;
  - 480p ~1,2 Mbps;
  - 360p ~0,6 Mbps.
  Pillələri real kurs videosunda ölç və qərarı əsaslandır.
- **Başlanğıc:** player 720p ilə başlayır və bant genişliyi yaxşıdırsa 1080p-yə qalxır (ilk kadr gecikməsini azaldır). `Save-Data` başlığı və ya mobil şəbəkədə 1080p avtomatik qadağan edilir, istifadəçi əl ilə seçə bilər.
- **Player:** yüngül açıq mənbəli player (məsələn Vidstack və ya hls.js + sadə UI). Player kodu yalnız dərs səhifəsində yüklənir.
- **Sürət:**
  - `preconnect`/`dns-prefetch` video domeninə;
  - kiçik WebP poster, video isə klik və ya ekrana girəndə yüklənir (lazy);
  - növbəti dərsin ilk seqmentləri yalnız cari dərsin sonuna yaxın əvvəlcədən yüklənir;
  - buffer limiti (30–60 saniyə), mobil yaddaşı şişirtməsin;
  - kəsilmədən davam etmə, şəbəkə düşəndə mesaj və avtomatik yenidən cəhd.
- **Ölçmə:** player metrikləri (ilk kadrın gəlmə vaxtı, buffer sayı və müddəti, orta keyfiyyət) toplansın və admin paneldə görünsün. Şəxsi məlumat yazılmasın.
- **Hədəflər (real test nəticəsi olmadan təmin edilmiş hesab etmə):** ilk kadr < 2,5 san, Lighthouse performans ≥ 90, Cumulative Layout Shift ≈ 0. Bunlar yalnız bölmə 4.6-dakı test mühitində ölçüləndən sonra "ödənilib" hesab edilir.

### 2.4 Dərs səhifəsinin düzülüşü
- Masaüstü:
  - **solda** modul və dərs siyahısı: progress, kilidlər, tamamlanma işarələri;
  - **ortada** video;
  - **videonun altında, ortada** tablar: **Ev tapşırığı** (demo istifadəçidə yoxdur), Materiallar, Qeydlər (şəxsi), Suallar (növbəti canlı görüşə sual).
- Mobil: video yuxarıda, tablar altında, modul siyahısı açılan panel kimi.
- Yuxarıda kurs üzrə ümumi progress (%) = tamamlanmış dərslərin bütün dərslərə faizi.

### 2.5 Ev tapşırıqları
- Hər modulda real keysə əsaslanan tapşırıq. Təlimçi admin paneldən yazır: mətn, keys faylı, son tarix (istəyə görə), **maksimum bal** və **məcburi/könüllü** işarəsi (diplom məxrəcinə yalnız məcburilər daxildir).
- Tələbə cavab göndərir: mətn redaktoru və fayl.
- **Fayl təhlükəsizliyi (hər tədbirin qarşısını aldığı risk ilə):**
  - tip yoxlaması MIME və məzmunla (magic bytes), yalnız uzantı ilə yox: PDF, DOCX, XLSX, PPTX, PNG, JPG. *Zərərli faylın başqa tip kimi gizlədilməsinin qarşısını alır.*
  - fayl ölçü limiti, istifadəçi və kurs üzrə ümumi storage limiti. *Diski doldurma hücumunun qarşısını alır.*
  - ZIP bomb və zərərli makro riskləri nəzərə alınır: DOCX/XLSX/PPTX arxivləri açılış ölçüsü və fayl sayı limiti ilə yoxlanılır, makrolu formatlar (`.docm`, `.xlsm`, `.pptm`) qəbul edilmir.
  - fayl adları randomlaşdırılır, orijinal ad yalnız metadata kimi saxlanılır. *Path traversal və adla bağlı hücumların qarşısını alır.*
  - fayllar veb kökündən və icra oluna bilən qovluqdan **kənarda**, private storage-da saxlanılır və yalnız sahibi ilə təlimçiyə qısa ömürlü imzalı link ilə açılır.
  - **antivirus:** ClamAV ilə lokal skan (hostinq imkan verərsə). Skan bitməyən fayl `pending` statusunda qalır və **endirilə bilməz**. Zərərli fayl **karantinə** alınır, sahibinə bildiriş gedir, təlimçiyə açılmır.
  - hostinq ClamAV-yə imkan vermirsə bunu **səssizcə keçmə**: mənə açıq yaz və istehsala çıxış üçün minimum tələbi bölmə 4.5-ə uyğun müəyyən et.
- Statuslar: `göndərilməyib → göndərildi → yoxlanılır → qiymətləndirildi` (və ya `düzəliş lazımdır`).
- Təlimçi bal və rəy yazır. Tələbənin topladığı bal və faiz (diplom üçün 75% hədd) həmişə dərs səhifəsində və profildə görünür.
- Tələbə qiymətləndirmə və rəy barədə bildiriş alır.
- Tapşırıqlar canlı görüşlərdə müzakirə olunur. Təlimçi görüş üçün seçilmiş tapşırıqları işarələyə bilər.
- **Tapşırığın və balın dəyişməsi (bölmə 3.4):** verilmiş qiymət xəbərsiz dəyişməməlidir. Hər qiymətlə birlikdə həmin andakı `max_points` saxlanılır.

### 2.6 Canlı sual-cavab (ayın 2-ci və 4-cü şənbəsi)
- **Cədvəl:** hər ayın 2-ci və 4-cü şənbəsi, 11:00, `Asia/Baku`. Sistem **gələcək 3 ayın** görüşlərini idempotent şəkildə yaradır (eyni tarix üçün təkrar yazı yaranmır, `UNIQUE(course_id, starts_at)`), hər ay cron/worker davam etdirir.
- **Tarix və vaxt:** `starts_at` UTC saxlanılır, amma 2-ci/4-cü şənbənin müəyyənləşdirilməsi və vaxt hesablamaları `Asia/Baku` əsasında aparılır. `.ics` fayllar və e-mail xatırlatmaları lokal vaxtla düzgün göstərilir.
- Admin hər görüşün tarixini, saatını dəyişə və ləğv edə bilər. **Dəyişiklikdə** tələbələrə bildiriş gedir və `.ics` yenilənir (`SEQUENCE` artır). **Ləğvdə** `METHOD:CANCEL` göndərilir və həmin görüşün gözləyən xatırlatmaları dayandırılır.
- Təlimçi hər görüşə Zoom/Meet linki əlavə edir.
- Yalnız **ödənişli tələbələr** (`active` və ya `past_due` enrollment):
  - görüş linkini görür (link serverdə qorunur);
  - əvvəlcədən sual göndərə bilir;
  - görüşün yazısını sonradan izləyə bilir. Yazılar yalnız uyğun giriş hüququ olan tələbələrə göstərilir və 2.3-dəki kimi qorunur.
- Xatırlatma: görüşdən 24 saat və 1 saat əvvəl (bölmə 2.8).

### 2.7 1:1 mentorluq — təqvimlə
- **Kursu almayanlar bu bölməni ümumiyyətlə görməsin**: menyuda yoxdur, API 403 qaytarır. Demo istifadəçilər də görmür.
- Təlimçi admin paneldə boş vaxtlarını (slotları) təqvimdə təyin edir.
- Bölmə yalnız tələbə **bütün modulların bütün dərslərini** tamamlayanda açılır. **Açılma hadisəsi qeydə alınır** (`mentoring_unlocked_at`). Admin kursa sonradan yeni dərs əlavə etsə, artıq açılmış 1:1 hüququ **avtomatik itmir** (bölmə 3.4).
- Tələbə **bir sessiya** rezerv edir. Eyni hüququn təkrar rezervlə çoxaldılması mümkün deyil.
- Rezerv: təsdiq e-maili, `.ics`, görüş linki, xatırlatmalar, ləğv və vaxt dəyişmə qaydası (məs. 24 saat əvvələdək, **serverdə tətbiq olunur**).
- Eyni slotu iki nəfər tuta bilməz: verilənlər bazasında unikal məhdudiyyət, **atomik tranzaksiya** (və ya `SELECT ... FOR UPDATE`). Ləğv olunan rezervasiyanın slotu yenidən boş olur. **Race condition testi məcburidir.**
- Öz slot cədvəlini yaz, xarici təqvim xidmətinə (Cal.com, Calendly və s.) bağlanma.

### 2.8 Bildirişlər (vahid modul)
- **Vahid notification modulu və background queue** (e-mail və platforma bildirişləri ayrı kanallardır). Tətbiq kodu hadisəni modula verir, modul şablonu seçir, dil və kanalı təyin edir.
- Hadisələr:
  - demo qeydiyyatı və giriş linki;
  - demo bitməsinə 6 saat qalması;
  - demo müddətinin bitməsi;
  - uğurlu və uğursuz ödəniş;
  - növbəti ödənişə 5 gün qalması;
  - grace period başlanması və bitməsi;
  - tapşırığın qiymətləndirilməsi;
  - canlı görüşə 24 saat və 1 saat qalması;
  - görüşün dəyişdirilməsi və ləğvi;
  - mentorluq rezervasiyası və dəyişiklikləri;
  - diplomun hazırlanması.
- **Təkrarlanma qarşısı:** hər bildirişin `dedupe_key`-i var (`event_type + entity_id + user_id + scheduled_for`), `UNIQUE`. Eyni hadisə üçün e-mail iki dəfə getmir.
- **Retry:** SMTP uğursuzluğunda eksponensial gecikmə ilə yenidən cəhd, sonda `failed` statusu və admin görünüşü.
- **Planlaşdırılmış xatırlatmalar:** cron/worker tərəfindən yaradılır. Ləğv olunan və ya dəyişən obyektin gözləyən xatırlatmaları ləğv olunur və ya yenidən planlanır.
- Şablonlar **üç dildə** (AZ/EN/RU), istifadəçinin hesabdakı dilinə görə.
- Hostinqdə queue worker və cron mümkünlüyü bölmə 4.1-də yoxlanılır. Daimi worker yoxdursa cron-un hər dəqiqə işlədiyi `outbox` prosessoru ilə əvəz et və məhdudiyyəti yaz.

### 2.9 Diplom
- **Hesablama:** `total_earned_points / total_possible_points * 100 >= 75`.
  - məxrəc yalnız həmin tələbənin **kurs versiyasındakı** (bölmə 3.4) məcburi tapşırıqların maksimum ballarının cəmidir;
  - göndərilməyən tapşırıq 0 bal alır;
  - maksimum bal cəmi 0-dırsa diplom **yaradılmır**;
  - 74,9% diplom vermir, 75% verir (dəqiq müqayisə, ondalıq xətaya qarşı tam ədəd/`DECIMAL` ilə hesabla);
  - təlimçi qiyməti dəyişəndə diplom hüququ yenidən hesablanır;
  - diplom yaradılması **idempotent**dir (eyni tələbə + kurs üçün ikinci diplom yaranmır, `UNIQUE`).
- **Snapshot:** diplom yaranan anda tələbənin adı, kurs adı, təlimçi, nəticə (bal və faiz) və tarix **dəyişməz snapshot** kimi saxlanılır. Sonradan ad dəyişməsi, tapşırıq əlavə olunması və ya maksimum balın dəyişməsi verilmiş diplomu avtomatik dəyişmir və ləğv etmir.
- **Daimilik:** diplom ödəniş statusundan asılı deyil. Enrollment `expired` olsa belə tələbə diplomunu yenidən yükləyə bilir (kursun qalan məzmununa giriş olmasa da).
- Ləğv yalnız admin tərəfindən, məcburi səbəb və audit izi ilə.
- Avtomatik PDF diplom: ad, kurs, tarix, təlimçi, nəticə və **unikal yoxlama ID-si** (təxmin olunmayan).
- Açıq yoxlama səhifəsi `/diplom/{id}`: yalnız ad, kurs və tarix göstərir, başqa şəxsi məlumat yoxdur. LinkedIn-ə əlavə linki. Enumerasiyaya qarşı rate limit.
- Təlimçi və admin qiymət dəyişikliyinin audit izini görür.

### 2.10 Ödəniş (aylıq 99 AZN, hər kurs ayrıca)
- Provayder: **Epoint** və ya **Payriff**. **İlk addım:** hər ikisinin rəsmi sənədlərini oxu və mənə cədvəllə təqdim et:
  - təkrarlanan (avtomatik aylıq) ödəniş və ya kart tokenizasiyası dəstəyi;
  - webhook və imza yoxlaması;
  - komissiya;
  - geri qaytarma (refund) və chargeback;
  - sınaq (sandbox) mühiti.
  Avtomatik aylıq çıxarış dəstəklənmirsə ehtiyat axın qur: hər ay `paid_until`-dən 5 gün əvvəl e-mail ilə ödəniş linki göndərilir.
- Provayder **adapter** arxasındadır (`PaymentProvider` interfeysi), provayder dəyişsə biznes məntiqi dəyişmir.
- **Qiymət serverdə:** məbləğ verilənlər bazasındakı qiymət cədvəlindən (`prices`: kurs, məbləğ qəpiklə tam ədəd, valyuta, `valid_from`) götürülür, müştəridən qəbul edilmir. Qiymət dəyişsə köhnə sifarişlər dəyişmir.
- **Kart məlumatı platformanın öz verilənlər bazasında saxlanmır.** Yalnız provayderin verdiyi token/ID və son 4 rəqəm (provayder icazə verirsə).
- **Nəticəyə etibar qaydası:** provayderin redirect səhifəsindən gələn nəticəyə təkbaşına etibar edilmir. Server **imzalı webhook**-u yoxlayır və mümkün olduqda **server-to-server status sorğusu** ilə təsdiqləyir. Enrollment yalnız təsdiqlənmiş ödənişdən sonra açılır.
- **Ödəniş tətbiqi tranzaksiyadadır:** `payment_transaction` qeydi, `payment_webhook_event` qeydi (provayder event ID-si üzrə `UNIQUE`), `subscription` və `enrollment` yenilənməsi bir tranzaksiyada. Təkrar gələn webhook ikinci dəfə tətbiq olunmur.
- **Lifecycle ssenariləri (hər biri üçün test yaz):**
  - checkout bağlanıb, amma ödəniş uğurlu olub (webhook və ya status sorğusu ilə tutulur);
  - webhook gecikir;
  - webhook təkrar gəlir;
  - webhook ardıcıllıqdan kənar gəlir (məs. "uğurlu" "gözləmə"dən əvvəl) — köhnə statusa geri düşməyə icazə verilmir;
  - ödəniş uğurlu olub, webhook emalı uğursuz olub (retry və admin görünüşü, `payment_webhook_events.status`);
  - refund (qaydası bölmə 1c-də);
  - chargeback;
  - avtomatik renewal uğursuzluğu → `past_due`;
  - abunəliyin dayandırılması → `paid_until`-ə qədər giriş;
  - güzəşt müddəti;
  - sonradan yenidən ödəniş və girişin bərpası (tələbə qaldığı yerdən davam edir).
- **Giriş statusu və abunə ayrıdır** (bölmə 2.1):
  - ödəniş uğurlu olanda `paid_until` bir ay uzanır (hesablama qaydası bölmə 1c, 7-ci maddə);
  - ödəniş alınmayanda `past_due`, `grace_until`-dən sonra `expired`;
  - `cancel_at_period_end` abunənin yenilənməsini dayandırır, girişi yox.
- **Çevik billing:** minimum ödəniş öhdəliyi və "kursu bitirəndə dayandırma" kimi qaydalar `billing_rules` konfiqurasiyasında (`min_commitment_months`, `auto_stop_on_completion`) saxlanılır və hələlik qeyri-aktivdir (bölmə 1c).
- **Mərhələ 1:** admin enrollment-i əl ilə aktivləşdirir (məcburi səbəb və audit log ilə, `paid_until` təyin edir). **Mərhələ 2:** onlayn ödəniş.

### 2.11 Admin / təlimçi paneli
- Kurs, modul və dərs idarəsi: video yükləmə, sıralama, demo üçün açıq modulların seçimi (default: ilk 2 modul).
- Kursun strukturunda dəyişiklik edəndə təsir görünüşü (bölmə 3.4).
- Tələbələr: axtarış, enrollment aktivləşdirmə (əvvəl əl ilə, sonra ödənişlə), progress, `grace_period_days` dəyişmək (audit log ilə), abunə statusu.
- Demo istifadəçiləri: demo qeydləri, e-mailin **yenidən göndərilməsi** (audit log), demo → ödəniş konversiyası.
- Tapşırıqların yoxlanması növbəsi, qiymətləndirmə, canlı görüşlərin (dəyişmə və ləğv) və 1:1 slotlarının idarəsi.
- Bildirişlər: göndərilmə statusu, `failed` olanlar, yenidən göndərmə.
- Audit log: kim, nə vaxt, nəyi dəyişdi (admin əməliyyatları, qiymət və bal dəyişiklikləri, billing dəyişiklikləri, diplom ləğvi).

#### 2.11b Admin analitikası
Minimal məlumat toplayan, şəxsi məlumatı lazımsız göstərməyən (yığılmış rəqəmlər) göstəricilər:
- hər kurs üzrə demo qeydiyyatları;
- demo → ödəniş konversiyası;
- demo istifadəçilərinin kursu hansı mərhələdə (dərsdə) tərk etməsi;
- aktiv, `past_due` və `expired` tələbələr;
- təkrar aylıq ödəniş faizi;
- kurslar üzrə orta progress;
- dərs üzrə video abandonment (hansı dəqiqədə tərk edilir);
- tapşırıqların göndərilmə faizi;
- tapşırıqların qiymətləndirilmə müddəti;
- diplom alan tələbələr;
- gəlir və uğursuz ödənişlər.

### 2.12 Landing ilə əlaqə və landing-də dəyişikliklər
- Landing statik qalır (Netlify). Platforma ayrı tətbiqdir, məsələn `app.marketingschool.az` (və ya `/platform`).
- **Demo formunun inteqrasiyası:** landing forması platformanın `POST /api/demo/register` endpoint-inə göndərir (cross-origin üçün CORS yalnız landing domeni ilə məhdudlaşdırılır). Netlify Forms bu axından çıxarılır (iki mənbədə təkrarlanan qeyd yaranmasın). Uğurdan sonra istifadəçi platformanın "E-mailinizi yoxlayın" səhifəsinə, seçdiyi dildə yönləndirilir.
- **Kurs strukturunun tək mənbəyi bölmə 3.3-də təyin olunur.** Landing və platforma arasında ad, slug, modul və qiymət uyğunsuzluğu yaranmamalıdır.
- Platforma `noindex` olsun və `robots`-da bağlansın. SEO, schema və `llms.txt` yalnız landing-dədir.
- **Qiymət landing-də göstərilsin:** hər kursun yanında "**99 AZN / ay**" və qeyd: "hər kurs ayrıca 99 AZN-dir". Yerlər: ana səhifədə kurs kartları, kurs səhifələrində faktlar cədvəli və hero, FAQ-da qiymət sualı (hazırda "formdan sonra göndərilir" yazılıb, düzəlt). `src/_data/kurslar/*.json`-da `price: "99"`, `currency: "AZN"`. Schema `Course.offers` (price, priceCurrency, availability) və `llms.txt` avtomatik yenilənsin. Qiymət səhifələrdə "təxminən", "-dən başlayaraq" kimi yazılmasın, çünki sabitdir.
- Landing-dəki "Pulsuz demo" CTA-sı, forma və təşəkkür səhifəsi yeni axına uyğunlaşdırılsın.

### 2.13 Üç dilli landing və platforma (SEO və xüsusilə GEO üçün İngilis dili kritikdir)
Dillər: **AZ (əsas), EN, RU.** Landing hazırda yalnız AZ-dir. Menyuda dil seçimi (AZ | EN | RU) olsun.
- **Landing URL-ləri:** `/` (AZ), `/en/`, `/ru/`.
- **İngilis dilinə xüsusi diqqət:** ChatGPT, Gemini, Perplexity və Google AI Overviews kimi sistemlər cavabları əsasən ingilis dilli mənbələrdən yığır. İngilis versiya sadə tərcümə deyil, **təbii, peşəkar marketinq İngilis dilində** yazılmalıdır. Mətnləri maşın tərcüməsi kimi buraxma, hər səhifə üçün keyfiyyəti özün yoxla və mənim də baxışıma təqdim et. İngilis versiyada Marketing School-un nə olduğu, harada (Azerbaijan, online), kim tərəfindən, qiymət (99 AZN per month per course), format və təlimçilər birbaşa və qısa cavab kimi yazılsın. **Rus dili üçün də təbii, peşəkar tərcümə** tələb olunur.
- **Kursun Azərbaycan dilində olması:** EN və RU səhifələrdə (kurs səhifələri, FAQ, hero, platformada demo ekranı) "kurs Azərbaycan dilindədir" aydın göstərilsin ki, alıcı aldanmasın.
- **Texniki SEO:**
  - hər səhifədə qarşılıqlı **`hreflang`** (`az`, `en`, `ru`, `x-default`), hər dil öz `canonical`-ı ilə;
  - sitemap-da dil alternativləri (`xhtml:link`), hər dil üçün düzgün `<html lang>`;
  - dil seçimi **brauzer dilinə görə avtomatik yönləndirmə etməsin** (axtarış botlarını itirir), yalnız təklif göstərsin və seçimi yadda saxlasın.
- **Schema və AI faylları:** `Course`, `FAQPage`, `Person`, `EducationalOrganization` hər dildə tərcümə olunmuş `name`, `description` və `inLanguage` ilə. `llms.txt` və `llms-full.txt` ingilis dilində də (və rus dilində) yaradılsın. `sameAs`, `address`, `areaServed` kimi sahələr hər dildə eyni qalır. Kursun tədris dili (`inLanguage: az`) hər dildəki schema-da düzgün göstərilir.
- **FAQ və bloq:** FAQ hər dildə tam olsun, İngilis FAQ-ı ingilis dilli istifadəçilərin real suallarına uyğun yazılsın (məs. "Are there online marketing courses in Azerbaijan?", "How much does the course cost?"). Bloq yazıları dil üzrə ayrıca saxlanılır; **tərcüməsi olmayan yazı həmin dilin siyahısında görünmür.**
- **CMS:** Decap CMS-də dil üzrə məzmun idarəsi (hər dil üçün ayrıca sahələr və ya Decap-ın i18n imkanı), redaktorun hansı dildə nəyin çatışmadığını görməsi üçün sadə göstərici.
- **Miqrasiya:** mövcud AZ məzmunu pozulmasın. `src/_data/*.json` strukturunu dillərə uyğunlaşdıran təkrar işə salına bilən (idempotent) miqrasiya skripti və AZ üçün mövcud URL-lərin dəyişməməsini təmin edən test yaz.
- **Platforma:** UI 3 dildədir, dil seçimi **hesabda saxlanılır**, **e-mail şablonları və bildirişlər də 3 dildədir**. Landing formasından gələn dil parametri yeni hesabın ilkin dili olur.
- **Avtomatik yoxlamalar (hər üç dil üçün):** başlıq və təsvir uzunluqları, sınıq link, `hreflang` qarşılıqlı uyğunluğu, sitemap alternativləri, boş tərcümə sahələri.

### 2.14 Hüquqi və məxfilik
- Məxfilik siyasəti və istifadə şərtləri səhifələri (yer tutucu mətnlə; hüquqi mətni sahibi təsdiqləyəcək), üç dildə.
- Azərbaycanın "Fərdi məlumatlar haqqında" Qanununa uyğun minimal məlumat toplama, açıq razılıq, hesabın silinməsi və məlumatların ixracı imkanı. Hesab silinəndə verilmiş diplomun açıq yoxlama qeydi qalır (yalnız snapshot, əlaqəli şəxsi əlaqə məlumatları silinir); bu qaydanı mənə təsdiqlət.

## 3. Məlumat modeli və invariantlar

### 3.1 Əsas obyektlər
Bunlar məcburi SQL sxemi deyil, **təklif olunan obyektlərdir**. Arxitektura təklifində əlaqələr, unikal məhdudiyyətlər, indekslər və xarici açarlar **ERD diaqramında** göstərilməlidir.
- **Hesab:** `users`, `user_roles`, `user_sessions`, `two_factor_secrets`.
- **Məzmun:** `courses`, `course_versions`, `modules`, `lessons`, `lesson_revisions`, `recordings`.
- **Giriş və billing:** `demo_claims`, `enrollments`, `subscriptions`, `prices`, `billing_rules`, `payment_orders`, `payment_transactions`, `payment_webhook_events`.
- **Tədris prosesi:** `lesson_progress`, `personal_notes`, `assignments`, `assignment_revisions`, `submissions`, `grades`, `uploaded_files`.
- **Canlı və mentorluq:** `live_sessions`, `live_questions`, `mentor_slots`, `mentor_bookings`.
- **Nəticə:** `certificates`.
- **Sistem:** `notifications`, `outbox`, `audit_logs`.

### 3.2 Biznes invariantları (bütün qatlarda qorunur)
Aşağıdakılar yalnız tətbiq kodunda deyil, mümkün olan yerlərdə **verilənlər bazası məhdudiyyəti** ilə də təmin edilir:
- Bir istifadəçinin bir kurs üzrə ən çox bir aktiv enrollment-i var. *(UNIQUE `user_id + course_id` aktiv enrollment üzrə)*
- Bir kursun ödənişi digər kursa giriş vermir.
- Demo bütün platformada bir dəfə verilir. *(UNIQUE `user_id`, `email_normalized`, `phone_e164`)*
- Ödəniş təsdiqlənmədən enrollment aktivləşmir.
- Demo ödənişli girişə çevriləndə mövcud `user_id` saxlanılır, progress və qeydlər qorunur.
- Webhook təkrar gəldikdə ödəniş ikinci dəfə tətbiq edilmir. Billing hadisələri idempotentdir.
- Ödəniş, ödəniş qeydi və enrollment yenilənməsi tək tranzaksiyadadır.
- Giriş hüququ yalnız serverdə yoxlanılır.
- Dərsin tamamlanması yalnız istifadəçinin öz əməliyyatı ilə olur və yalnız o kursa giriş hüququ olanda.
- Diplom hüququ ödəniş statusundan ayrıdır.
- Eyni mentor slotuna iki rezervasiya mümkün deyil.
- Eyni tələbə və kurs üçün ikinci diplom yaranmır.
- Verilmiş qiymətdəki `max_points` sonradan dəyişmir.

### 3.3 Kurs və məzmunun tək mənbəyi
- **Platformanın operativ kurs strukturu, dərslər, tapşırıqlar və progress üçün əsas mənbə verilənlər bazasıdır.**
- Hazırkı `src/_data/kurslar/*.json` faylları ilkin məlumatın **importu** və landing üçün **statik export** kimi istifadə olunur.
- Landing və platforma arasında ad, slug, modul sayı və qiymət uyğunsuzluğu yaranmaması üçün sinxronizasiya mexanizmi yazılır: platformadan landing üçün JSON export (CI-da və ya əl ilə işə düşən skript). **Eyni sahə iki yerdə müstəqil redaktə olunmur**, hər sahənin bir sahibi var (məs. kurs adı, slug, qiymət və modul adları platformada; landing mətnləri, SEO və FAQ Decap CMS-də).
- Import/export **təkrar icra olunduqda** təkrarlanan kurs və modullar yaratmır (açar: `slug` və xarici ID).
- Arxitektura təklifində sahə sahibliyi cədvəli və sinxronizasiya axını göstərilsin.

### 3.4 Versiyalaşdırma
Kursun strukturu dəyişəndə əvvəlki tələbələrin progress-i və diplom hüququ təsadüfən pozulmamalıdır.
- Versiyalaşdırılan obyektlər: **kurs, modul, dərs, tapşırıq, maksimum bal**.
- **Enrollment kurs versiyasına bağlanır** (`enrollments.course_version_id`). Yeni dərs və ya məcburi tapşırıq əlavə edəndə admin **əvvəlcədən təsir görünüşünü** görür: neçə tələbə təsirlənir, kimin 1:1 və diplom hüququ dəyişə bilər. Admin seçir: yalnız yeni enrollment-lərə tətbiq et, yoxsa mövcud tələbələrə də tətbiq et (məcburi səbəb ilə).
- Dərsin məzmunu dəyişəndə yeni `lesson_revision` yaranır, köhnə revizyalar saxlanılır.
- **Artıq açılmış 1:1 hüququ və verilmiş diplom** sonradan yeni dərs və ya tapşırıq əlavə olunması ilə **itmir və ləğv olunmur.**
- `grades` sətri qiymət verilən anda `max_points` snapshot-ını saxlayır, arxada dəyişən maksimum bal əvvəlki qiyməti xəbərsiz dəyişmir.
- Maksimum balın dəyişdirilməsi qaydası bölmə 1c, 4-cü maddədədir.

## 4. Texnologiya və hostinq

### 4.1 Hostinq sualları (əvvəl soruş, cavabı gözlə)
Sistem **hostinq alınıb orada** qurulacaq. Bu, seçimi birbaşa təyin edir, ona görə işə başlamazdan əvvəl **mənə bu sualları ver və cavabı gözlə**:
1. Hostinqin növü: paylaşılan (cPanel), VPS, yoxsa ayrıca server? Provayder və plan.
2. Əməliyyat sistemi, Node.js / PHP / Python versiyaları, **SSH girişi**.
3. **vCPU, RAM, disk növü (SSD/HDD) və həcmi**, aylıq bandwidth limiti, paralel HTTP bağlantı limiti.
4. **Daimi (uzun işləyən) proses** (məs. Node.js) və **queue worker** mümkündürmü? **Cron** mümkündürmü və minimum intervalı nədir?
5. **Docker**, **ffmpeg** və **ClamAV** mövcudluğu və ya quraşdırıla bilməsi.
6. **SMTP göndərmə limiti** (saatlıq/günlük) və e-mail reputasiyası (SPF/DKIM/DMARC qurula bilirmi?).
7. Verilənlər bazası: MySQL, PostgreSQL və ya SQLite? **Avtomatik backup** və **restore** imkanı.
8. Domen və SSL: `app.marketingschool.az` və ya `/platform`. **Staging subdomain** (məs. `staging.marketingschool.az`) yaratmaq mümkündürmü?
9. Eyni anda **gözlənilən video izləyici sayı** (təxmini). Bu, yalnız tətbiqin (videonun yox) yüklənməsini qiymətləndirmək üçündür.

**Paylaşılan hostinqdə bütün funksiyaların problemsiz işləyəcəyini əvvəlcədən qəbul etmə.** Xüsusilə queue/worker, cron intervalı, virus skanı, uzun işləyən proses və SMTP limiti üçün məhdudiyyətləri mənə açıq yaz.

### 4.2 Seçim qaydası (sən qərar ver, əsaslandır)
- **Paylaşılan hostinq (cPanel, yalnız PHP + MySQL):** Laravel. Node/Next.js bura düşmür.
- **VPS və ya ayrıca server (SSH var):** Next.js (və ya Laravel) + PostgreSQL, Nginx, **Docker Compose** ilə.
- **İcazə qatı mərkəzləşdirilmiş olmalıdır:** nə qədər çərçivə seçilsə də, icazə yoxlamaları tək **policy/gate** qatında yazılsın və testlə yoxlansın. Demo məhdudiyyətinin ("yalnız 2 modul") pozulmaması vacibdir.
- **Verilənlər bazası və icazə uyğunluğu:**
  - **PostgreSQL** seçilərsə Row-Level Security (RLS) istifadəçi və kurs üzrə həssas cədvəllərdə (`submissions`, `grades`, `lesson_progress`, `personal_notes`, `certificates`, `enrollments`) qiymətləndirilsin və tətbiq olunsun. RLS tətbiq səviyyəsindəki authorization yoxlamalarını **əvəz etmir**, əlavə qoruma qatıdır. Tətbiqin DB istifadəçisi RLS-i **bypass edə bilməyən** səlahiyyətlə işləməlidir (superuser/owner olmamalıdır).
  - **MySQL** seçilərsə RLS mövcudmuş kimi yazma. Bunun əvəzinə **server-side authorization**, parametrləşdirilmiş sorğular və bütün həssas endpoint-lər üçün **ownership testləri** tələb olunur.
  - Security audit hesabatı bu fərqi açıq sənədləşdirməlidir (bölmə 6).
- Çox mürəkkəb, qeyri-adi və az dəstəklənən texnologiyadan qaç. Layihə tamamilə AI köməyi ilə aparılacaq, buna görə geniş yayılmış, sənədləşməsi yaxşı və təhlükəsiz standartları olan stack seç.

### 4.3 Video (qəbul olunmuş qərar)
Videolar **ayrıca video xidmətindədir, şərti olaraq Bunny Stream** (tokenli HLS, referer məhdudiyyəti, DRM imkanlarını rəsmi sənəddən yoxla). Tətbiq hostinqdə qalır. **Videoları öz hostinqində yayımlama** (bant genişliyi və CPU-nu tükədir, yükləmədən qorumaq çətindir).
- Video qatı **adapter** arxasındadır (kiçik interfeys: yüklə, imzalı playback URL ver, token yenilə, sil). Provayderə bağlı kod yalnız bu adapterdə olsun ki, sonradan dəyişmək asan olsun.
- Provayderin `Authentication Key`, `Library ID` kimi dəyərləri yalnız server `.env`-ində saxlanır.

### 4.4 Texnologiyanın yazılması: qaydalar
- Bütün sistemi **özün yaz**: giriş (auth), icazələr, fayl yükləmə, e-mail göndərmə, admin panel, təqvim və rezervasiya, diplom PDF-i, ödəniş inteqrasiyası. **Əlavə pullu xidmətə, SaaS-a və ya xarici platformaya (Supabase, Firebase, Auth0, Cal.com, Calendly, Zapier, hazır LMS və s.) məcbur etmə.**
- İcazə verilən yeganə xarici xidmətlər: **Bunny Stream** (video), **Epoint/Payriff** (ödəniş), **SMTP** (e-mail, hostinqin öz SMTP-si və ya adi SMTP) və canlı görüş üçün **Zoom/Google Meet linki** (əl ilə və ya sadə link). Başqa xidmət lazım görürsənsə, əvvəlcə mənə səbəbi və pulsuz alternativi yaz, razılıq almadan əlavə etmə.
- Açıq mənbəli, pulsuz kitabxanalardan istifadə etmək olar (yoxlanılmış, aktiv saxlanılan və lisenziyası uyğun olanlar). Hər kitabxana üçün `npm audit`/`composer audit` yoxlansın.
- Hər şey tək hostinq və öz verilənlər bazası ilə işləməlidir. Tətbiq `.env` ilə konfiqurasiya olunur, quraşdırma və deploy addımları README-də olsun.

### 4.5 Hostinq məhdudiyyətləri və istehsala çıxış üçün minimum tələblər
- **Antivirus (ClamAV)** və **daimi/queue worker** hostinqdə mümkün deyilsə, bunu **səssizcə keçmə**. Mənə açıq yaz və bölmə 1c, 5-ci maddəsinə görə qərar tələb et. Alternativ təkliflər: fayl qəbulunu müvəqqəti məhdud tiplərə (PDF/JPG/PNG) endirmək, yüklənmiş faylları yalnız təlimçinin brauzerində "yüklə, açma" rejimində vermək, ayrıca skan xidməti. Minimum tələb: **skan edilməyən fayl heç bir istifadəçiyə endirilə bilməz.**
- Daimi worker mümkün deyilsə: hər dəqiqə işləyən cron ilə `outbox` və planlaşdırılmış bildirişlər prosessoru. Cron intervalı 1 dəqiqədən böyükdürsə, demo bitmə və xatırlatma vaxtlarının dəqiqliyi və e-mail gecikməsi məhdudiyyəti mənə yazılsın.
- SMTP limiti demo qeydiyyatı həcminə çatmırsa, hostinqin limitinə uyğun növbə və prioritet (demo linki ən yüksək prioritet).

### 4.6 Performans və test mühiti
1080p video performans hədəfləri (bölmə 2.3b) saxlanılır, amma **real test nəticəsi olmadan təmin edilmiş kimi göstərilmir**. Test mühitini dəqiqləşdir və hesabatda yaz:
- **cihaz profili:** orta səviyyəli masaüstü və orta səviyyəli mobil (məs. Lighthouse "Moto G Power" profili);
- **şəbəkə:** ən azı iki profil (sürətli broadband və yavaş 4G, aydın Mbps ilə);
- **Lighthouse şərtləri:** hansı rejim (mobil/masaüstü), neçə dəfə işlədilib, median;
- **video metrikləri:** ilk kadr (time-to-first-frame), buffer sayı və müddəti, orta keyfiyyət, keyfiyyət dəyişmə sayı;
- **yük testi:** gözlənilən eyni vaxtda tələbə sayı (bölmə 4.1-in 9-cu sualı) ilə tətbiq (video xaric) yük testi.

## 5. Mərhələ planı
Hər mərhələ ayrıca branch və PR ilə təqdim olunur, hər birinin sonunda testlər və README yenilənməsi var. Mərhələlər arasında təsdiqimi gözlə. Landing dəyişiklikləri (M8) platforma ilə paralel gedə bilər.

| Mərhələ | Məzmun | Qəbul meyarı |
|---|---|---|
| **M0** | Hostinq cavabları, arxitektura təklifi, ERD, billing və auth axınları, mərhələ planının uyğunlaşdırılması | Mənim təsdiqim |
| **M1** | Əsaslar: layihə skeleti, dizayn sistemi, 3 dilli UI skeleti, auth (e-mail təsdiqi, parol, 2FA admin üçün), sessiyalar, rollar, audit log, bildiriş və `outbox` infrastrukturu, CI (test, lint, `audit`, secret scan) | Auth və icazə testləri keçir |
| **M2** | Məzmun modeli: kurs/modul/dərs, JSON import/export sinxronizasiyası, video adapter (Bunny), dərs səhifəsi, player, progress, "Dərsi tamamladım", versiyalaşdırma əsasları | Progress və tamamlanma testləri, import idempotentdir |
| **M3** | Demo axını: `demo_claims`, normallaşdırma, birdəfəlik e-mail bloku, 24 saat, e-mail linki və outbox, ilk 2 modul, demo giriş məhdudiyyəti, landing formunun inteqrasiyası | Bölmə 7-dəki bütün demo testləri |
| **M4** | Ev tapşırıqları, fayl təhlükəsizliyi (antivirus/karantin), qiymətləndirmə, ballar, diplom hesablanması, snapshot, PDF və açıq yoxlama | Diplom sərhəd testləri, fayl testləri |
| **M5** | Canlı görüşlər (avtomatik cədvəl, `.ics`, xatırlatmalar), suallar, yazılar, 1:1 slotlar və atomik rezervasiya | Cədvəl və race condition testləri |
| **M6** | Billing: `prices`, `subscriptions`, `billing_rules`, giriş statusları, admin əl ilə aktivləşdirmə, sonra Epoint/Payriff adapteri, webhook, lifecycle ssenariləri, demo → ödəniş keçidi | Billing və keçid testləri |
| **M7** | Admin analitikası, admin panelin tamamlanması, bildirişlərin tam siyahısı | Analitika və bildiriş testləri |
| **M8** | Landing: qiymət (99 AZN), 3 dil (AZ/EN/RU), `hreflang`, sitemap, schema, `llms*.txt`, FAQ/bloq/CMS dilləri, miqrasiya | SEO və link testləri |
| **M9** | Security audit, düzəlişlər, performans testləri (bölmə 4.6), backup və restore testi, rollback planı, README, istehsala çıxış | Audit hesabatı və DoD |

## 6. Bitəndə: Security audit (məcburi)
Bütün funksionallıq bitəndən sonra tam audit et, tapılanları düzəlt və hesabat yaz (`docs/security-audit.md`). OWASP Top 10 və OWASP ASVS Level 2 əsas götürülsün. Minimum yoxlanacaqlar:

- **Access control / IDOR**:
  - demo istifadəçi seçilmiş kursun 3-cü və sonrakı modullarının dərsini, tokenini, materialını və tapşırığını heç bir yolla ala bilmir;
  - müddəti bitmiş demo bloklanır, davam edən video token yenilənməsi rədd olunur;
  - demo istifadəçi **başqa kursun** demosunu ala bilmir;
  - kursu almayan 1:1, canlı görüş linki, yazılar və diplom endpoint-lərinə daxil ola bilmir;
  - bir kursdakı ödəniş digər kursa giriş vermir;
  - tələbə başqasının tapşırığını, faylını, qeydlərini və diplomunu görə bilmir;
  - təlimçi yalnız öz kursunu görür;
  - Bütün bunlar üçün **avtomatik testlər** olsun: API səviyyəsində və Playwright e2e. **Hər həssas endpoint üçün ownership testi** olsun.
- **Verilənlər bazası və authorization:**
  - **PostgreSQL seçilərsə:** RLS həssas cədvəllərdə aktivdir və testlə yoxlanılıb, tətbiqin DB istifadəçisi RLS-i bypass edə bilməyən səlahiyyətlə işləyir. RLS tətbiq səviyyəsindəki yoxlamaları əvəz etmir, hər ikisi test olunur.
  - **MySQL seçilərsə:** RLS yoxdur və bu hesabatda açıq yazılır. Əvəzində server-side authorization, parametrləşdirilmiş sorğular və ownership testləri audit olunur.
  - Verilənlər bazası səlahiyyətləri minimum olmalıdır (tətbiq DB istifadəçisi sxemi dəyişə bilməməlidir).
- **Auth və sessiyalar:**
  - parolun güclü hash ilə saxlanması (Argon2id və ya bcrypt);
  - **session cookie-ləri:** `HttpOnly`, `Secure`, `SameSite`; **session rotation** giriş və yüksək səlahiyyət dəyişəndə; **logout zamanı server-side session ləğvi**;
  - **e-mail enumeration qoruması:** giriş, qeydiyyat və parol bərpası cavabları hesabın mövcudluğunu açmır;
  - **parol bərpası tokenləri:** təsadüfi, hash ilə saxlanılan, qısa ömürlü, birdəfəlik; istifadədən sonra bütün sessiyalar ləğv olunur;
  - **TOTP əsaslı 2FA** (admin və təlimçi üçün məcburi) və **recovery code-lar** (hash ilə saxlanılır, birdəfəlik);
  - e-mail təsdiqi və demo e-mail linki (hash, birdəfəlik, expiry, resend limit);
  - eyni vaxtda sessiya limiti.
- **Rate limit** (hamısı test olunur): giriş, parol bərpası, demo qeydiyyatı, fayl yükləmə, video token, webhook endpoint-i, açıq diplom yoxlaması.
- **Demo limiti keçid cəhdləri:** eyni e-mail, eyni telefon, e-mail `+tag` və Gmail nöqtə variantları, böyük/kiçik hərf, telefon format variantları, **kurs dəyişməklə** ikinci demo, **paralel qeydiyyat**. **Telefon SMS ilə təsdiqlənmədiyi üçün başqa nömrə ilə keçid cəhdi mümkündür, bu qalıq risk hesabatda açıq yazılır.**
- **Video**:
  - token TTL və müddətin demo/grace ilə məhdudlaşması;
  - tokenin başqa istifadəçi və ya domendə işləməməsi;
  - HLS açarlarının qorunması;
  - birbaşa fayl linklərinin olmaması;
  - watermark;
  - eyni vaxtda sessiya limiti.
  - Qeyd: 100% yükləmə qorunması mümkün deyil, qalan riski hesabatda açıq yaz.
- **Fayl yükləmə:** tip və ölçü yoxlaması, **antivirus və karantin testləri** (zərərli test faylı EICAR ilə), skan edilməyən faylın endirilməməsi, private storage və imzalı linklər, fayl adlarının randomlaşdırılması, faylın icra olunmaması, ZIP bomb və makro testləri, storage limiti.
- **XSS və injection**: tapşırıq cavabları, rəylər, suallar və qeydlərin render-i sanitizasiya olunur; parametrləşdirilmiş sorğular.
- **CSRF**, **CORS** (yalnız öz domenlər, demo endpoint-i üçün landing domeni), **SSRF**, açıq redirect.
- **HTTP başlıqları**: CSP, HSTS, `frame-ancestors`, Referrer-Policy, Permissions-Policy, X-Content-Type-Options. Landing-in `netlify.toml`-u da yoxlanılsın.
- **Ödəniş:**
  - webhook imza yoxlaması, **replay və duplicate event testləri**, idempotentlik;
  - **payment race condition testləri** (iki paralel webhook, bir ödəniş bir dəfə tətbiq olunur);
  - məbləğin serverdə hesablanması, kart məlumatının saxlanmaması;
  - enrollment-in yalnız təsdiqlənmiş ödənişdən sonra açılması, ardıcıllıqdan kənar hadisələr;
  - **diplomun ödəniş müddəti bitəndən sonra açıq qalması**.
- **1:1 rezerv**: race condition (eyni slot iki nəfərə), ləğv qaydalarının serverdə tətbiqi, bir hüququn çoxaldılmaması.
- **Diplom:** ownership, açıq yoxlama endpoint-inin enumerasiyaya qarşı qorunması, snapshot dəyişməzliyi.
- **Sirlər:** repo tarixçəsində sirr yoxdur (`gitleaks` və ya oxşarı ilə **secret scanning**), `.env` gitignore-dadır, açarlar rotasiya oluna bilir.
- **Kod təhlili:** **SAST** (məs. Semgrep) CI-da işləyir.
- **Asılılıqlar**: `npm audit`/`composer audit`, Dependabot və ya Renovate aktiv, lockfile commit olunub.
- **Mühitlər:** **staging və production ayrıdır** (ayrı verilənlər bazası, ayrı açarlar, ayrı ödəniş sandbox/live açarları). Staging-də real istifadəçi məlumatı yoxdur.
- **Decap CMS**: OAuth app minimal scope, redaktor icazələri, `/admin` noindex.
- **Spam**: landing və demo formalarında spam qoruması (honeypot, minimum doldurma vaxtı, rate limit; xarici CAPTCHA yalnız bölmə 1c, 9-cu maddə təsdiqlənərsə).
- **Log, monitorinq:** xəta monitorinqi, şübhəli fəaliyyət logları (şəxsi məlumat və tokenlər log-a yazılmır), admin əməliyyatlarının **audit log-u** (dəyişməz, silinə bilməyən).
- **Backup və bərpa:** gündəlik DB backup, **backup şifrələnir**, ayrıca yerdə saxlanılır, **real restore testi** (backup-dan ayrı mühitdə bərpa) aparılıb və sənədləşib. **Rollback planı** (deploy geri qaytarma və miqrasiyanı geri qaytarma) yazılıb.

Hesabatda hər tapıntı üçün bunlar yazılsın: risk səviyyəsi, təsvir, necə düzəldildi və hansı test onu yoxlayır. Hesabatda ayrıca **qalıq risklər** bölməsi olsun (telefon əsaslı demo identifikasiyasının məhdudiyyəti, ekran yazısı ilə video qorunması və hostinq məhdudiyyətləri).

## 7. Test tələbləri (hər biri avtomatik test olmalıdır)

**Demo**
- Yeni istifadəçi qeydiyyatdan keçir və e-mailinə link dərhal göndərilir.
- 24 saat qeydiyyat vaxtından hesablanır, e-mail təsdiqindən yox.
- Linkin gec açılması demo müddətini uzatmır.
- Linkin yenidən göndərilməsi yeni demo yaratmır və köhnə linki etibarsız edir.
- Eyni (normallaşdırılmış) e-mail ilə ikinci demo mümkün deyil.
- Eyni (normallaşdırılmış) telefonla ikinci demo mümkün deyil.
- İstifadəçi digər kursda ikinci demo ala bilmir.
- İki paralel qeydiyyat eyni e-mail ilə yalnız bir demo yaradır.
- Demo yalnız seçilmiş kursun ilk iki moduluna giriş verir.
- 24 saatdan sonra video və material tokeni verilmir və davam edən videonun token yenilənməsi rədd olunur.
- Demo istifadəçisinə tapşırıq endpoint-i 403 qaytarır.
- SMTP uğursuz olanda outbox yenidən cəhd edir, admin yenidən göndərə bilir.

**Demo → ödəniş keçidi**
- Eyni `user_id` saxlanılır.
- Progress və qeydlər saxlanılır.
- Ödəniş tamamlandığı anda tam giriş açılır.
- Webhook təkrar gəldikdə ikinci dəfə aktivləşmə olmur.
- İki paralel webhook-da bir ödəniş yalnız bir dəfə tətbiq olunur.
- Əvvəl demo istifadə etmiş şəxs yenidən qeydiyyat etmədən kursa davam edir.

**Billing**
- Aylıq 99 AZN hesablanır, məbləğ serverdən gəlir.
- Hər kurs üçün ayrıca ödəniş tələb olunur, birinin ödənişi digərinə giriş vermir.
- 3 aylıq kursdan sonra tamamlamamış tələbə əlavə aylara ödəniş edə bilir (3 aylıq sərt son tarix yoxdur).
- Avtomatik renewal uğursuz olduqda `past_due` yaranır.
- 3 günlük grace period ərzində giriş qalır, bitəndə bağlanır.
- `cancel_at_period_end` olduqda `paid_until` tarixinə qədər giriş qalır, sonra grace və bağlanma.
- Sonradan ödəniş bərpa olunanda köhnə progress saxlanılır.
- Webhook ardıcıllıqdan kənar gələndə status geri düşmür.

**Diplom**
- 74,9% nəticədə diplom yaranmır.
- 75% nəticədə diplom yaranır.
- Maksimum bal cəmi 0 olanda diplom yaranmır.
- Göndərilməyən tapşırıq 0 bal sayılır.
- Ödənişin bitməsi diplom hüququnu ləğv etmir və diplom yenidən yüklənir.
- Sonradan tapşırıq əlavə edilməsi artıq verilmiş diplomu ləğv etmir.
- Eyni tələbə və kurs üçün ikinci diplom yaranmır.

**Dərs və progress**
- Video izləmə mövqeyi saxlanılır, bərpa olunur.
- İstifadəçi dərsi özü tamamlandı işarələyir, təkrar sorğu ikiqat progress yaratmır.
- Video izləmə faizi dərsi özü tamamlamır.
- Modul bütün dərslər tamamlanmadan bitmiş sayılmır.
- 1:1 bütün modullar tamamlanmadan açılmır və açılandan sonra yeni dərs əlavə olunsa itmir.

**Canlı görüş və mentorluq**
- Hər ayın ikinci və dördüncü şənbəsi 11:00-da (`Asia/Baku`) görüş yaradılır, təkrar işə salınma dublikat yaratmır.
- Admin görüşü dəyişdikdə bildiriş gedir və `.ics` yenilənir.
- Ləğv edilmiş görüşün xatırlatması göndərilmir.
- Mentor slotuna race condition: iki paralel rezerv, yalnız biri uğurludur.

**Landing və dillər**
- Hər üç dildə başlıq və təsvir uzunluqları, sınıq link, `hreflang` qarşılıqlı uyğunluğu, sitemap alternativləri.
- Tərcüməsi olmayan bloq yazısı həmin dilin siyahısında görünmür.
- AZ URL-ləri miqrasiyadan sonra dəyişməyib.
- EN və RU səhifələrdə "kurs Azərbaycan dilindədir" qeydi var.
- Landing-də hər kursun yanında "99 AZN / ay" göstərilir.

**Security**
- Bütün əsas negative authorization ssenariləri.
- Demo limit bypass testləri.
- Account enumeration.
- Session hijacking-ə qarşı qoruma (cookie bayraqları, rotation, logout).
- Upload təhlükəsizliyi (EICAR, karantin, MIME, ZIP bomb).
- Payment replay.
- Diplom ownership.
- Backup restore.

## 8. Hazırdır demək üçün şərtlər (Definition of Done)
- [ ] **Demo:** qeydiyyat → 24 saat (qeydiyyatdan hesablanır) → yalnız seçilmiş kursun ilk 2 modulu açıq → vaxt bitəndə bağlanır. Platforma üzrə yalnız bir demo, e-mail və telefon normallaşdırılıb, birdəfəlik e-mail bloklanıb, paralel qeydiyyat testi keçir.
- [ ] **Demo e-mail linki:** hash, birdəfəlik, expiry, resend limit, outbox və admin yenidən göndərmə.
- [ ] Demo istifadəçi ev tapşırığını heç görmür (UI və API).
- [ ] **Demo → ödəniş** eyni `user_id` ilə tam kursa keçir, progress və qeydlər qorunur, testlə yoxlanılıb.
- [ ] Ödənişli tələbə kursun bütün modullarını, tapşırıqlarını, canlı görüşlərini, 1:1 rezervini və diplomunu istifadə edə bilir. Bir kursun ödənişi digərinə giriş vermir.
- [ ] **Billing:** hər kurs aylıq 99 AZN ayrıca, 3 aylıq sərt son tarix yoxdur, `paid_until`, 3 günlük grace, `past_due`/`expired` keçidləri, `cancel_at_period_end`, billing statusu enrollment statusundan ayrıdır, webhook idempotentdir, lifecycle ssenariləri testlə yoxlanılıb.
- [ ] Billing konfiqurasiyası (`min_commitment_months`, `auto_stop_on_completion`, `grace_period_days`) kodda sərt yazılmayıb.
- [ ] **Dərsin tamamlanması** yalnız "Dərsi tamamladım" ilə olur, progress idempotentdir.
- [ ] Kursu almayanlar 1:1 bölməsini görmür və API 403 qaytarır. 1:1 yalnız bütün modulların bütün dərsləri tamamlananda açılır və açıldıqdan sonra itmir.
- [ ] **Diplom:** bal cəmi ≥ 75% (məxrəc məcburi tapşırıqların maksimum bal cəmi), sərhəd halları (74,9% / 75%) testlə yoxlanılıb, snapshot saxlanılır, ödəniş bitəndən sonra qalır və yenidən yüklənir.
- [ ] **Canlı görüşlər:** hər ayın 2-ci və 4-cü şənbəsi 11:00 `Asia/Baku`, avtomatik yaranır, admin dəyişə/ləğv edə bilir, bildiriş və `.ics` düzgündür.
- [ ] **Versiyalaşdırma:** kurs/dərs/tapşırıq/maksimum bal dəyişikliyi mövcud tələbələrin progress-ini, 1:1 hüququnu və diplomunu pozmur, admin təsir görünüşünü görür.
- [ ] **Bildirişlər:** vahid modul, queue/outbox, retry, təkrarlanma qarşısı, 3 dilli şablonlar.
- [ ] 1080p video: adaptiv pillələr, 720p ilə başlanğıc, mobil məlumat qənaəti rejimi. Performans hədəfləri (ilk kadr < 2,5 san, Lighthouse ≥ 90) **bölmə 4.6-dakı test mühitində ölçülüb və hesabatda yazılıb**.
- [ ] Videolar birbaşa yüklənmir: imzalı tokenlər, token yenilənmə zamanı hüquq yoxlaması, watermark, sessiya limiti var.
- [ ] Dərs səhifəsi: solda modullar, ortada video və altında tapşırıq paneli. Mobil uyğundur.
- [ ] Fayl yükləmə: tip/ölçü/storage limiti, antivirus və karantin (və ya bölmə 4.5-ə uyğun təsdiqlənmiş alternativ), skan olunmayan fayl endirilmir.
- [ ] Admin paneldən kurs, dərs, tapşırıq, görüş, slot və tələbə idarə olunur. Admin analitikası (bölmə 2.11b) işləyir.
- [ ] **Landing 3 dildədir** (AZ/EN/RU), menyuda dil seçimi var, `hreflang`, sitemap və schema hər dil üçün düzgündür. İngilis və rus versiyalar təbii dildə yazılıb, `llms.txt` ingilis dilində də var, "kurs Azərbaycan dilindədir" qeydi bütün dillərdədir.
- [ ] Landing-də hər kurs üçün "99 AZN / ay" yazılıb və hər kursun ayrıca ödənildiyi göstərilib.
- [ ] Platforma UI, e-mail şablonları və bildirişlər 3 dildədir, dil hesabda saxlanılır. Platforma landing-in dizayn sisteminə uyğundur.
- [ ] Məzmunun tək mənbəyi (bölmə 3.3) işləyir, landing və platforma arasında uyğunsuzluq yoxdur, import/export təkrar işə salınanda dublikat yaratmır.
- [ ] **Security audit hesabatı** hazırdır (seçilmiş verilənlər bazasına uyğun RLS/ownership fərqi yazılıb), kritik və yüksək riskli tapıntı qalmayıb, qalıq risklər yazılıb.
- [ ] Staging və production ayrıdır, SAST və secret scanning CI-da işləyir, backup şifrələnir və real restore testi keçib, rollback planı yazılıb.
- [ ] README yenilənib: quraşdırma, `.env.example`, deploy, admin istifadəsi, backup və bərpa.
- [ ] Lighthouse əlçatanlıq ≥ 95. Əsas axınlar üçün e2e testlər CI-da işləyir.
