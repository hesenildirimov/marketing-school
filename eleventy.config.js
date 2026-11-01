// DEMO=1 → düz .html fayllar (artifact önizləməsi üçün). Əks halda təmiz URL-lər: /bloq/slug/
const DEMO = process.env.DEMO === "1";
const SITE = "https://marketingschool.az";

const outPath = (slug) =>
  slug === "404" ? "404.html"
  : DEMO ? (slug ? slug.replace(/\//g, "-") : "ana-sehife") + ".html"
  : (slug ? slug + "/" : "") + "index.html";
const href = (slug) => (DEMO ? outPath(slug) : slug ? `/${slug}/` : "/");
const abs = (slug) => (slug === "404" ? `${SITE}/404.html` : `${SITE}/${slug ? slug + "/" : ""}`);

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
  cfg.addFilter("crumbLd", (crumbs) =>
    [["Ana səhifə", ""], ...crumbs].map(([name, slug], i) => ({ "@type": "ListItem", position: i + 1, name, item: abs(slug) })));
  cfg.addFilter("where", (arr, key, val) => (arr || []).filter((x) => [].concat(x[key] ?? []).includes(val)));
  // CMS-dən gələn blog yazısında internal link "slug" kimi yazıla bilər: [Kurs](kurs:digital-marketinq-kursu)
  cfg.addTransform("cms-links", (html) => html.replace(/href="kurs:([a-z0-9\/-]*)"/g, (_, s) => `href="${href(s)}"`));

  cfg.addCollection("posts", (api) =>
    api.getFilteredByGlob("src/bloq/*.md").filter((p) => !p.data.draft).sort((a, b) => b.date - a.date)
  );

  cfg.amendLibrary("md", (md) => { cfg.markdownLibrary = md; });

  return {
    dir: { input: "src", includes: "_includes", data: "_data" },
    templateFormats: ["njk", "md"],
    markdownTemplateEngine: false,
    htmlTemplateEngine: "njk",
  };
}
