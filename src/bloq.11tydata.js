import { ROUTES } from "./_includes/routes-map.js";
import { safe } from "./_includes/safe.js";
import { blog } from "./_includes/schema.js";
const NAMES = { az: "Marketing School Bloqu", en: "Marketing School Blog", ru: "Блог Marketing School" };
export default {
  permalink: (d) => (process.env.DEMO === "1" ? `${ROUTES.blog[d.lang].replace(/\//g, "-")}.html` : `${ROUTES.blog[d.lang]}/index.html`),
  eleventyComputed: {
    slug: (d) => d.routes?.blog?.[d.lang],
    alternates: (d) => d.routes?.blog,
    seo_title: (d) => d.loc?.[d.lang]?.blog.seo_title,
    seo_description: (d) => d.loc?.[d.lang]?.blog.seo_description,
    crumbs: (d) => [[d.loc?.[d.lang]?.ui.blog, d.routes?.blog?.[d.lang]]],
    ld: safe((d) => [blog(d.collections?.["posts_" + d.lang] || [], d.routes?.blog?.[d.lang], NAMES[d.lang], d.lang)], []),
  },
};
