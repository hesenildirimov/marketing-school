// DEMO=1 → düz .html fayllar (artifact önizləməsi üçün). Əks halda təmiz URL-lər: /bloq/slug/
import { ROUTES } from "./src/_includes/routes-map.js";

const DEMO = process.env.DEMO === "1";
const SITE = "https://marketingschool.az";
const LANGS = ["az", "en", "ru"];

const outPath = (slug) =>
  slug === "404" ? "404.html"
  : DEMO ? (slug ? slug.replace(/\//g, "-") : "ana-sehife") + ".html"
  : (slug ? slug + "/" : "") + "index.html";
const href = (slug) => (DEMO ? outPath(slug) : slug ? `/${slug}/` : "/");
const abs = (slug) => (slug === "404" ? `${SITE}/404.html` : `${SITE}/${slug ? slug + "/" : ""}`);
const langOf = (outputPath) => {
  const m = String(outputPath || "").replace(/\\/g, "/").match(/_(?:site|demo)\/(en|ru)(?:\/|-)/);
  return m ? m[1] : "az";
};

export default function (cfg) {
  cfg.addPassthroughCopy({ "src/assets": "/" });
  cfg.addGlobalData("demo", DEMO);
  cfg.addGlobalData("siteUrl", SITE);

  cfg.addFilter("out", outPath);
  cfg.addFilter("link", href);
  cfg.addFilter("abs", abs);
  cfg.addFilter("asset", (p) => (DEMO ? p : "/" + p));
  cfg.addFilter("json", (v) => JSON.stringify(v).replace(/</g, "\\u003c"));
  cfg.addFilter("md", (s) => (s ? cfg.markdownLibrary?.render(String(s)) ?? s : ""));
  cfg.addFilter("mdi", (s) => (s ? cfg.markdownLibrary?.renderInline(String(s)) ?? s : ""));
  cfg.addFilter("plain", (s) => String(s ?? "").replace(/<[^>]+>/g, "").replace(/\[([^\]]+)\]\([^)]+\)/g, "$1").replace(/[*_`#>]/g, "").trim());
  cfg.addFilter("ymd", (d) => new Date(d).toISOString().slice(0, 10));
  cfg.addFilter("rfc822", (d) => new Date(d).toUTCString());
  cfg.addFilter("words", (s) => String(s ?? "").replace(/<[^>]+>/g, " ").split(/\s+/).filter(Boolean).length);
  cfg.addFilter("pluck", (arr, key) => (arr || []).map((x) => x[key]));
  // ":n" kimi yer tutucuların doldurulması (UI mətnləri)
  cfg.addFilter("fill", (s, vars = {}) => String(s ?? "").replace(/:(\w+)/g, (m, k) => (k in vars ? vars[k] : m)));
  cfg.addFilter("crumbLd", (crumbs, homeLabel = "Ana səhifə", homeSlug = "") =>
    [[homeLabel, homeSlug], ...crumbs].map(([name, slug], i) => ({ "@type": "ListItem", position: i + 1, name, item: abs(slug) })));
  cfg.addFilter("where", (arr, key, val) => (arr || []).filter((x) => [].concat(x[key] ?? []).includes(val)));
  // Mətndəki kurs linki: [Kurs](kurs:digital-marketinq-kursu) → səhifənin dilindəki kurs URL-i
  cfg.addTransform("cms-links", function (html) {
    const lang = langOf(this.page?.outputPath);
    return html.replace(/href="kurs:([a-z0-9\/-]*)"/g, (_, s) => `href="${href(ROUTES.course[s]?.[lang] ?? s)}"`);
  });

  // .txt (llms.txt) çıxışları düz mətndir: Nunjucks-un HTML escaping-i geri qaytarılır
  cfg.addTransform("txt-unescape", function (out) {
    if (!String(this.page?.outputPath || "").endsWith(".txt")) return out;
    return out.replace(/&amp;/g, "&").replace(/&lt;/g, "<").replace(/&gt;/g, ">").replace(/&quot;/g, '"').replace(/&#39;/g, "'");
  });

  const posts = (glob) => (api) => api.getFilteredByGlob(glob).filter((p) => !p.data.draft).sort((a, b) => b.date - a.date);
  cfg.addCollection("posts_az", posts("src/bloq/*.md"));
  cfg.addCollection("posts_en", posts("src/en/blog/*.md"));
  cfg.addCollection("posts_ru", posts("src/ru/blog/*.md"));
  cfg.addCollection("posts", posts("src/bloq/*.md")); // geriyə uyğunluq (AZ)

  cfg.amendLibrary("md", (md) => { cfg.markdownLibrary = md; });

  return {
    dir: { input: "src", includes: "_includes", data: "_data" },
    templateFormats: ["njk", "md"],
    markdownTemplateEngine: false,
    htmlTemplateEngine: "njk",
  };
}
