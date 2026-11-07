# Performans (M9)

Bu sənəd nəyin **ölçüldüyünü**, nəyin **hələ ölçülmədiyini** və istehsalda necə ölçüləcəyini ayırır.
Lokal nəticələr istehsal rəqəmi deyil — Netlify CDN, proxy (≈ +30–80 ms), real VPS və Bunny CDN onları dəyişəcək.

## 1. Metodologiya

| Parametr | Dəyər |
|---|---|
| Alət | Lighthouse 12.8.2, Chromium (headless), hər səhifə ayrıca proses |
| Mobil profil | Lighthouse default: Moto G Power emulyasiyası, 4× CPU yavaşlatma, "Slow 4G" simulyasiyası (150 ms RTT, 1.6 Mbit/s) |
| Desktop profil | `--preset=desktop` (40 ms RTT, 10 Mbit/s, CPU yavaşlatma yox) |
| Təkrar | Mobil: 3 qaçış, **median** götürülür. Desktop: 1 qaçış |
| Landing | `npm run build` → `_site/` statik server (127.0.0.1:8090) |
| Platforma | `php artisan serve` (127.0.0.1:8001), `APP_ENV=local`, `ms_e2e` DB (`platform:dev-seed`), dərs səhifəsi daxil olmuş tələbə cookie-si ilə |
| Video | `VIDEO_PROVIDER=fake` — real HLS axını yoxdur, ona görə video TTFF burada ölçülmür |

Təkrar etmək üçün: `e2e/serve.sh` (platforma), `npx @11ty/eleventy && python3 -m http.server -d _site 8090`, sonra
`lighthouse <url> --only-categories=performance,accessibility,best-practices,seo [--preset=desktop]`.

## 2. Lokal nəticələr (2026-11-02, median)

| Səhifə | Profil | Perf | A11y* | Best pr. | SEO | LCP | CLS | TBT |
|---|---|---|---|---|---|---|---|---|
| Landing `/en/` | mobil | 100 | 100 | 100 | 100 | 1.36 s | 0 | 16 ms |
| Landing `/en/` | desktop | 100 | 100 | 100 | 100 | 0.34 s | 0 | 4 ms |
| Kurs səhifəsi `/marketinq-kursu/` | mobil | 100 | 100 | 100 | 100 | 1.22 s | 0.043 | 44 ms |
| Kurs səhifəsi `/marketinq-kursu/` | desktop | 100 | 100 | 100 | 100 | 0.31 s | 0.028 | 0 |
| Platforma `/platform/login` | mobil | 100 | 100 | 100 | 54† | 0.90 s | 0 | 0 |
| Platforma `/platform/login` | desktop | 100 | 100 | 100 | 54† | 0.24 s | 0 | 0 |
| Platforma dərs səhifəsi | mobil | 93 | 100 | 100 | 54† | 3.24 s | 0 | 40 ms |
| Platforma dərs səhifəsi | desktop | 100 | 100 | 100 | 54† | 0.69 s | 0 | 0 |

\* İlk ölçmədə a11y 93–97 idi (səbəb: `--muted` rənginin kontrastı 4.2:1, footer-də mətn içində linklər yalnız rənglə fərqlənirdi).
`--muted` `#6E7691` → `#5F6783` (5.2:1) edildi, footer linkləri altdan xətt aldı; yenidən ölçmədə hər üç səhifədə 100.

† Platforma səhifələri qəsdən `noindex` (X-Robots-Tag + meta) və meta description-suzdur — SEO balı burada hədəf deyil.

**Dərs səhifəsi mobil LCP 3.24 s**: LCP elementi video posteridir; `php artisan serve` tək axınlıdır və poster eyni prosesdən
verilir. İstehsalda poster Bunny CDN-dən gəlir, HTML nginx + PHP-FPM + OPcache ilə verilir. Hədəf: mobil LCP < 2.5 s (aşağıda).

## 3. Hələ ölçülməyənlər (istehsal/staging tələb edir)

| Göstərici | Hədəf | Necə ölçülür |
|---|---|---|
| Video TTFF (ilk kadr) | p75 < 2 s (4G), p75 < 1 s (Wi-Fi) | `player.js` ilk `playing` − klik vaxtını `ttff_ms` kimi göndərir (hazırdır); admin → analitika → video keyfiyyəti cədvəlində orta və p75 |
| Platforma TTFB (proxy ilə) | p95 < 400 ms | k6 (aşağıda) + Netlify loqları |
| API p95 (heartbeat, token) | p95 < 300 ms, xəta < 0.1% | `deploy/perf/k6-platform.js` |
| Landing real istifadəçi CWV | LCP p75 < 2.5 s, CLS < 0.1, INP < 200 ms | Google Search Console → Core Web Vitals (28 gün) |

## 4. Yük testi planı (100 eyni vaxtlı tələbə)

`deploy/perf/k6-platform.js`: 2 dəq ərzində 100 VU-ya qalxır, 10 dəq saxlayır. Hər VU: giriş → kurs → dərs → video token →
15 s-dən bir heartbeat (~40 dəfə) → token yeniləmə. Video trafiki Bunny CDN-dən gəldiyi üçün VPS-ə düşən yük ≈ 7 req/s (100 × 1/15 s)
+ səhifə keçidləri. Hədəflər skriptdə threshold kimi yazılıb (uğursuz olsa k6 non-zero qaytarır).

Staging-də işə salın (istehsal DB-sində **yox**):
```bash
php artisan platform:dev-seed --loadtest=100 --password=...   # loadtest+1..100@example.test, birinci kursa ödənişli giriş
k6 run -e BASE=https://staging.marketingschool.az -e PASSWORD=... deploy/perf/k6-platform.js
```

Minimum VPS (arxitektura sənədi, cədvəl sətir 3): 2 vCPU / 4 GB RAM / 60 GB NVMe (ClamAV ≈ 1.2–1.5 GB), PHP-FPM `pm.max_children=16`
(`deploy/php/www.conf`). Nəticə threshold-ları keçməsə ilk addım: `pm.max_children` və DB indekslərini
`pg_stat_statements` ilə yoxlamaq.
