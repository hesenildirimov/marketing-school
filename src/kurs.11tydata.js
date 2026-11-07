import { ROUTES } from "./_includes/routes-map.js";
import { safe } from "./_includes/safe.js";
import { course, faqPage } from "./_includes/schema.js";
export default {
  permalink: (d) => {
    const s = ROUTES.course[d.p.key][d.p.lang];
    return process.env.DEMO === "1" ? `${s.replace(/\//g, "-")}.html` : `${s}/index.html`;
  },
  eleventyComputed: {
    lang: (d) => d.p?.lang,
    slug: (d) => d.routes?.course?.[d.p?.key]?.[d.p?.lang],
    alternates: (d) => d.routes?.course?.[d.p?.key],
    seo_title: (d) => d.p?.c.seo_title,
    seo_description: (d) => d.p?.c.seo_description,
    crumbs: (d) => [[d.p?.c.name, d.routes?.course?.[d.p?.key]?.[d.p?.lang]]],
    ld: safe((d) => {
      const slug = d.routes?.course?.[d.p?.key]?.[d.p?.lang];
      const items = d.loc?.[d.p?.lang]?.faq.items.filter((f) => (f.show_on || []).includes(d.p?.key));
      return [course(d.p?.c, `${d.siteUrl}/${slug}/`, d.p?.lang), ...(items.length ? [faqPage(items, slug, d.p?.lang)] : [])];
    }, []),
  },
};
