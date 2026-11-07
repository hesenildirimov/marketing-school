import { ROUTES } from "./_includes/routes-map.js";
import { safe } from "./_includes/safe.js";
import { itemList } from "./_includes/schema.js";
const NAMES = { az: "Marketinq kursları — Bakı", en: "Online marketing courses — Baku, Azerbaijan", ru: "Онлайн-курсы маркетинга — Баку" };
export default {
  permalink: (d) => (process.env.DEMO === "1" ? (d.lang === "az" ? "ana-sehife.html" : `${d.lang}.html`) : `${ROUTES.home[d.lang] ? ROUTES.home[d.lang] + "/" : ""}index.html`),
  eleventyComputed: {
    slug: (d) => d.routes?.home?.[d.lang],
    alternates: (d) => d.routes?.home,
    seo_title: (d) => d.loc?.[d.lang]?.home.seo_title,
    seo_description: (d) => d.loc?.[d.lang]?.home.seo_description,
    ld: safe((d) => {
      const L = d.loc[d.lang];
      const slugs = Object.fromEntries(L.courses.map((c) => [c.slug, d.routes?.course?.[c.slug]?.[d.lang]]));
      return [
        { "@context": "https://schema.org", "@type": "WebSite", "@id": `${d.siteUrl}/#website`, url: `${d.siteUrl}/${d.routes?.home?.[d.lang] ? d.routes?.home?.[d.lang] + "/" : ""}`, name: L.site.name, inLanguage: d.lang, publisher: { "@id": `${d.siteUrl}/#organization` } },
        itemList(L.courses, slugs, NAMES[d.lang]),
      ];
    }, []),
  },
};
