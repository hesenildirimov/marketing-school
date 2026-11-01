# Marketing School — marketingschool.az

Azərbaycan dilində onlayn marketinq və rəqəmsal marketinq kurslarının landing saytı.
Statik sayt ([Eleventy](https://www.11ty.dev/)) + [Decap CMS](https://decapcms.org/). Framework yoxdur, əsas səhifələrdə JS yoxdur (yalnız ~0.6 KB scroll-animasiya skripti `src/_includes/base.njk`-in sonunda).

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
`netlify.toml` hazırdır (build: `npm run build`, publish: `_site`, www→apex və http→https yönləndirmələri). Netlify-da repo-nu qoşmaq kifayətdir. Forma `data-netlify="true"` ilə Netlify Forms-a düşür (Netlify → Forms).

## Hələ edilməli işlər
- [ ] **Qiymət**, **telefon**, **ünvan**, **e-mail** — boşdur, `kurslar/*.json` (`price`) və `site.json`-da doldurulanda schema (`offers`, `address`) və səhifələrdə avtomatik görünür.
- [ ] **Pulsuz demo dərs**: forma hələ yalnız e-mail/telefon toplayır. Platforma hazır olanda 1 günlük giriş məlumatının göndərilməsi (Netlify Forms bildirişi → e-mail, və ya platformaya inteqrasiya) qurulmalıdır. Telefon sahəsi məcburidir (`src/_includes/form.njk`).
- [ ] Kurs modullarının və videodərslərin saatı/sayı dəqiqləşəndə `kurslar/*.json`-a əlavə edilməlidir (indi uydurma rəqəm yoxdur).
- [ ] Təlimçi mətnləri (`instructor.highlights`) LinkedIn profillərindən götürülüb — dərc etməzdən əvvəl təlimçilər təsdiq etməlidir (şirkət adlarının açıq yazılması daxil).
- [ ] `og.png` (paylaşım şəkli) sadə hazırlanıb — dizayner variantı ilə əvəz oluna bilər (1200×630).
- [ ] Bloq: ilk 5 yazı planı — "digital marketinq nədir", "SMM nədir", "Google Ads necə öyrənilir" və s. (SEO/GEO üçün ən böyük təsir buradan gəlir).

## Dizayn qaydaları
- Əsas rəng sarı (`--brand` və s., `style.css`-in yuxarısı və sonundakı "Sarı mono palitra" bölməsi). Vurğular sarı/tünd; yeni rəng əlavə etməyin.
- Tək şrift ailəsi (sistem şrifti), xarici şrift yoxdur.
- Animasiya: yalnız yüngül scroll reveal; `prefers-reduced-motion`-a hörmət edilir.

## SEO / GEO prinsipləri
- Hər kursda **"Qısa cavab"** (`summary`) və faktlar cədvəli var — AI cavablarının sitat gətirdiyi hissədir. Rəqəmləri (müddət, format, modul sayı) bütün yerlərdə eyni saxlayın.
- FAQ sualları insanların Google/ChatGPT-yə yazdığı kimi yazılmalıdır; cavabın ilk cümləsi birbaşa cavab olmalıdır.
- Başlıqlarda "rəqəmsal", axtarış üçün title/FAQ/schema-da "digital" də işlənir.
- Kurs `slug`-unu dəyişməyin; dəyişsə `netlify.toml`-a 301 əlavə edin.
