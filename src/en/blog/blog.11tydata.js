import { safe } from "../../_includes/safe.js";
import { post } from "../../_includes/schema.js";
const ymd = (d) => (d ? new Date(d).toISOString().slice(0, 10) : undefined);
// İngilis bloq yazıları: /en/blog/<slug>/ . translation_key AZ versiya ilə hreflang əlaqəsini qurur.
export default {
  layout: "post.njk",
  ogType: "article",
  lang: "en",
  eleventyComputed: {
    slug: (d) => `en/blog/${d.page.fileSlug}`,
    permalink: (d) => (d.draft ? false : process.env.DEMO === "1" ? `en-blog-${d.page.fileSlug}.html` : `en/blog/${d.page.fileSlug}/index.html`),
    seo_title: (d) => d.seo_title || d.title,
    seo_description: (d) => d.description,
    crumbs: (d) => [["Blog", "en/blog"], [d.title, `en/blog/${d.page.fileSlug}`]],
    alternates: (d) => {
      const out = {};
      for (const [l, base] of Object.entries(d.routes?.postBase || {})) {
        const s = d.postIndex?.[l]?.[d.translation_key];
        if (s) out[l] = `${base}/${s}`;
      }
      return out;
    },
    ld: safe((d) => [post({ ...d, ymd: ymd(d.page.date), updatedYmd: ymd(d.updated) }, `https://marketingschool.az/en/blog/${d.page.fileSlug}/`, "en")], []),
  },
};
