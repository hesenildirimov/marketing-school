import { ROUTES } from "./_includes/routes-map.js";
import { safe } from "./_includes/safe.js";
import { faqPage } from "./_includes/schema.js";
export default {
  permalink: (d) => (process.env.DEMO === "1" ? `${ROUTES.faq[d.lang].replace(/\//g, "-")}.html` : `${ROUTES.faq[d.lang]}/index.html`),
  eleventyComputed: {
    slug: (d) => d.routes?.faq?.[d.lang],
    alternates: (d) => d.routes?.faq,
    seo_title: (d) => d.loc?.[d.lang]?.faq.seo_title,
    seo_description: (d) => d.loc?.[d.lang]?.faq.seo_description,
    crumbs: (d) => [["FAQ", d.routes?.faq?.[d.lang]]],
    ld: safe((d) => [faqPage(d.loc?.[d.lang]?.faq.items, d.routes?.faq?.[d.lang], d.lang, { dateModified: d.loc?.[d.lang]?.faq.updated })], []),
  },
};
