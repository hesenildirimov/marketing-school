import { ROUTES } from "./_includes/routes-map.js";
export default {
  permalink: (d) => (process.env.DEMO === "1" ? `${ROUTES.thanks[d.lang].replace(/\//g, "-")}.html` : `${ROUTES.thanks[d.lang]}/index.html`),
  eleventyComputed: {
    slug: (d) => d.routes?.thanks?.[d.lang],
    seo_title: (d) => `${d.loc?.[d.lang]?.site?.thanks?.title} | Marketing School`,
    seo_description: (d) => `${d.loc?.[d.lang]?.site?.thanks?.title}.`,
  },
};
