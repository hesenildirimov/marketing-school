// Landing testləri (bölmə 7 — "Landing və dillər"). İşə salma: npm test (əvvəlcə build edir).
import { test } from "node:test";
import assert from "node:assert/strict";
import fs from "node:fs";
import path from "node:path";
import { execFileSync } from "node:child_process";
import { migrate } from "../src/_includes/i18n-check.js";

const SITE = "_site";
const ORIGIN = "https://marketingschool.az";
const walk = (d) => fs.readdirSync(d, { withFileTypes: true }).flatMap((e) => (e.isDirectory() ? walk(path.join(d, e.name)) : [path.join(d, e.name)]));
const html = walk(SITE).filter((f) => f.endsWith(".html"));
const urlOf = (f) => "/" + path.relative(SITE, f).replace(/\\/g, "/").replace(/index\.html$/, "");
const pages = Object.fromEntries(html.map((f) => [urlOf(f), fs.readFileSync(f, "utf8")]));
const indexable = Object.entries(pages).filter(([u, h]) => !/noindex/.test(h.match(/<meta name="robots" content="([^"]+)"/)?.[1] ?? "") && !u.startsWith("/admin"));
const attr = (h, re) => [...h.matchAll(re)].map((m) => m[1]);
const fileFor = (u) => path.join(SITE, u.replace(/^\//, ""), u.endsWith("/") ? "index.html" : "");

test("AZ URL-ləri miqrasiyadan sonra dəyişməyib", () => {
  for (const u of ["/", "/marketinq-kursu/", "/digital-marketinq-kursu/", "/faq/", "/bloq/", "/bloq/double-jeopardy-qanunu/", "/bloq/sov-som-esov-cercivesi/",
    "/tesekkur/", "/404.html", "/llms.txt", "/llms-full.txt", "/sitemap.xml", "/rss.xml", "/robots.txt"]) {
    assert.ok(fs.existsSync(u.endsWith("/") ? fileFor(u) : path.join(SITE, u)), `yoxdur: ${u}`);
  }
  assert.match(pages["/"], /<html lang="az">/);
});

test("hər dildə başlıq (≤60) və təsvir (50–160) uzunluqları", () => {
  for (const [u, h] of indexable) {
    const title = h.match(/<title>([^<]*)<\/title>/)?.[1] ?? "";
    const desc = h.match(/<meta name="description" content="([^"]*)"/)?.[1] ?? "";
    const t = title.replace(/&amp;/g, "&");
    assert.ok(t.length > 0 && t.length <= 60, `${u}: başlıq ${t.length}: ${t}`);
    assert.ok(desc.length >= 50 && desc.length <= 160, `${u}: təsvir ${desc.length}`);
  }
});

test("düzgün <html lang> və dil seçimi hər səhifədə", () => {
  for (const [u, h] of indexable) {
    const lang = u.startsWith("/en/") || u === "/en/" ? "en" : u.startsWith("/ru/") ? "ru" : "az";
    assert.match(h, new RegExp(`<html lang="${lang}">`), u);
    assert.match(h, /class="lang-switch"/, u);
    assert.doesNotMatch(h, /location\.(replace|href)\s*=|http-equiv="refresh"/, `${u}: avtomatik yönləndirmə olmamalıdır`);
  }
});

test("hreflang qarşılıqlı uyğundur və x-default var", () => {
  for (const [u, h] of indexable) {
    const alts = Object.fromEntries([...h.matchAll(/<link rel="alternate" hreflang="([a-z-]+)" href="([^"]+)">/g)].map((m) => [m[1], m[2]]));
    assert.ok(alts["x-default"], `${u}: x-default yoxdur`);
    const self = ORIGIN + u;
    assert.ok(Object.values(alts).includes(self), `${u}: özünə hreflang yoxdur`);
    assert.equal(h.match(/<link rel="canonical" href="([^"]+)">/)?.[1], self, `${u}: canonical`);
    for (const [lang, href] of Object.entries(alts)) {
      if (lang === "x-default") continue;
      const other = pages[href.replace(ORIGIN, "")];
      assert.ok(other, `${u}: ${lang} alternate səhifəsi yoxdur: ${href}`);
      assert.ok(other.includes(`hreflang="${lang}" href="${href}"`) && other.includes(`href="${self}"`), `${href} → ${u} geri istinad yoxdur`);
    }
  }
});

test("sitemap: bütün URL-lər mövcuddur və dil alternativləri var", () => {
  const sm = fs.readFileSync(`${SITE}/sitemap.xml`, "utf8");
  const locs = attr(sm, /<loc>([^<]+)<\/loc>/g);
  assert.ok(locs.length >= 17);
  for (const l of locs) assert.ok(pages[l.replace(ORIGIN, "")], `sitemap-da mövcud olmayan URL: ${l}`);
  for (const block of sm.split("<url>").slice(1)) {
    assert.match(block, /hreflang="x-default"/);
    for (const href of attr(block, /href="([^"]+)"/g)) assert.ok(pages[href.replace(ORIGIN, "")], `alternate mövcud deyil: ${href}`);
  }
  for (const [u] of indexable) assert.ok(locs.includes(ORIGIN + u), `sitemap-da yoxdur: ${u}`);
});

test("sınıq daxili link yoxdur", () => {
  for (const [u, h] of Object.entries(pages)) {
    for (const href of attr(h, /href="(\/[^"#?]*)/g)) {
      if (href.startsWith("/platform/")) continue; // platforma (VPS) — ayrıca test olunur
      const target = href.endsWith("/") ? fileFor(href) : path.join(SITE, href);
      assert.ok(fs.existsSync(target), `${u}: sınıq link ${href}`);
    }
  }
});

test("boş və ya çatışmayan tərcümə sahəsi yoxdur; miqrasiya idempotentdir", () => {
  const r = migrate({ write: false });
  assert.deepEqual(r.changed, [], "miqrasiya skripti təkrar işə salınanda dəyişiklik etməməlidir");
  assert.deepEqual(r.empty, {}, "boş tərcümə sahələri");
});

test("tərcüməsi olmayan bloq yazısı həmin dilin siyahısında görünmür", () => {
  const ru = pages["/ru/blog/"];
  assert.doesNotMatch(ru, /double-jeopardy|sov-som/);
  assert.match(ru, /На этом языке статей пока нет/);
  assert.match(pages["/en/blog/"], /\/en\/blog\/double-jeopardy-law\//);
  assert.doesNotMatch(pages["/en/blog/"], /\/bloq\/double-jeopardy-qanunu\//);
  assert.ok(!fs.existsSync(`${SITE}/ru/blog/double-jeopardy-qanunu`));
});

test("EN və RU səhifələrdə 'kurs Azərbaycan dilindədir' qeydi var", () => {
  for (const u of ["/en/", "/en/marketing-course/", "/en/digital-marketing-course/", "/en/faq/"]) assert.match(pages[u], /taught in Azerbaijani/, u);
  for (const u of ["/ru/", "/ru/kurs-marketinga/", "/ru/kurs-digital-marketinga/", "/ru/faq/"]) assert.match(pages[u], /на азербайджанском языке/, u);
  assert.match(fs.readFileSync(`${SITE}/en/llms.txt`, "utf8"), /taught in Azerbaijani/);
});

test("hər kursun yanında 99 AZN / ay göstərilir və hər kursun ayrıca ödənildiyi yazılıb", () => {
  const checks = { "/": ["99 AZN / ay", "Hər kurs ayrıca 99 AZN-dir"], "/en/": ["99 AZN per month", "billed separately"], "/ru/": ["99 AZN в месяц", "оплачивается отдельно"] };
  for (const [u, [price, note]] of Object.entries(checks)) {
    assert.equal((pages[u].match(new RegExp(`class="price-tag">${price}`, "g")) || []).length, 2, `${u}: hər kurs kartında qiymət`);
    assert.ok(pages[u].includes(note), `${u}: ${note}`);
  }
  for (const u of ["/marketinq-kursu/", "/digital-marketinq-kursu/"]) {
    assert.match(pages[u], /99 AZN \/ ay \(hər kurs ayrıca 99 AZN-dir\)/, `${u}: faktlar cədvəli`);
    assert.doesNotMatch(pages[u], /təxminən|-dən başlayaraq|Müraciət formu ilə göndərilir/, `${u}: qiymət sabitdir`);
  }
  assert.match(pages["/faq/"], /Hər kursun qiyməti ayrıca 99 AZN\/aydır/);
});

test("schema: Course offers 99 AZN, tədris dili az, səhifə dili tərcümə olunub", () => {
  for (const [u, lang] of [["/marketinq-kursu/", "az"], ["/en/marketing-course/", "en"], ["/ru/kurs-marketinga/", "ru"]]) {
    const blocks = attr(pages[u], /<script type="application\/ld\+json">([\s\S]*?)<\/script>/g).map((b) => JSON.parse(b));
    const c = blocks.find((b) => b["@type"] === "Course");
    assert.equal(c.inLanguage, "az");
    assert.equal(c.offers.price, "99");
    assert.equal(c.offers.priceCurrency, "AZN");
    assert.equal(c.offers.availability, "https://schema.org/InStock");
    assert.deepEqual(c.hasCourseInstance[0].courseSchedule.byMonthWeek, [2, 4]);
    const org = blocks.find((b) => b["@type"] === "EducationalOrganization");
    assert.equal(org.inLanguage, lang);
    assert.ok(org.sameAs.length >= 3, "sameAs bütün dillərdə eynidir");
    const faq = blocks.find((b) => b["@type"] === "FAQPage");
    assert.equal(faq.inLanguage, lang);
  }
  assert.match(pages["/en/marketing-course/"], /"name":"Marketing Course"/);
});

test("llms.txt hər üç dildə; platforma robots-da bağlıdır; landing-də köhnə '2 həftədən bir' yoxdur", () => {
  for (const f of ["llms.txt", "en/llms.txt", "ru/llms.txt", "llms-full.txt", "en/llms-full.txt", "ru/llms-full.txt"]) assert.ok(fs.existsSync(`${SITE}/${f}`), f);
  assert.match(fs.readFileSync(`${SITE}/robots.txt`, "utf8"), /Disallow: \/platform\//);
  for (const [u, h] of Object.entries(pages)) assert.doesNotMatch(h, /2 həftədən bir/, u);
  assert.doesNotMatch(fs.readFileSync(`${SITE}/llms.txt`, "utf8"), /&amp;/);
});

test("demo forması platforma endpoint-inə gedir, Netlify Forms yoxdur, 'Hər ikisi' seçimi yoxdur", () => {
  for (const u of ["/", "/en/", "/ru/kurs-marketinga/"]) {
    assert.match(pages[u], /<form name="demo" method="POST" action="\/platform\/api\/demo\/register"/);
    assert.doesNotMatch(pages[u], /data-netlify|her-ikisi/);
    assert.match(pages[u], /name="consent"[^>]*required/);
  }
  assert.match(pages["/en/"], /name="lang" value="en"/);
});

test("Decap CMS SRI ilə yüklənir; landing-də inline skript yoxdur (CSP)", () => {
  assert.match(pages["/admin/"], /integrity="sha384-[A-Za-z0-9+/=]+" crossorigin="anonymous"/);
  for (const [u, h] of indexable) {
    const inline = [...h.matchAll(/<script(?![^>]*\bsrc=)(?![^>]*application\/ld\+json)[^>]*>/g)];
    assert.equal(inline.length, 0, `${u}: inline skript CSP-ni pozur`);
  }
  assert.match(fs.readFileSync("netlify.toml", "utf8"), /Content-Security-Policy = "default-src 'self'; script-src 'self';/);
});
